<?php

namespace App\Services;

use App\Models\TransactionLog;
use App\Notifications\VtPassTransactionSuccessful;
use Illuminate\Support\Facades\DB;

class VTpassWebhookService
{
    public function handleTransactionUpdate(array $data): void
    {
        $requestId = $data['content']['transactions']['transactionId'] ?? null;
        $responseCode = $data['code'];
        $transactionData = $data['content']['transactions'] ?? [];

       #  Find the transaction by requestId
        $transaction = TransactionLog::where('vtpass_transaction_id', $requestId)->first();

        if (!$transaction) {
            BillLogger::log('Transaction not found for webhook', ['requestId' => $requestId]);
            return;
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
        $transaction->update([
            'status' => 'successful',
            'vtpass_transaction_id' => $transactionData['transactionId'] ?? null,
            'vtpass_webhook_data' => json_encode($data)
        ]);

        BillLogger::log('Transaction completed successfully', [
            'requestId' => $transaction->request_id,
            'transactionId' => $transactionData['transactionId'] ?? null
        ]);

       #  Send notification to user
        if ($transaction->user) {
            $transaction->user->notify(new VtPassTransactionSuccessful($transaction, 'completed'));
        }
    }

    private function handleReversedTransaction($transaction, $data, $transactionData): void
    {
        $reversalAmount = $data['amount'];

        $transaction->update([
            'status' => 'reversed',
            'vtpass_transaction_id' => $transactionData['transactionId'] ?? null,
            'description' => "Refund for transaction: {$transaction->request_id}",
            'webhook_data' => json_encode($data)
        ]);

       #  Credit user's wallet if you have a wallet system
        if ($transaction->user && $reversalAmount > 0) {
            $this->creditUserWallet($transaction->user, $reversalAmount, $transaction);
        }

       BillLogger::log('Transaction reversed', [
            'requestId' => $transaction->request_id,
            'amount' => $reversalAmount,
            'walletCreditId' => $walletCreditId
        ]);

       #  Send notification to user
        if ($transaction->user) {
            $transaction->user->notify(new TransactionStatusNotification($transaction, 'reversed'));
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

        $oldBalance = $wallet->amount;
        $wallet->increment('amount', $oldBalance);

        BillLogger::log('User wallet credited', [
            'user_id' => $user->id,
            'amount' => $amount,
            'transaction_id' => $transaction->id
        ]);
    }
}
