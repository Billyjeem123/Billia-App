<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InAppTransferController extends Controller
{
    public function inAppTransfer(GlobalRequest $request)
    {
        $validated = $request->validated();

        $sender = Auth::user();
        $identifier = $validated['identifier'];
        $amount = abs($validated['amount']);
        $ref_id = Utility::txRef("in-app", "paystack", true);

        PaymentLogger::log('Initiating In-app-transfer', [
            'sender_id' => $sender->id,
            'identifier' => $identifier,
            'amount' => $amount,
            'reference' => $ref_id
        ]);

        $recipient = User::findByEmailOrAccountNumber($identifier);
        if (!$recipient) {
            return Utility::outputData(false, 'Recipient not found', [], 200);
        }

        if ($recipient->id === $sender->id) {
            return Utility::outputData(false, 'Self-transfer not allowed', [], 200);
        }

        $sender_balance = Wallet::check_balance();
        if ($amount > $sender_balance) {
            return Utility::outputData(false, 'Insufficient balance', [], 200);
        }

        DB::beginTransaction();

        try {
            $this->debit($sender, $amount, $ref_id);
            $this->credit($recipient, $amount, $ref_id);

            $transaction = $this->logInAppTransfer($sender, $recipient, $amount, $ref_id);

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
                    'new_balance' => Wallet::check_balance()
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            PaymentLogger::log('Transfer failed with error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'reference' => $ref_id
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Transfer failed. Please try again.',
                'reference' => $ref_id,
                'data' => Utility::getExceptionDetails($e)
            ], 500);
        }
    }


    public static function logInAppTransfer(User $sender, User $recipient, float $amount, string $ref_id): array
    {
        $sender_wallet = $sender->wallet;
        $recipient_wallet = optional($recipient->fresh())->wallet;

        $sender_balance_before = $sender_wallet->amount;
        $recipient_balance_before = $recipient_wallet?->amount ?? 0;

        // Fetch balances after the debit/credit methods
        $sender_balance_after = Wallet::check_balance();
        $recipient_balance_after = optional($recipient_wallet)->fresh()->amount ?? 0;

        $sender_tx = TransactionLog::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender_wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_before' => $sender_balance_before,
            'amount_after' => $sender_balance_after,
            'status' => 'successful',
            'provider' => 'System',
            'channel' => 'Internal',
            'currency' => 'NGN',
            'description' => 'In-app-transfer',
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
            ]),
            'payload' => json_encode([
                'identifier' => $recipient->email ?? $recipient->username,
                'amount' => $amount
            ]),
        ]);

        $recipient_tx = TransactionLog::create([
            'user_id' => $recipient->id,
            'wallet_id' => $recipient_wallet?->id,
            'type' => 'credit',
            'amount' => $amount,
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_before' => $recipient_balance_before,
            'amount_after' => $recipient_balance_after,
            'status' => 'successful',
            'provider' => 'System',
            'channel' => 'Internal',
            'currency' => 'NGN',
            'description' => 'In-app-transfer',
            'provider_response' => json_encode([
                'transfer_type' => 'in-app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
            ]),
            'payload' => json_encode([
                'identifier' => $recipient->email ?? $recipient->username,
                'amount' => $amount
            ]),
        ]);

        return [
            'sender_transaction_id' => $sender_tx->id,
            'recipient_transaction_id' => $recipient_tx->id,
        ];
    }


    public static function logInAppTransfer001(User $sender, User $recipient, float $amount, string $ref_id): array
    {
        #  Fetch initial balances
        $sender_wallet = $sender->wallet;
        $recipient_wallet = optional($recipient->fresh())->wallet;

        $sender_balance_before = $sender_wallet->amount;
        $recipient_balance_before = $recipient_wallet?->amount ?? 0;

        #  Perform balance update
        $sender_wallet->amount -= $amount;
        $sender_wallet->save();

        $recipient_wallet?->increment('amount', $amount);

        #  Fetch balances after the transfer
        $sender_balance_after = $sender_wallet->fresh()->amount;
        $recipient_balance_after = $recipient_wallet?->fresh()->amount ?? 0;


        $sender_tx = TransactionLog::create([
            'user_id' => $sender->id,
            'wallet_id' => $sender_wallet->id,
            'type' => 'debit',
            'amount' => $amount,
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_after' => $sender_balance_before + $sender_balance_after,
            'status' => 'successful',
            'provider' => 'System',
            'channel' => 'Internal',
            'currency' => 'NGN',
            'description' => 'In-app-transfer',
            'provider_response' => json_encode([
                'transfer_type' => 'in_app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'sender_balance_before' => $sender_balance_before,
                'sender_balance_after' => $sender_balance_after,
            ]),
            'payload' => json_encode([
                "identifier" => $recipient,
                 "amount" => $amount
            ]),

        ]);


        $recipient_tx = TransactionLog::create([
            'user_id' => $recipient->id,
            'wallet_id' => $recipient_wallet?->id,
            'type' => 'credit',
            'amount' => $amount,
            'transaction_reference' => $ref_id,
            'service_type' => 'in-app-transfer',
            'amount_after' => $recipient_balance_before + $recipient_balance_after,
            'status' => 'successful',
            'provider' => 'System',
            'channel' => 'Internal',
            'currency' => 'NGN',
            'description' => 'In-app-transfer',
            'provider_response' => json_encode([
                'transfer_type' => 'in-app',
                'from' => $sender->email,
                'sender_id' => $sender->id,
                'recipient_balance_before' => $recipient_balance_before,
                'recipient_balance_after' => $recipient_balance_after,
            ]),
            'payload' => json_encode([
                "identifier" => $recipient,
                "amount" => $amount
            ]),

        ]);

        return [
            'sender_transaction_id' => $sender_tx->id,
            'recipient_transaction_id' => $recipient_tx->id,
        ];
    }


    public static function debit(User $user, float $amount, string $reference): bool
    {
        $balance = Wallet::check_balance();

        PaymentLogger::log('Debiting wallet', [
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_before' => $balance,
            'reference' => $reference
        ]);

        Wallet::remove_From_wallet($amount);

        $balance_after = Wallet::check_balance();

        PaymentLogger::log('Wallet debited successfully', [
            'user_id' => $user->id,
            'balance_after' => $balance_after,
            'reference' => $reference
        ]);

        return true;
    }

    public static function credit(User $recipient, float $amount, string $reference): bool
    {
        $balance_before = optional($recipient->wallet)->amount ?? 0;

        PaymentLogger::log('Crediting wallet', [
            'user_id' => $recipient->id,
            'amount' => $amount,
            'balance_before' => $balance_before,
            'reference' => $reference
        ]);

        Wallet::credit_recipient($amount, $recipient->id);

        $balance_after = optional($recipient->fresh()->wallet)->amount ?? 0;

        PaymentLogger::log('Wallet credited successfully', [
            'user_id' => $recipient->id,
            'balance_after' => $balance_after,
            'reference' => $reference
        ]);

        return true;
    }


}
