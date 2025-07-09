<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\TransactionLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ActivityTracker;
use App\Services\FraudDetectionService;
use App\Services\FraudLogger;
use App\Services\PaymentLogger;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class SecureInAppTransferController extends Controller
{


    public  $fraudDetection;

    public  $tracker;

    public function __construct(FraudDetectionService $fraudDetection, ActivityTracker $activityTracker)
    {
        $this->fraudDetection = $fraudDetection;
        $this->tracker = $activityTracker;
    }


    public function InAppTransferNow(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        try {
            #  Step 1: Validate request data
            $transferData = $this->validateTransferRequest($validated);

            #  Step 2: Perform fraud checks
            $this->performFraudChecks($transferData);

            #  Step 3: Execute transfer
            $result = $this->executeTransfer($transferData);

            return $this->successResponse($result);

        } catch (TransferException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            PaymentLogger::log('Transfer failed with unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $transferData['reference'] ?? null
            ]);

            return $this->errorResponse('Transfer failed. Please try again.', 500);
        }
    }



    /**
     * Validate and prepare transfer request data
     */
    private function validateTransferRequest(array $validated): array
    {
        $sender = Auth::user();
        $amount = $this->validateAndSanitizeAmount($validated['amount']);

        if ($amount <= 0) {
            throw new TransferException('Invalid amount. Amount must be positive.', 400);
        }

        $sender_balance = Wallet::check_balance();
        if ($amount > $sender_balance) {
            throw new TransferException('Insufficient balance.', 200);
        }


       #  Check transaction limits
        $this->checkTransactionLimits($sender, $amount);

       // $this->IsDuplicateTransfer($sender, $amount, $validated['identifier']);

        $recipient = $this->validateRecipient($validated['identifier'], $sender);

        return [
            'sender' => $sender,
            'recipient' => $recipient,
            'amount' => $amount,
            'identifier' => $validated['identifier'],
            'reference' => Utility::txRef("in-app", "paystack", true),
            'idempotency_key' => $this->generateIdempotencyKey($sender, $validated['identifier'], $amount)
        ];
    }

    /**
     * Perform fraud detection checks
     */
    private function performFraudChecks(array $transferData): void
    {
        $fraudCheck = $this->fraudDetection->checkTransaction(
            $transferData['sender'],
            $transferData['amount'],
            'debit',
            [
                'transaction_type' => 'wallet_transfer_out',
                'recipient_identifier' => $transferData['identifier'],
                'reference' => $transferData['reference']
            ]
        );

        if (!$fraudCheck['passed']) {
            $this->logFraudAlert($transferData, $fraudCheck);
            throw new TransferException($fraudCheck['message'], 403);
        }
    }

    /**
     * Execute the actual transfer within a database transaction
     */
    private function executeTransfer(array $transferData): array
    {
        DB::beginTransaction();

        try {
           #  Lock wallets and validate balances
            $wallets = $this->lockWalletsForTransfer(
                $transferData['sender'],
                $transferData['recipient'],
                $transferData['amount']
            );

           #  Execute the transfer
            $this->executeTransferNow(
                $wallets['sender'],
                $wallets['recipient'],
                $transferData['amount'],
                $transferData['reference']
            );

           #  Log the transaction
            $transactionIds = $this->logTransferTransaction($transferData);

           #  Track the activity
            $this->trackTransferActivity($transferData);

            DB::commit();

            return [
                'transfer_data' => $transferData,
                'transaction_ids' => $transactionIds,
                'new_balance' => $this->getAvailableBalance($transferData['sender']->id)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate idempotency key for duplicate prevention
     */
    private function generateIdempotencyKey(User $sender, string $identifier, float $amount): string
    {
        return hash('sha256', $sender->id . $identifier . $amount . time());
    }

    /**
     * Log fraud alert
     */
    private function logFraudAlert(array $transferData, array $fraudCheck): void
    {
        FraudLogger::logFraudAlert('Transaction blocked by fraud detection', [
            'user_id' => $transferData['sender']->id,
            'amount' => $transferData['amount'],
            'fraud_check_id' => $fraudCheck['fraud_check_id'],
            'reason' => $fraudCheck['message']
        ]);
    }

    /**
     * Log transfer transaction for both sender and recipient
     */
    private function logTransferTransaction(array $transferData): array
    {
        return  $this->logInAppTransfer(
            $transferData['sender'],
            $transferData['recipient'],
            $transferData['amount'],
            $transferData['reference'],
            $transferData['idempotency_key']
        );
    }

    /**
     * Track transfer activity
     */
    private function trackTransferActivity(array $transferData): void
    {
        $this->tracker->track(
            'wallet_in_app_transfer',
            "In-app transfer of ₦" . number_format($transferData['amount']) .
            " from {$transferData['sender']->first_name} to {$transferData['recipient']->first_name}",
            [
                'sender_id' => $transferData['sender']->id,
                'recipient_id' => $transferData['recipient']->id,
                'amount' => $transferData['amount'],
                'reference' => $transferData['reference'],
                'identifier_used' => $transferData['identifier'],
                'idempotency_key' => $transferData['idempotency_key'],
                'effective' => true,
            ]
        );
    }

    /**
     * Return success response
     */
    private function successResponse(array $result): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Transfer successful',
            'reference' => $result['transfer_data']['reference'],
            'data' => [
                'amount' => $result['transfer_data']['amount'],
                'recipient' => [
                    'name' => $result['transfer_data']['recipient']->name ?? $result['transfer_data']['recipient']->email,
                    'email' => $result['transfer_data']['recipient']->email
                ],
                'new_balance' => $result['new_balance']
            ]
        ], 200);
    }

    /**
     * Return error response
     */
    private function errorResponse(string $message, int $code): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => []
        ], $code);
    }




    /**
     * Lock wallets for transfer and validate balances
     */
    public function lockWalletsForTransfer(User $sender, User $recipient, float $amount): array
    {
        $senderWallet = Wallet::where('user_id', $sender->id)
            ->lockForUpdate()
            ->first();

        if (!$senderWallet) {
            throw new TransferException('Sender wallet not found', 404);
        }

        if ($senderWallet->status === 'locked') {
            throw new TransferException('Wallet is currently locked for processing. Please try again later.', 423);
        }

        $recipientWallet = Wallet::where('user_id', $recipient->id)
            ->lockForUpdate()
            ->first();

        if (!$recipientWallet) {
            throw new TransferException('Recipient wallet not found', 404);
        }

        if ($recipientWallet->status === 'locked') {
            throw new TransferException('Recipient wallet is currently locked. Please try again later.', 423);
        }

        $availableBalance = $senderWallet->amount - $senderWallet->locked_amount;
        if ($amount > $availableBalance) {
            throw new TransferException('Insufficient available balance', 400);
        }

        return [
            'sender' => $senderWallet,
            'recipient' => $recipientWallet
        ];
    }

    /**
     * Execute the actual transfer between wallets
     */
    public function executeTransferNow(Wallet $senderWallet, Wallet $recipientWallet, float $amount, string $reference): void
    {
       #  Lock the amount in sender's wallet
       $this->lockAmount($senderWallet, $amount, $reference);

        try {
           #  Perform the transfer
            $this->debitWallet($senderWallet, $amount, $reference);
            $this->creditWallet($recipientWallet, $amount, $reference);

           #  Unlock the amount after successful transfer
            $this->unlockAmount($senderWallet, $amount, $reference);
        } catch (\Exception $e) {
           #  Unlock amount if transfer fails
            $this->unlockAmount($senderWallet, $amount, $reference);
            throw $e;
        }
    }

    /**
     * Lock amount in wallet during processing
     */
    private function lockAmount(Wallet $wallet, float $amount, string $reference)
    {
        $wallet->increment('locked_amount', $amount);

        PaymentLogger::log('Amount locked in wallet', [
            'wallet_id' => $wallet->id,
            'amount_locked' => $amount,
            'total_locked' => $wallet->fresh()->locked_amount,
            'reference' => $reference
        ]);

        return;
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
     * Debit wallet with security checks
     */
    private function debitWallet(Wallet $wallet, float $amount, string $reference): void
    {
        $balanceBefore = $wallet->amount;

        if ($wallet->locked_amount < $amount) {
            throw new TransferException('Locked funds are insufficient for this debit.', 400);
        }

        PaymentLogger::log('Debiting wallet', [
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'locked_amount' => $wallet->locked_amount,
            'reference' => $reference
        ]);

        $affected = Wallet::where('id', $wallet->id)
            ->where('amount', '>=', $amount)
            ->decrement('amount', $amount);

        if ($affected === 0) {
            throw new TransferException('Failed to debit wallet - insufficient funds or wallet locked', 400);
        }

        PaymentLogger::log('Wallet debited successfully', [
            'wallet_id' => $wallet->id,
            'balance_after' => $wallet->fresh()->amount,
            'reference' => $reference
        ]);
    }

    /**
     * Credit wallet
     */
    private function creditWallet(Wallet $wallet, float $amount, string $reference): void
    {
        $balanceBefore = $wallet->amount;

        PaymentLogger::log('Crediting wallet', [
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'reference' => $reference
        ]);

        $wallet->increment('amount', $amount);

        PaymentLogger::log('Wallet credited successfully', [
            'wallet_id' => $wallet->id,
            'balance_after' => $wallet->fresh()->amount,
            'reference' => $reference
        ]);
    }

    /**
     * Get available balance (total - locked)
     */
    public function getAvailableBalance(int $userId): float
    {
        $wallet = Wallet::where('user_id', $userId)->first();
        return $wallet ? ($wallet->amount - $wallet->locked_amount) : 0;
    }



    /**
     * Validate and sanitize amount
     */
    public function validateAndSanitizeAmount($amount): float
    {
        $amount = floatval($amount);

        if ($amount <= 0) {
            return 0.0;
        }

        return round($amount, 2);
    }


    /**
     * Check transaction limits
     */
    private function IsDuplicateTransfer(User $sender, float $amount, $identifier): void
    {
        $recentTransaction = TransactionLog::isDuplicateTransfer($sender->id, $amount, $identifier);
         if ($recentTransaction) {
             throw new TransferException("Possible Duplicate Transfer, Please try again later", 429);
       }
    }

    /**
     * Check transaction limits
     */
    public function checkTransactionLimits(User $sender, float $amount): void
    {
        [$limitOk, $limitMessage] = TransactionLog::checkLimits($sender, $amount);
        if (!$limitOk) {
            throw new TransferException($limitMessage, 403);
        }
    }


    /**
     * Validate recipient and prevent self-transfer
     */
    public function validateRecipient(string $identifier, User $sender): User
    {
        $recipient = User::findByEmailOrAccountNumber($identifier);
        if (!$recipient) {
            throw new TransferException('Recipient not found', 404);
        }

        if ($recipient->id === $sender->id) {
            throw new TransferException('Self-transfer not allowed', 400);
        }

        return $recipient;
    }

    /**
     * Log in-app transfer for both sender and recipient
     */
    public static function logInAppTransfer(User $sender, User $recipient, float $amount, string $refId, string $idempotencyKey): array
    {
        $senderWallet = Wallet::where('user_id', $sender->id)->first();
        $recipientWallet = Wallet::where('user_id', $recipient->id)->first();

        $senderBalanceBefore = $senderWallet->amount + $amount;
        $recipientBalanceBefore = $recipientWallet->amount - $amount;

        $commonPayload = [
            'amount' => $amount,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'idempotency_key' => $idempotencyKey
        ];

        $senderTx = TransactionLog::create([
            'user_id' => $sender->id,
            'wallet_id' => $senderWallet->id,
            'type' => 'debit',
            'category' => 'wallet_transfer_out',
            'amount' => $amount,
            'transaction_reference' => $refId,
            'service_type' => 'in-app-transfer',
            'amount_before' => $senderBalanceBefore,
            'amount_after' => $senderWallet->amount,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => 'Sent to ' . $recipient->first_name . ' ' . $recipient->last_name,
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'security_check' => 'passed',
                'timestamp' => now()->toISOString()
            ]),
            'payload' => json_encode(array_merge($commonPayload, [
                'identifier' => $recipient->email ?? $recipient->username
            ])),
        ]);

        $recipientTx = TransactionLog::create([
            'user_id' => $recipient->id,
            'wallet_id' => $recipientWallet->id,
            'type' => 'credit',
            'category' => 'wallet_transfer_in',
            'amount' => $amount,
            'transaction_reference' => $refId,
            'service_type' => 'in-app-transfer',
            'amount_before' => $recipientBalanceBefore,
            'amount_after' => $recipientWallet->amount,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => 'Received from ' . $sender->first_name . ' ' . $sender->last_name,
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'security_check' => 'passed',
                'timestamp' => now()->toISOString()
            ]),
            'payload' => json_encode(array_merge($commonPayload, [
                'identifier' => $recipient->email ?? $recipient->username
            ])),
        ]);

        return [
            'sender_transaction_id' => $senderTx->id,
            'recipient_transaction_id' => $recipientTx->id,
        ];
    }




}
