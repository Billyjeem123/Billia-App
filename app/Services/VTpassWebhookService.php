<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Models\TransactionLog;
use App\Notifications\VtPassTransactionFailed;
use App\Notifications\VtPassTransactionSuccessful;
use Illuminate\Support\Facades\DB;

class VTpassWebhookService
{
    public function handleTransactionUpdate(array $data)
    {
        $requestId = $data['content']['transactions']['transactionId'] ?? null;
        $responseCode = $data['code'];
        $transactionData = $data;

       #  Find the transaction by requestId
        $transaction = TransactionLog::where('vtpass_transaction_id', $requestId)->first();

        if (!$transaction) {
            BillLogger::log('Transaction not found for webhook', ['requestId' => $requestId]);
            return;
        }

        $AlreadyProcessed =   $this->isAlreadyProcessed($requestId);
        if($AlreadyProcessed){
            BillLogger::log("Vtpass transaction already processed", ['requestId' => $requestId]);
            return ['success' => true, 'message' => 'Already processed'];
        }


        DB::beginTransaction();

        try {
           #  Handle different response codes
            switch ($responseCode) {
                case '000':#  Transaction successful/delivered
                    $this->handleSuccessfulTransaction($transaction, $data, $transactionData);
                    break;

                case '040':#  Transaction reversed
                    $this->handleReversedTransaction($transaction, $data, $transactionData);
                    break;

                default:
                    $this->handleOtherStatusUpdate($transaction, $data, $transactionData);
                    break;
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            BillLogger::error('Transaction update failed', [
                'requestId' => $requestId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function handleSuccessfulTransaction($transaction, $data, $transactionData): void
    {
        DB::transaction(function () use ($transaction, $data, $transactionData) {
        $transaction->update([
            'status' => 'successful',
            'description' => "Payment for: " . ($transactionData['content']['transactions']['product_name'] ?? 'Unknown'),
            'vtpass_webhook_data' => json_encode($data)
        ]);

        BillLogger::log('Transaction completed successfully', [
            'requestId' => $transaction->request_id,
            'transactionId' => $transactionData['transactionId'] ?? null
        ]);

        });

        $transaction->load('user');
       #  Send notification to user
        if ($transaction->user) {
            $transaction->user->notify(new VtPassTransactionSuccessful($transactionData, 'success'));
        }
    }


    private function handleReversedTransaction($transaction, $data, $transactionData): void
    {
        DB::transaction(function () use ($transaction, $data, $transactionData) {
            $reversalAmount = floatval($data['amount'] ?? 0);
            $wallet = $transaction->wallet;

           #  Update the transaction
            $transaction->update([
                'status' => 'failed',
                'description' => "Refund for payment: " . ($transactionData['content']['transactions']['product_name'] ?? 'Unknown'),
                'webhook_data' => json_encode($data),
            ]);

           #  Credit user's wallet
            if ($transaction->user && $reversalAmount > 0) {
                $this->creditUserWallet($transaction->user, $reversalAmount, $transaction);
            }

            $referenceId = Utility::txRef("reverse", "system", false);


             TransactionLog::create([
                'user_id' => $transaction->user->id,
                'wallet_id' => $transaction->wallet->id,
                'type' => 'credit',
                'amount' => $reversalAmount,
                'transaction_reference' => $referenceId,
                'service_type' => $transaction->service_type,
                'amount_after' => $wallet->fresh()->amount + $reversalAmount,
                'status' => 'successful',
                'provider' => 'System',
                'channel' => 'Internal',
                'currency' => 'NGN',
                'description' => "Refund for payment: " . ($transactionData['content']['transactions']['product_name'] ?? 'Unknown'),
                'provider_response' => json_encode([
                    'transfer_type' => 'in_app',
                    'transactionWebhookData' => $transactionData,
                ]),
                'payload' => json_encode([
                    'refund_status' =>"reversal",
                    'provider' => "vtu"
                ]),
            ]);



           #  Log reversal (you could log outside transaction if it's not DB-based)
            BillLogger::log('Transaction reversed', [
                'requestId' => $transaction->request_id,
                'amount' => $reversalAmount,
            ]);
        });

        $transaction->load('user');
        if ($transaction->user) {
            $transaction->user->notify(new VtPassTransactionFailed($transactionData, 'failed'));
        }
    }


    private function handleOtherStatusUpdate($transaction, $data, $transactionData): void
    {
        $status = $transactionData['status'] ?? 'unknown';

        $transaction->update([
            'status' => $status,
            'vtpass_transaction_id' => $transactionData['transactionId'] ?? null,
            'response_description' => $data['response_description'],
            'webhook_data' => json_encode($data)
        ]);

       BillLogger::log('Transaction status updated', [
            'requestId' => $transaction->request_id,
            'status' => $status,
            'code' => $data['code']
        ]);
    }

    private function creditUserWallet($user, $amount, $transaction): void
    {
        $wallet = $transaction->wallet;
        $wallet->increment('amount', $amount);

        BillLogger::log('User wallet credited', [
            'user_id' => $user->id,
            'amount' => $amount,
            'transaction_id' => $transaction->id
        ]);
    }

    private function isAlreadyProcessed(string $vtpass_transaction_id): bool
    {
        return TransactionLog::where('vtpass_transaction_id', $vtpass_transaction_id)
            ->whereNotNull('vtpass_webhook_data')
            ->exists();
    }


}
