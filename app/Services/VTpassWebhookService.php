<?php

namespace App\Services;

use App\Events\PushNotificationEvent;
use App\Helpers\BillLogger;
use App\Helpers\Utility;
use App\Models\TransactionLog;
use App\Notifications\VtPassTransactionFailed;
use App\Notifications\VtPassTransactionSuccessful;
use Illuminate\Support\Facades\DB;

class VTpassWebhookService
{

    protected $tracker;

    public function __construct(ActivityTracker $tracker)
    {
        $this->tracker = $tracker;
    }

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

//        $AlreadyProcessed =   $this->isAlreadyProcessed($requestId);
//        if($AlreadyProcessed){
//            BillLogger::log("Vtpass transaction already processed", ['requestId' => $requestId]);
//            return ['success' => true, 'message' => 'Already processed'];
//        }


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

        // Track the successful bill payment
        $this->trackBillPaymentEvent(
            'bill_payment_completed_and_verified',
            $transaction,
            $transactionData,
            [
                'product_name' => $transactionData['content']['transactions']['product_name'] ?? 'Unknown',
                'service_type' => $transaction->service_type,
                'provider' => 'vtpass',
                'webhook_response_code' => $data['code'],
            ]
        );


        $user = $transaction->user;
        $this->sendSafePushNotification(
            $user,
            'Transaction Notification',
            "Payment for: " . ($transactionData['content']['transactions']['product_name'] ?? '_') . " was successful."
        );

        if ($user) {
            $user->notify(new VtPassTransactionSuccessful($transactionData, 'success'));
        }
    }


    private function handleReversedTransaction($transaction, $data, $transactionData): void
    {
        DB::transaction(function () use ($transaction, $data, $transactionData) {
            $reversalAmount = floatval($data['amount'] ?? 0);
            $wallet = $transaction->wallet;
            $oldBalance = $wallet->amount;

           #  Update the transaction
            $transaction->update([
                'status' => 'failed',
              //  'description' => "Refund for payment: " . ($transactionData['content']['transactions']['product_name'] ?? 'Unknown'),
                'vtpass_webhook_data' => json_encode($data),
            ]);

           #  Credit user's wallet
            if ($transaction->user && $reversalAmount > 0) {
                $this->creditUserWallet($transaction->user, $reversalAmount, $transaction);
            }

            $newBalance = $wallet->fresh()->amount;

            $referenceId = Utility::txRef("reverse", "system", false);

             TransactionLog::create([
                'user_id' => $transaction->user->id,
                'wallet_id' => $transaction->wallet->id,
                'type' => 'credit',
                'category' => 'refund',
                'amount' => $reversalAmount,
                'transaction_reference' => $referenceId,
                'service_type' => $transaction->service_type,
                 'amount_before' => $oldBalance,
                'amount_after' => $newBalance,
                'status' => 'successful',
                'provider' => 'system',
                'channel' => 'internal',
                'currency' => 'NGN',
                'description' => "Refund for payment: " . ($transactionData['content']['transactions']['product_name'] ?? 'Unknown'),
                'provider_response' => json_encode([
                    'transfer_type' => 'in_app',
                    'transactionWebhookData' => $transactionData,
                ]),
                'payload' => json_encode([
                    'refund_status' =>"reversal",
                    'provider' => "vtpass"
                ]),
            ]);


           #  Log reversal (you could log outside transaction if it's not DB-based)
            BillLogger::log('Transaction reversed', [
                'requestId' => $transaction->request_id,
                'amount' => $reversalAmount,
            ]);
        });

        $this->trackBillPaymentEvent(
            'bill_payment_reversed',
            $transaction,
            $transactionData,
            [
                'product_name' => $transactionData['content']['transactions']['product_name'] ?? 'Unknown',
                'service_type' => $transaction->service_type,
                'provider' => 'vtpass',
                'reversal_amount' => floatval($data['amount'] ?? 0),
                'webhook_response_code' => $data['code'],
                'reversal_reason' => $data['response_description'] ?? 'Unknown',
            ]
        );

        $user = $transaction->user;
        $this->sendSafePushNotification(
            $user,
            'Transaction Notification',
            "Payment for " . ($transactionData['content']['transactions']['product_name'] ?? '_') . " has been reversed."
        );

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

        $this->trackBillPaymentEvent(
            'bill_payment_status_updated',
            $transaction,
            $transactionData,
            [
                'product_name' => $transactionData['content']['transactions']['product_name'] ?? 'Unknown',
                'service_type' => $transaction->service_type,
                'provider' => 'vtpass',
                'old_status' => $transaction->getOriginal('status'),
                'new_status' => $status,
                'webhook_response_code' => $data['code'],
                'response_description' => $data['response_description'] ?? null,
            ]
        );
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

    private function sendSafePushNotification($user, string $title, string $message): void
    {
        try {
            event(new PushNotificationEvent($user, $title, $message));
        } catch (\Throwable $e) {
            BillLogger::error("Push notification event failed", [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Track bill payment events with predefined templates
     */
    private function trackBillPaymentEvent(
        string $eventType,
        TransactionLog $transaction,
        array $transactionData,
        array $customData = []
    ): void {
        try {
            $eventTemplates = [
                'bill_payment_completed_and_verified' => [
                    'description' => "verified bill payment of ₦{amount} for {product} has been successfully verified and completed",
                ],
                'bill_payment_reversed' => [
                    'description' => "bill payment of ₦{amount} for {product} was reversed and refunded successfully",
                ],
                'bill_payment_status_updated' => [
                    'description' => "bill payment of ₦{amount} for {product} status updated from {old_status} to {new_status}",
                ],
            ];

            if (!isset($eventTemplates[$eventType])) {
                BillLogger::error('Unknown event type for tracking', ['event_type' => $eventType]);
                return;
            }

            $template = $eventTemplates[$eventType];

            // Replace placeholders in description
            $description = str_replace(
                ['{amount}', '{product}', '{old_status}', '{new_status}'],
                [
                    number_format($transaction->amount),
                    $customData['product_name'] ?? 'Unknown Service',
                    $customData['old_status'] ?? '',
                    $customData['new_status'] ?? ''
                ],
                $template['description']
            );

            // Base tracking data
            $baseTrackingData = [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'service_type' => $transaction->service_type,
                'provider' => 'vtpass',
                'reference' => $transaction->transaction_reference,
                'status' => $transaction->status,
                'vtpass_transaction_id' => $transaction->vtpass_transaction_id,
                'ip' => request()->ip(),
                'processed_at' => now()->toISOString(),
            ];

            // Merge custom data
            $trackingData = array_merge($baseTrackingData, $customData);

            $this->tracker->track($eventType, $description, $trackingData);

        } catch (\Exception $e) {
            BillLogger::error('Tracking failed in VTpass webhook', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
                'event_type' => $eventType,
            ]);
        }
    }



}
