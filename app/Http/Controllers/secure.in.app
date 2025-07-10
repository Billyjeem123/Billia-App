<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Http\Requests\GlobalRequest;
use App\Models\TransactionLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ActivityTracker;
use App\Services\FraudDetectionService;
use App\Helpers\FraudLogger;
use App\Helpers\PaymentLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecureInAppTransferController extends Controller
{

    private FraudDetectionService $fraudDetection;

    private  $tracker;

    public function __construct(FraudDetectionService $fraudDetection, ActivityTracker $activityTracker)
    {
        $this->fraudDetection = $fraudDetection;
        $this->tracker = $activityTracker;
    }
    public function InAppTransferNow(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

       #  Validate and sanitize amount to prevent negative values and precision issues
        $amount = $this->validateAndSanitizeAmount($validated['amount']);
        if ($amount <= 0) {
            return Utility::outputData(false, 'Invalid amount. Amount must be positive.', [], 400);
        }
        $sender = Auth::user();

        # Enforce tier-based limits
        [$limitOk, $limitMessage] = TransactionLog::checkLimits($sender, $amount);
        if (!$limitOk) {
            return Utility::outputData(false, $limitMessage, [], 403);
        }

        $identifier = $validated['identifier'];
        $ref_id = Utility::txRef("in-app", "paystack", true);

       #  Generate idempotency key to prevent duplicate transactions.  This ensures that if the request is retried (e.g., due to network failure or user double-click),
        $idempotencyKey = hash('sha256', $sender->id . $identifier . $amount . time());# \\

        PaymentLogger::log('Initiating In-app-transfer', [
            'sender_id' => $sender->id,
            'identifier' => $identifier,
            'amount' => $amount,
            'reference' => $ref_id,
            'idempotency_key' => $idempotencyKey
        ]);

       #  Check for duplicate transaction within last 5 minutes
#         $recentTransaction = TransactionLog::isDuplicateTransfer($sender->id, $amount, $identifier);
#         if ($recentTransaction) {
#             return Utility::outputData(false, 'Possible Duplicate Transfer, Please try again later', [], 429);
#         }

        $recipient = User::findByEmailOrAccountNumber($identifier);
        if (!$recipient) {
            return Utility::outputData(false, 'Recipient not found', [], 404);
        }

        if ($recipient->id === $sender->id) {
            return Utility::outputData(false, 'Self-transfer not allowed', [], 400);
        }

        #  Lock sender's wallet for exclusive access
        $senderWallet = Wallet::where('user_id', $sender->id)
            ->lockForUpdate()
            ->first();

        if (!$senderWallet) {
            DB::rollBack();
            return Utility::outputData(false, 'Sender wallet not found', [], 404);
        }

        $fraudCheck = $this->fraudDetection->checkTransaction(
            $sender,
            $amount,
            'debit',
            [
                'transaction_type' => 'wallet_transfer_out',
                'recipient_identifier' => $identifier,
                'reference' => $ref_id
            ]
        );
        if (!$fraudCheck['passed']) {
            #  Log the blocked transaction
            DB::rollBack();
            FraudLogger::logFraudAlert('Transaction blocked by fraud detection', [
                'user_id' => $sender->id,
                'amount' => $amount,
                'fraud_check_id' => $fraudCheck['fraud_check_id'],
                'reason' => $fraudCheck['message']
            ]);

            return Utility::outputData(false, $fraudCheck['message'], [], 403);
        }


       #  Use database transaction with row-level locking
        DB::beginTransaction();

        try {

            #  Check if wallet is already locked for processing
            if ($senderWallet->status === 'locked') {
                DB::rollBack();
                return Utility::outputData(false, 'Wallet is currently locked for processing. Please try again later.', [], 423);
            }

           #  Lock recipient's wallet
            $recipientWallet = Wallet::where('user_id', $recipient->id)
                ->lockForUpdate()
                ->first();

            if (!$recipientWallet) {
                DB::rollBack();
                return Utility::outputData(false, 'Recipient wallet not found', [], 404);
            }

            if ($recipientWallet->status === 'locked') {
                DB::rollBack();
                return Utility::outputData(false, 'Recipient wallet is currently locked. Please try again later.', [], 423);
            }

           #  Calculate available balance (total - locked)
            $availableBalance = $senderWallet->amount - $senderWallet->locked_amount;

            if ($amount > $availableBalance) {
                DB::rollBack();
                return Utility::outputData(false, 'Insufficient available balance', [], 400);
            }

           #  Lock the amount in sender's wallet
            $this->lockAmount($senderWallet, $amount, $ref_id);

           #  Perform the transfer
            $this->debit($sender, $amount, $ref_id);
            $this->credit($recipient, $amount, $ref_id);

           #  Unlock the amount after successful transfer
            $this->unlockAmount($senderWallet, $amount, $ref_id);

            $this->logInAppTransfer($sender, $recipient, $amount, $ref_id, $idempotencyKey);
            #  ✅ Add tracker here
            $this->tracker->track(
                'wallet_in_app_transfer',
                "In-app transfer of ₦" . number_format($amount) . " from {$sender->first_name} to {$recipient->first_name}",
                [
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'amount' => $amount,
                    'reference' => $ref_id,
                    'identifier_used' => $identifier,
                    'idempotency_key' => $idempotencyKey,
                    'effective' => true,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Transfer successful',
                'reference' => $ref_id,
                'data' => [
                    'amount' => $amount,
                    'recipient' => [
                        'name' => $recipient->name ?? $recipient->email,
                        'email' => $recipient->email
                    ],
                    'new_balance' => $this->getAvailableBalance($sender->id)
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

           #  Unlock any locked amounts if transaction fails
            if (isset($senderWallet)) {
                $this->unlockAmount($senderWallet, $amount, $ref_id);
            }
            PaymentLogger::log('Transfer failed with error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $ref_id
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Transfer failed. Please try again.',
                'reference' => $ref_id,
                'data' => []
            ], 500);
        }
    }

    /**
     * Validate and sanitize amount to prevent precision issues and negative values
     */
    private function validateAndSanitizeAmount($amount): float
    {
        #  Convert string to float directly (preserves negative sign)
        $amount = floatval($amount);

        #  If amount is zero or negative, return 0.0 (invalid)
        if ($amount <= 0) {
            return 0.0;
        }

        #  Round to 2 decimal places for currency
        return round($amount, 2);
    }


    /**
     * Lock amount in wallet during processing
     */
    private function lockAmount(Wallet $wallet, float $amount, string $reference): void
    {
        $wallet->increment('locked_amount', $amount);

        PaymentLogger::log('Amount locked in wallet', [
            'wallet_id' => $wallet->id,
            'amount_locked' => $amount,
            'total_locked' => $wallet->fresh()->locked_amount,
            'reference' => $reference
        ]);
    }

    /**
     * Unlock amount after processing
     */
    private function unlockAmount(Wallet $wallet, float $amount, string $reference): void
    {
        $wallet->decrement('locked_amount', $amount);

        PaymentLogger::log('Amount unlocked in wallet', [
            'wallet_id' => $wallet->id,
            'amount_unlocked' => $amount,
            'total_locked' => $wallet->fresh()->locked_amount,
            'reference' => $reference
        ]);
    }

    /**
     * Get available balance (total - locked)
     */
    private function getAvailableBalance(int $userId): float
    {
        $wallet = Wallet::where('user_id', $userId)->first();
        return $wallet ? ($wallet->amount - $wallet->locked_amount) : 0;
    }

    /**
     * Enhanced debit method with additional security checks
     */
    public static function debit(User $user, float $amount, string $reference): bool
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        if (!$wallet) {
            throw new \Exception('Wallet not found for debit operation');
        }

        $balance_before = $wallet->amount;
        $available_balance = $balance_before - $wallet->locked_amount;

        if ($amount > $available_balance) {
            throw new \Exception('Insufficient available balance for debit');
        }

        PaymentLogger::log('Debiting wallet', [
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'available_balance' => $available_balance,
            'reference' => $reference
        ]);

       #  Perform atomic debit operation
        $affected = Wallet::where('user_id', $user->id)
            ->where('amount', '>=', $amount)
            ->decrement('amount', $amount);

        if ($affected === 0) {
            throw new \Exception('Failed to debit wallet - insufficient funds or wallet locked');
        }

        $balance_after = $wallet->fresh()->amount;

        PaymentLogger::log('Wallet debited successfully', [
            'user_id' => $user->id,
            'balance_after' => $balance_after,
            'reference' => $reference
        ]);

        return true;
    }

    /**
     * Enhanced credit method with additional security checks
     */
    public static function credit(User $recipient, float $amount, string $reference): bool
    {
        $wallet = Wallet::where('user_id', $recipient->id)->lockForUpdate()->first();

        if (!$wallet) {
           #  Create wallet if it doesn't exist
            $wallet = Wallet::create([
                'user_id' => $recipient->id,
                'amount' => 0,
                'locked_amount' => 0,
                'status' => 'active'
            ]);
        }

        $balance_before = $wallet->amount;

        PaymentLogger::log('Crediting wallet', [
            'user_id' => $recipient->id,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'reference' => $reference
        ]);

       #  Perform atomic credit operation
        $wallet->increment('amount', $amount);

        $balance_after = $wallet->fresh()->amount;

        PaymentLogger::log('Wallet credited successfully', [
            'user_id' => $recipient->id,
            'balance_after' => $balance_after,
            'reference' => $reference
        ]);

        return true;
    }

    /**
     * Enhanced logging with additional security context
     */
    public static function logInAppTransfer(User $sender, User $recipient, float $amount, string $ref_id, $idempotencyKey=null): array
    {
        $sender_wallet = Wallet::where('user_id', $sender->id)->first();
        $recipient_wallet = Wallet::where('user_id', $recipient->id)->first();

        $sender_balance_before = $sender_wallet->amount + $amount;#  Before debit
        $recipient_balance_before = $recipient_wallet->amount - $amount;#  Before credit

        $sender_balance_after = $sender_wallet->amount;
        $recipient_balance_after = $recipient_wallet->amount;

        $sender_tx = TransactionLog::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender_wallet->id,
            'type' => 'debit',
            'category' => 'wallet_transfer_out',
            'amount' => $amount,
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_before' => $sender_balance_before,
            'amount_after' => $sender_balance_after,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => 'Sent to ' . $recipient->first_name . ' ' . $recipient->last_name ,
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'security_check' => 'passed',
                'timestamp' => now()->toISOString()
            ]),
            'payload' => json_encode([
                'identifier' => $recipient->email ?? $recipient->username,
                'amount' => $amount,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'idempotencyKey' => $idempotencyKey ?? null
            ]),
        ]);

        $recipient_tx = TransactionLog::create([
            'user_id' => $recipient->id,
            'wallet_id' => $recipient_wallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'category' => 'wallet_transfer_in',
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_before' => $recipient_balance_before,
            'amount_after' => $recipient_balance_after,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => 'Received from '. $sender->first_name . ' ' . $sender->last_name ,
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'security_check' => 'passed',
                'timestamp' => now()->toISOString()
            ]),
            'payload' => json_encode([
                'identifier' => $recipient->email ?? $recipient->username,
                'amount' => $amount,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'idempotencyKey' => $idempotencyKey ?? null
            ]),
        ]);

        return [
            'sender_transaction_id' => $sender_tx->id,
            'recipient_transaction_id' => $recipient_tx->id,
        ];
    }


}
