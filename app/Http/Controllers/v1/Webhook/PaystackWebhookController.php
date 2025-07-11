<?php

namespace App\Http\Controllers\v1\Webhook;

use App\Events\PushNotificationEvent;
use App\Helpers\PaymentLogger;
use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\PaystackTransaction;
use App\Models\TransactionLog;
use App\Models\VirtualAccount;
use App\Notifications\PaystackTransferFailed;
use App\Notifications\PaystackTransferReversed;
use App\Notifications\PaystackTransferSucessfull;
use App\Notifications\VirtualAccountDepositNotification;
use App\Notifications\WalletFundedNotification;
use App\Services\ActivityTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaystackWebhookController extends Controller
{
    private const SUPPORTED_EVENTS = [
        'charge.success',
        'transfer.success',
        'transfer.failed',
        'transfer.reversed'
    ];

    public $tracker;

    private const STATUS_MAP = [
        'success' => 'successful',
        'failed' => 'failed',
        'abandoned' => 'failed',
        'pending' => 'pending',
        'reversed' => 'reversed',
    ];

    public function __construct(ActivityTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Handle Paystack webhook
     */
    public function paystackWebhook(Request $request): Response
    {
        try {

            PaymentLogger::log("All messages", ($request->all()));
            #  Step 1: Security - Verify webhook signature
//            if (!$this->verifyWebhookSignature($request)) {
//                PaymentLogger::log('Invalid webhook signature', [
//                    'ip' => $request->ip(),
//                    'headers' => $request->headers->all()
//                ]);
//                return response('Unauthorized', 401);
//            }

            #  Step 2: Validate payload structure
            $validatedData = $this->validateWebhookPayload($request);
            if (!$validatedData) {
                return response('Invalid payload structure', 400);
            }

            #  Step 3: Check if event is supported
            $event = $validatedData['event'];
            if (!in_array($event, self::SUPPORTED_EVENTS)) {
                PaymentLogger::log('Unsupported webhook event', ['event' => $event]);
                return response('Event not supported', 200); #  Return 200 to prevent retries
            }

            #  Step 4: Process webhook with database transaction
            $result = DB::transaction(function () use ($validatedData, $request) {
                return $this->processWebhook($validatedData, $request);
            });

            if ($result['success']) {
                return response('Webhook processed successfully', 200);
            } else {
                return response($result['message'], $result['status_code']);
            }

        } catch (\Exception $e) {
           PaymentLogger::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            return response('Internal server error', 200);
        }
    }

    /**
     * @param TransactionLog $transaction
     * @param mixed $wallet
     * @return void
     */
    public function recordTransaction(TransactionLog $transaction,  $wallet, $service_type, $oldBalance, $newBalance): void
    {
        $reference = Utility::txRef("reverse", "system");

        $serviceType = $service_type;

        $description = match ($service_type) {
            'transfer_failed'   => 'failed transfer',
            'transfer_reversed' => 'reversed transfer',
            default             => 'transfer',
        };

        TransactionLog::create([
            'user_id' => $transaction->user->id,
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $transaction->amount,
            'category' => 'refund',
            'transaction_reference' => $reference,
            'service_type' => $serviceType,
            'amount_before' => $oldBalance,
            'amount_after' => $newBalance,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => $description,
            'payload' => json_encode([
                'source' => 'referral_program',
                'provider' => 'system',
                'channel' => 'internal',
                'currency' => 'NGN',
            ])
        ]);
    }

    /**
     * Verify webhook signature for security
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');

        if (!$signature) {
            return false;
        }

        $body = $request->getContent();
        $secret = config('services.paystack.sk');

        if (!$secret) {
           PaymentLogger::error('Paystack webhook secret not configured');
            return false;
        }

        PaymentLogger::log('Webhook signature details', [
            'received_signature' => $signature,
            'computed_signature' => hash_hmac('sha512', $body, $secret),
            'body' => $body,
            'secret_exists' => $secret ? true : false,
        ]);


        $computedSignature = hash_hmac('sha512', $body, $secret);

        return hash_equals($signature, $computedSignature);
    }

    /**
     * Validate webhook payload structure
     */
    private function validateWebhookPayload(Request $request): ?array
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|string',
            'data' => 'required|array',
            'data.reference' => 'required|string',
            'data.amount' => 'required|numeric|min:0',
            'data.status' => 'required|string',
            'data.currency' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            PaymentLogger::log('Invalid webhook payload', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all()
            ]);
            return null;
        }

        return $request->all();
    }

    /**
     * Process webhook based on event type
     */
    private function processWebhook(array $data, Request $request): array
    {
        $reference = $data['data']['reference'];
        $event = $data['event'];

        #  Step 1: Check for idempotency - prevent duplicate processing
//        if ($this->isAlreadyProcessed($reference, $event)) {
//            PaymentLogger::log('Webhook already processed', [
//                'reference' => $reference,
//                'event' => $event
//            ]);
//            return ['success' => true, 'message' => 'Already processed'];
//        }

        #  Step 2: Try to find existing transaction
        $transaction = $this->findExistingTransaction($reference);

        #  Step 3: Handle virtual account funding if no transaction found
        if (!$transaction && $this->isVirtualAccountFunding($data)) {
            return $this->handleVirtualAccountFunding($data, $transaction);
        }

        #  Step 4: Process existing transaction
        if ($transaction) {
            return $this->processExistingTransaction($transaction, $data, $request);
        }

        PaymentLogger::log('No matching transaction found', [
            'reference' => $reference,
            'event' => $event
        ]);

        return [
            'success' => false,
            'message' => 'Transaction not found',
            'status_code' => 404
        ];
    }

    /**
     * Check if webhook has already been processed
     */
    private function isAlreadyProcessed(string $reference, string $event): bool
    {
        return PaystackTransaction::where('reference', $reference)
            ->where('status', 'successful')
            ->exists();
    }

    /**
     * Find existing transaction by reference
     */
    private function findExistingTransaction(string $reference): ?TransactionLog
    {
        return TransactionLog::where(function ($query) use ($reference) {
            $query->where('transaction_reference', $reference);
        })
            ->where('provider', 'paystack')
            ->first();
    }

    /**
     * Check if this is virtual account funding
     */
    private function isVirtualAccountFunding(array $data): bool
    {
        return isset($data['data']['metadata']['receiver_account_number'])
            && $data['event'] === 'charge.success';
    }

    /**
     * Handle virtual account funding
     */
    private function handleVirtualAccountFunding(array $data): array
    {
        $result = DB::transaction(function () use ($data) {


        $accountNumber = $data['data']['metadata']['receiver_account_number'];
        $amount = $data['data']['amount'] / 100; #  Convert from kobo to naira
        $reference = $data['data']['reference'];

        #  Find virtual account
        $virtualAccount = VirtualAccount::with(['user', 'wallet'])
            ->where('account_number', $accountNumber)
            ->first();

        if (!$virtualAccount) {
            PaymentLogger::log('Virtual account not found', [
                'account_number' => $accountNumber,
                'reference' => $reference
            ]);
            return [
                'success' => false,
                'message' => 'Virtual account not found',
                'status_code' => 404
            ];
        }

        if (!$virtualAccount->wallet) {
            PaymentLogger::log('Wallet not found for virtual account', [
                'virtual_account_id' => $virtualAccount->id,
                'user_id' => $virtualAccount->user_id
            ]);
            return [
                'success' => false,
                'message' => 'Wallet not found',
                'status_code' => 404
            ];
        }

        #  Create transaction record
        $transaction = TransactionLog::create([
            'user_id' => $virtualAccount->user_id,
            'wallet_id' => $virtualAccount->wallet->id,
            'amount' => $amount,
            'amount_before' => $virtualAccount->wallet->amount,
            'amount_after' => $virtualAccount->wallet->fresh()->amount + $amount,
            'currency' => $data['data']['currency'] ?? 'NGN',
            'description' => 'Received from'. $data['data']['authorization']['account_name'] ?? $data['data']['authorization']['sender_bank_account_number'] ,
            'status' => 'successful',
            'type' => 'credit',
            'category' => 'external_bank_deposit',
            'service_type' => 'external_bank_deposit',
            'purpose' => 'wallet_funding',
            'payable_type' => 'App\\Models\\Wallet',
            'payable_id' => $virtualAccount->wallet->id,
            'provider' => 'paystack',
            'transaction_reference' => $reference,
            'channel' => $data['data']['channel'] ?? 'bank_transfer',
            'paid_at' => now(),
            'provider_response' => json_encode([
                'virtual_account_number' => $accountNumber,
                'funding_source' => 'bank_transfer',
                'sender_account_name' => $data['data']['authorization']['account_name'] ?? null,
                'sender_bank' => $data['data']['authorization']['sender_bank'] ?? null
            ])
        ]);

        #  Create PaystackTransaction record
        $paystackTransaction = PaystackTransaction::create([
            'transaction_id' => $transaction->id,
            'reference' => $reference,
            'type' => 'payment',
            'amount' => $amount,
            'currency' => $data['data']['currency'] ?? 'NGN',
            'fees' => ($data['data']['fees'] ?? 0) / 100,
            'channel' => $data['data']['channel'] ?? 'bank_transfer',
            'status' => 'successful',
            'reason' => $data['data']['reason'] ?? null,
            'transfer_code' => $data['data']['transfer_code'] ?? null,
            'gateway_response' => $data['data']['gateway_response'] ?? 'Successful',
            'authorization_code' => $data['data']['authorization']['authorization_code'] ?? null,
            'card_details' => isset($data['data']['authorization']) ? json_encode($data['data']['authorization']) : null,
            'user_id' => $virtualAccount->user_id,
            'paid_at' => now(),
            'webhook_event' => $data['event'],
            'metadata' => json_encode($data['data']['metadata'] ?? [])
        ]);

        #  Update wallet balance
        $oldBalance = $virtualAccount->wallet->amount;
        $virtualAccount->wallet->increment('amount', $amount);
        $newBalance = $virtualAccount->wallet->fresh()->amount;

        PaymentLogger::log('Virtual account funded successfully', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id,
            'user_id' => $virtualAccount->user_id,
            'amount' => $amount,
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
            'virtual_account' => $accountNumber
        ]);

            return [
                'success' => true,
                'message' => 'Virtual account funded successfully',
                'transaction' => $transaction,
                'user' => $virtualAccount->user,
                 'data' => $data
            ];

        });

        if ($result['success'] && isset($result['user'])) {
            $this->trackSpecificWebhookEvent('virtual_account_funding_completed', $result['transaction'], $data);
            $result['user']->notify(new VirtualAccountDepositNotification($result['transaction'], $result['data']));
        }

        $sender = $data['data']['metadata']['account_name'] ?? 'Someone';
        $amount = number_format($data['data']['amount'] / 100, 2);

        $this->sendSafePushNotification(
            $result['user'],
            'Transaction Notification',
            "{$sender} just sent you ₦{$amount}."
        );

        return $result;


    }

    /**
     * Process existing transaction based on webhook event
     */
    private function processExistingTransaction(TransactionLog $transaction, array $data, Request $request): array
    {
        #  Prevent processing if already successful
//        if ($transaction->status === 'successful') {
//            PaymentLogger::log('Transaction already successful', [
//                'transaction_id' => $transaction->id,
//                'reference' => $data['data']['reference']
//            ]);
//            return ['success' => true, 'message' => 'Already processed'];
//        }

        #  Find or create PaystackTransaction
        $paystackTransaction = $this->findOrCreatePaystackTransaction($transaction, $data);

        #  Process based on event type
        switch ($data['event']) {
            case 'charge.success':
                return $this->handleChargeSuccess($transaction, $paystackTransaction, $data);

            case 'transfer.success':
                return $this->handleTransferSuccess($transaction, $paystackTransaction, $data);

            case 'transfer.failed':
                return $this->handleTransferFailed($transaction, $paystackTransaction, $data);

            case 'transfer.reversed':
                return $this->handleTransferReversed($transaction, $paystackTransaction, $data);

            default:
                PaymentLogger::log('Unhandled webhook event', ['event' => $data['event']]);
                return ['success' => false, 'message' => 'Unhandled event', 'status_code' => 400];
        }
    }

    /**
     * Find or create PaystackTransaction record
     */
    private function findOrCreatePaystackTransaction(TransactionLog$transaction, array $data): PaystackTransaction
    {
        $paystackTransaction = PaystackTransaction::where('reference', $data['data']['reference'])
            ->orWhere('transaction_id', $transaction->id)
            ->first();

        if (!$paystackTransaction) {
            $paystackTransaction = PaystackTransaction::create([
                'transaction_id' => $transaction->id,
                'reference' => $data['data']['reference'],
                'type' => $this->determineTransactionType($data['event']),
                'amount' => $data['data']['amount'] / 100,
                'currency' => $data['data']['currency'] ?? 'NGN',
                'fees' => ($data['data']['fees'] ?? 0) / 100,
                'channel' => $data['data']['channel'] ?? null,
                'reason' => $data['data']['reason'] ?? null,
                'transfer_code' => $data['data']['transfer_code'] ?? null,
                'status' => $this->mapPaystackStatus($data['data']['status']),
                'gateway_response' => $data['data']['gateway_response'] ?? null,
                'authorization_code' => $data['data']['authorization']['authorization_code'] ?? null,
                'card_details' => isset($data['data']['authorization']) ? json_encode($data['data']['authorization']) : null,
                'user_id' => $transaction->user_id,
                'webhook_event' => $data['event'],
                'metadata' => json_encode($data['data']['metadata'] ?? [])
            ]);
        } else {
            #  Update existing record
            $paystackTransaction->update([
                'status' => $this->mapPaystackStatus($data['data']['status']),
                'gateway_response' => $data['data']['gateway_response'] ?? $paystackTransaction->gateway_response,
                'fees' => ($data['data']['fees'] ?? ($paystackTransaction->fees * 100)) / 100,
                'webhook_event' => $data['event'],
                'reason' => $data['data']['reason'] ?? null,
                'transfer_code' => $data['data']['transfer_code'] ?? null,
                'metadata' => array_merge(
                    $paystackTransaction->metadata ?? [],
                    $data['data']['metadata'] ?? []
                ),
            ]);
        }

        return $paystackTransaction;
    }

    /**
     * Handle successful charge
     */
    private function handleChargeSuccess(TransactionLog $transaction, PaystackTransaction $paystackTransaction, array $data): array
    {
        $result =  DB::transaction(function () use ($transaction, $paystackTransaction, $data) {
        $amount = $data['data']['amount'] / 100;

        #  Update transaction status
        $transaction->update([
            'status' => 'successful',
            'amount_before' => $transaction->wallet->amount,
            'amount_after' => $transaction->wallet->fresh()->amount + $amount,
            'paid_at' => now()
        ]);

        #  Update paystack transaction
        $paystackTransaction->update([
            'status' => 'successful',
            'reason' => $data['data']['reason'] ?? null,
            'transfer_code' => $data['data']['transfer_code'] ?? null,
            'paid_at' => now()
        ]);

        # Credit wallet if it's a wallet transaction
        if ($transaction->wallet && $transaction->type === 'credit') {
            $wallet = $transaction->wallet;

            $oldBalance = $wallet->amount;
            $wallet->increment('amount', $amount);

            PaymentLogger::log('Wallet credited from charge success', [
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'old_balance' => $oldBalance,
                'new_balance' => $wallet->fresh()->amount
            ]);
        }


        PaymentLogger::log('Charge success processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id,
            'amount' => $amount
        ]);

        return ['success' => true, 'message' => 'Charge success processed'];
        });


        $this->trackSpecificWebhookEvent('wallet_funding_completed', $transaction, $data);
        $user = $transaction->user;
        if ($user) {
            $user->notify(new WalletFundedNotification($transaction, $data));
        }

        $this->sendSafePushNotification($user, 'Transaction Notification', "Your account has been credited with ₦{$transaction->amount} ");


        return $result;
    }

    /**
     * Handle successful transfer
     */

    private function handleTransferSuccess(TransactionLog $transaction, PaystackTransaction $paystackTransaction, array $data): array
    {
        $result = DB::transaction(function () use ($transaction, $paystackTransaction) {
            $transaction->update([
                'status' => 'successful',
                'paid_at' => now()
            ]);


            $paystackTransaction->update([
                'status' => 'successful',
                'paid_at' => now()
            ]);

            return ['success' => true, 'message' => 'Transfer success processed'];
        });

        $this->trackSpecificWebhookEvent('external_bank_transfer_completed', $transaction, $data);
        // Handle logging and notifications AFTER the transaction
        PaymentLogger::log('Transfer success processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);

        $user = $transaction->user;
        if ($user) {
            $user->notify(new PaystackTransferSucessfull($transaction, $data));
        }



        $recipient = $data['data']['recipient']['details']['account_name'] ?? 'Recipient';

        $this->sendSafePushNotification(
            $user,
            "We've Completed Your Transfer",
            "You sent ₦{$transaction->amount} to {$recipient}."
        );


        return $result;
    }

    /**
     * Handle failed transfer
     */
    private function handleTransferFailed(TransactionLog $transaction, PaystackTransaction $paystackTransaction, array $data): array
    {
        #  Refund wallet if it was debited
        $result =  DB::transaction(function () use ($transaction, $paystackTransaction, $data) {
        if ($transaction->type === 'debit' && $transaction->wallet) {
            $wallet = $transaction->wallet;

            $oldBalance = $wallet->amount;
            $wallet->increment('amount', $transaction->amount);
            $newBalance = $wallet->fresh()->amount;

            PaymentLogger::log('Wallet refunded due to transfer failure', [
                'wallet_id' => $wallet->id,
                'refund_amount' => $transaction->amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance
            ]);
        }

        $transaction->update([
            'status' => 'failed',
            'amount_after' => $newBalance,
            'failed_at' => now()
        ]);

        $paystackTransaction->update([
            'status' => 'failed',
            'failed_at' => now()
        ]);

             #Record credit Transaction.
        $this->recordTransaction($transaction, $wallet, "bank_transfer_failed", $oldBalance, $newBalance);

            PaymentLogger::log('Transfer failure processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);

            return ['success' => true, 'message' => 'Transfer failure processed'];
        });

        $this->trackSpecificWebhookEvent('external_bank_transfer_failed', $transaction, $data);

        $user = $transaction->user;
        if ($user) {
            $user->notify(new PaystackTransferFailed($transaction, $data));
        }


        $this->sendSafePushNotification(
            $user,
            'Transaction Notification',
            "Transaction refunded due to transfer failure."
        );


        return $result;
    }

    /**
     * Handle reversed transfer
     */
    private function handleTransferReversed(TransactionLog $transaction, PaystackTransaction $paystackTransaction, array $data): array
    {
        $result =  DB::transaction(function () use ($transaction, $paystackTransaction, $data) {
        if ($transaction->type === 'debit' && $transaction->wallet) {
            $wallet = $transaction->wallet;

            $oldBalance = $wallet->amount;
            $wallet->increment('amount', $transaction->amount);
            $newBalance = $wallet->fresh()->amount;

            PaymentLogger::log('Wallet refunded due to transfer reversal', [
                'wallet_id' => $wallet->id,
                'refund_amount' => $transaction->amount,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance
            ]);
        }

        $transaction->update([
            'status' => 'reversed',
            'amount_after' => $newBalance,
        ]);

        $paystackTransaction->update([
            'status' => 'reversed'
        ]);


        $this->recordTransaction($transaction, $wallet, "bank_transfer_reversed", $oldBalance, $newBalance);

        PaymentLogger::log('Transfer reversal processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);

        return ['success' => true, 'message' => 'Transfer reversal processed'];

        });

        $this->trackSpecificWebhookEvent('external_bank_transfer_reversed', $transaction, $data);

        $user = $transaction->user;
        if ($user) {
            $user->notify(new PaystackTransferReversed($transaction, $data));
        }

        $this->sendSafePushNotification(
            $user,
            'We Reversed Some Money Into Your Account',
            "We reversed ₦{$transaction->amount} to your account"
        );


        return $result;
    }

    /**
     * Determine transaction type from event
     */
    private function determineTransactionType(string $event): string
    {
        if (strpos($event, 'charge') !== false) {
            return 'payment';
        } elseif (strpos($event, 'transfer') !== false) {
            return 'transfer';
        }
        return 'payment';
    }

    /**
     * Map Paystack status to internal status
     */
    private function mapPaystackStatus(string $paystackStatus): string
    {
        return self::STATUS_MAP[$paystackStatus] ?? 'pending';
    }

    private function sendSafePushNotification($user, string $title, string $message): void
    {
        try {
            event(new PushNotificationEvent($user, $title, $message));
        } catch (\Throwable $e) {
            PaymentLogger::error("Push notification event failed", [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Generic method to track webhook events
     */
    private function trackWebhookEvent(
        string $eventType,
        string $description,
        TransactionLog $transaction,
        array $webhookData,
        array $additionalData = []
    ): void {
        try {
            $baseTrackingData = [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'provider' => 'paystack',
                'reference' => $webhookData['data']['reference'] ?? null,
                'status' => $transaction->status,
                'webhook_event' => $webhookData['event'],
                'ip' => request()->ip(),
                'processed_at' => now()->toISOString(),
            ];

            // Merge additional data
            $trackingData = array_merge($baseTrackingData, $additionalData);

            $this->tracker->track($eventType, $description, $trackingData);
        } catch (\Exception $e) {
            PaymentLogger::error('Tracking failed in webhook', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
                'event_type' => $eventType,
            ]);
        }
    }

    /**
     * Track specific webhook events with predefined templates
     */
    private function trackSpecificWebhookEvent(
        string $eventType,
        TransactionLog $transaction,
        array $webhookData,
        array $customData = []
    ): void {
        $eventTemplates = [
            'wallet_funding_completed' => [
                'description' => "wallet deposit of ₦{amount} completed successfully",
                'additional_data' => [
                    'payment_method' => $webhookData['data']['channel'] ?? 'unknown',
                    'funding_source' => 'direct_payment',
                ]
            ],
            'external_bank_transfer_completed' => [
                'description' => "bank transfer of ₦{amount} to {recipient} completed successfully",
                'additional_data' => [
                    'recipient_account' => $webhookData['data']['recipient']['details']['account_number'] ?? null,
                    'recipient_name' => $webhookData['data']['recipient']['details']['account_name'] ?? 'Unknown',
                    'recipient_bank' => $webhookData['data']['recipient']['details']['bank_name'] ?? 'Unknown Bank',
                    'transfer_code' => $webhookData['data']['transfer_code'] ?? null,
                ]
            ],
            'external_bank_transfer_failed' => [
                'description' => "bank transfer of ₦{amount} to {recipient} failed and was refunded",
                'additional_data' => [
                    'recipient_account' => $webhookData['data']['recipient']['details']['account_number'] ?? null,
                    'recipient_name' => $webhookData['data']['recipient']['details']['account_name'] ?? 'Unknown',
                    'recipient_bank' => $webhookData['data']['recipient']['details']['bank_name'] ?? 'Unknown Bank',
                    'failure_reason' => $webhookData['data']['reason'] ?? 'Unknown',
                    'refund_amount' => $transaction->amount,
                ]
            ],
            'external_bank_transfer_reversed' => [
                'description' => "bank transfer of ₦{amount} to {recipient} was reversed",
                'additional_data' => [
                    'recipient_account' => $webhookData['data']['recipient']['details']['account_number'] ?? null,
                    'recipient_name' => $webhookData['data']['recipient']['details']['account_name'] ?? 'Unknown',
                    'recipient_bank' => $webhookData['data']['recipient']['details']['bank_name'] ?? 'Unknown Bank',
                    'reversal_reason' => $webhookData['data']['reason'] ?? 'Unknown',
                    'refund_amount' => $transaction->amount,
                ]
            ],
            'virtual_account_funding_completed' => [
                'description' => "received ₦{amount} from {sender} via virtual account",
                'additional_data' => [
                    'sender_name' => $webhookData['data']['metadata']['account_name'] ??
                            $webhookData['data']['authorization']['account_name'] ?? 'Someone',
                    'sender_bank' => $webhookData['data']['authorization']['sender_bank'] ?? 'Unknown Bank',
                    'virtual_account' => $webhookData['data']['metadata']['receiver_account_number'] ?? null,
                    'funding_source' => 'virtual_account',
                ]
            ],
        ];

        if (!isset($eventTemplates[$eventType])) {
            PaymentLogger::error('Unknown event type for tracking', ['event_type' => $eventType]);
            return;
        }

        $template = $eventTemplates[$eventType];

        // Replace placeholders in description
        $description = str_replace(
            ['{amount}', '{recipient}', '{sender}'],
            [
                number_format($transaction->amount),
                $template['additional_data']['recipient_name'] ?? 'Unknown',
                $template['additional_data']['sender_name'] ?? 'Someone'
            ],
            $template['description']
        );

        // Merge custom data with template data
        $additionalData = array_merge($template['additional_data'], $customData);

        $this->trackWebhookEvent($eventType, $description, $transaction, $webhookData, $additionalData);
    }


}
