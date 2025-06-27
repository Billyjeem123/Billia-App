<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\PaystackTransaction;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\TransferRecipient;
use App\Models\VirtualAccount;
use App\Services\PaymentLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaystackController extends Controller
{

    protected $client;
    protected $base_url;

    public function __construct()
    {
        $baseUrl = config('services.paystack.base_url');
        $secretKey = config('services.paystack.sk');

        if (empty($baseUrl) || empty($secretKey)) {
            return Utility::outputData(false, "Paystack configuration is missing.", [], 400);
        }

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $secretKey,
                'Cache-Control' => 'no-cache',
            ],
        ]);
        $this->base_url = $baseUrl;
    }

    public function initializeTransaction(GlobalRequest $request)
    {
        try {
            $user = Auth::user();
            $validatedRequest = $request->validated();
            return DB::transaction(function () use ($validatedRequest, $request, $user) {
                $amount = $validatedRequest['amount'];
                $callbackUrl = route('paystack.callback'); # or hardcoded for testing
                $user = $user->load('virtual_accounts');

                $reference = Utility::txRef('initiate-payment', "paystack", false);

                $response = $this->client->post("/transaction/initialize", [
                    'json' => [
                        'amount' => $amount * 100,
                        'email' => $user->email,
                        'reference' => $reference,
                        'currency' => 'NGN',
                        'callback_url' => $callbackUrl,
                        'metadata' => [
                            'ip' => request()->ip(),
                            'user_id' => $user->id,
                            'user_email' => $user->email,
                            'receiver_account_number' => $user->virtual_accounts->first()->account_number ?? null,
                        ],
                        'channels' => ['card']
                    ]
                ]);

                $responseData = json_decode($response->getBody(), true);

                if (!$responseData['status']) {
                    return Utility::outputData(false, $responseData['message'] ?? "Paystack API error", [], 400);
                }

                PaymentLogger::log('Paystack Response:', $responseData);


               $transaction =  TransactionLog::create([
                    'user_id' => $user->id,
                    'wallet_id' => $user->wallet->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'transaction_reference' => $reference,
                    'service_type' => 'wallet top up',
                    'amount_after' => 0.00,
                    'status' => 'pending',
                    'provider' => 'paystack',
                    'channel' => 'Fund wallet',
                    'currency' => 'NGN',
                    'description' => 'wallet top up',
                    'payload' => json_encode([
                        'initialized_at' => now(),
                        'ip' => request()->ip(),
                        'paystack_response' => $responseData
                    ])
                ]);


                PaystackTransaction::create([
                    'transaction_id' => $transaction->id,
                    'reference' => $reference,
                    'amount' => $amount,
                    'status' => 'pending',
                    'gateway_response' => $responseData['message'],
                    'metadata' => $responseData['data']
                ]);

                $transaction->save();

                PaymentLogger::log('Initiating  transaction reference:', ['reference' => $reference]);

                return Utility::outputData(true, 'Transaction initialized successfully', [
                    'authorization_url' => $responseData['data']['authorization_url'],
                    'reference' => $reference,
                    'transaction_id' => $transaction->id
                ], 200);
            });
        } catch (\Exception $e) {
            PaymentLogger::error('Paystack initialization failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'amount' => $request->input('amount'),
                'trace' => $e->getTraceAsString()
            ]);

            return Utility::outputData(false, $e->getMessage(), null, 500);
        }
    }


    public function verifyTransaction(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $request->query('reference');
            $response = $this->client->get("/transaction/verify/" . urlencode($reference));
            $responseData = json_decode($response->getBody(), true);

            PaymentLogger::log('Verifying transaction reference:', ['reference' => $reference]);

            PaymentLogger::log('Paystack Verify Response:', $responseData);

            if (!$responseData['status']) {
                return Utility::outputData(false, $responseData['message'] ?? 'Paystack verification failed', [], 400);
            }

            return Utility::outputData(true, 'Transaction verified', [
                'verified' => true,
                'data' => $responseData['data']
            ],200);
        } catch (\Exception $e) {
            PaymentLogger::error('Paystack verification exception: ' . $e->getMessage(), [
                'reference' => $reference,
                'trace' => $e->getTraceAsString()
            ]);

            return Utility::outputData(false, $e->getMessage(), [], 500);
        }
    }


    public function paystackWebhook(Request $request)
    {
        $data = $request->all();
        PaymentLogger::log('Paystack webhook', $data);

        # Verify webhook signature (recommended for security)
//        $paystackSecretKey = config('paystack.secret_key');
//        $signature = $request->header('x-paystack-signature');
//        $computedSignature = hash_hmac('sha512', $request->getContent(), $paystackSecretKey);

//        if (!hash_equals($signature, $computedSignature)) {
//            PaymentLogger::error('Invalid Paystack webhook signature');
//            return response('Unauthorized', 401);
//        }

        $transaction = null;
        $paystackTransaction = null;

        # First try to find existing transaction
        $transaction = Transaction::query()
            ->where('external_reference', $data['data']['reference'])
            ->orWhere('reference', $data['data']['reference'])
            ->where('status', 'pending')
            ->where('provider', 'paystack')
            ->first();

        # Also check paystack_transactions table
        $paystackTransaction = PaystackTransaction::where('reference', $data['data']['reference'])
            ->orWhere('transaction_id', $transaction->id ?? null)
            ->first();

        # If no transaction found, check for virtual account
        if (!$transaction && isset($data['data']['metadata']['receiver_account_number'])) {
            $virtualAccount = VirtualAccount::where('account_number', $data['data']['metadata']['receiver_account_number'])->first();

            if ($virtualAccount) {
                $reference = Utility::txRef('transfer', 'paystack');
                $transaction = Transaction::create([
                    'user_id' => $virtualAccount->user->id,
                    'wallet_id' => $virtualAccount->user->wallet->id,
                    'amount' => $data['data']['amount'] / 100,
                    'currency' => $data['data']['currency'] ?? 'NGN',
                    'description' => 'Bank transfer top-up',
                    'status' => 'pending',
                    'purpose' => 'transfer',
                    'paystack_customer_id' => $virtualAccount->user->paystack_customer_id ?? null,
                    'provider' => 'paystack',
                    'reference' => $reference,
                    'external_reference' => $data['data']['reference'],
                    'channel' => $data['data']['channel'] ?? 'bank_transfer',
                    'type' => 'credit',
                ]);
            }
        }

        if (!$transaction && !$paystackTransaction) {
            PaymentLogger::log('Transaction not found for reference: ' . ($data['data']['reference'] ?? 'unknown'));
            return response('Transaction not found', 404);
        }

        # Create or update paystack_transaction record
        if (!$paystackTransaction) {
            $paystackTransaction = PaystackTransaction::create([
                'transaction_id' => $transaction->id,
                'reference' => $data['data']['reference'],
                'type' => $this->determineTransactionType($data['event']),
                'amount' => $data['data']['amount'] / 100,
                'currency' => $data['data']['currency'] ?? 'NGN',
                'fees' => ($data['data']['fees'] ?? 0) / 100,
                'channel' => $data['data']['channel'] ?? null,
                'status' => $this->mapPaystackStatus($data['data']['status']),
                'gateway_response' => $data['data']['gateway_response'] ?? null,
                'authorization_code' => $data['data']['authorization']['authorization_code'] ?? null,
                'card_details' => isset($data['data']['authorization']) ? json_encode($data['data']['authorization']) : null,
                'recipient_code' => $data['data']['recipient']['recipient_code'] ?? null,
                'bank_code' => $data['data']['recipient']['details']['bank_code'] ?? null,
                'account_number' => $data['data']['recipient']['details']['account_number'] ?? null,
                'account_name' => $data['data']['recipient']['details']['account_name'] ?? null,
                'transfer_reason' => $data['data']['reason'] ?? null,
                'user_id' => $transaction?->user_id,
                'metadata' => json_encode($data['data']['metadata'] ?? []),
            ]);
        } else {
            # Update existing paystack transaction
            $paystackTransaction->update([
                'status' => $this->mapPaystackStatus($data['data']['status']),
                'gateway_response' => $data['data']['gateway_response'] ?? $paystackTransaction->gateway_response,
                'fees' => ($data['data']['fees'] ?? ($paystackTransaction->fees * 100)) / 100,
                'metadata' => json_encode(array_merge(json_decode($paystackTransaction->metadata ?? '[]', true), $data['data']['metadata'] ?? [])),
            ]);
        }

        PaymentLogger::log('Transaction and Paystack Transaction', [
            'transaction' => $transaction?->toArray(),
            'paystack_transaction' => $paystackTransaction->toArray()
        ]);

        # Handle different webhook events
        switch ($data['event']) {
            case 'charge.success':
                $this->chargeEvent($transaction, $paystackTransaction, $request);
                break;

            case 'transfer.success':
                $this->successfulTransfer($transaction, $paystackTransaction, $request);
                break;

            case 'transfer.failed':
                $this->failedTransfer($transaction, $paystackTransaction, $request);
                break;

            case 'transfer.reversed':
                $this->reversedTransfer($transaction, $paystackTransaction, $request);
                break;

            default:
                PaymentLogger::log('Unhandled webhook event: ' . $data['event']);
        }

        return response('Webhook processed successfully', 200);
    }

    private function determineTransactionType($event)
    {
        if (strpos($event, 'charge') !== false) {
            return 'payment';
        } elseif (strpos($event, 'transfer') !== false) {
            return 'transfer';
        }
        return 'payment';
    }

    private function mapPaystackStatus($paystackStatus)
    {
        $statusMap = [
            'success' => 'success',
            'failed' => 'failed',
            'abandoned' => 'failed',
            'pending' => 'pending',
            'reversed' => 'reversed',
        ];

        return $statusMap[$paystackStatus] ?? 'pending';
    }

    public function chargeEvent(Transaction $transaction = null, PaystackTransaction $paystackTransaction, $request)
    {
        $data = $request->all();

        if (!$transaction) {
            PaymentLogger::log('Trying to update paystack payment but transaction not found', $data);
            $this->fundCustomerAccount($request);
            return;
        }

        $wallet = $transaction->wallet;

        $settledAmount = $data['data']['amount'] / 100;
        $amountToBeCredited = $settledAmount;


        DB::transaction(function()use($transaction, $wallet, $amountToBeCredited, $settledAmount, $data, $paystackTransaction){

            $wallet->amount += $amountToBeCredited;
            $wallet->save();

            # Update main transaction
            $transaction->update([
                'status' => 'successful',
                'paid_at' => now(),
            ]);
            # Update paystack transaction
            $paystackTransaction->update([
                'status' => 'successful',
                'paid_at' => now(),
            ]);

        });

      PaymentLogger::log('Transactions status updated successfully', $transaction->toArray());


    }

    public function successfulTransfer(Transaction $transaction = null, PaystackTransaction $paystackTransaction, $request)
    {
        if (!$transaction) {
            PaymentLogger::log('Transfer success but no main transaction found');
            return;
        }

        # Update main transaction
        $transaction->update([
            'status' => 'successful',
            'paid_at' => now(),
        ]);

        # Update paystack transaction
        $paystackTransaction->update([
            'status' => 'successful',
            'paid_at' => now(),
        ]);



        PaymentLogger::log('Transfer success processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);
    }

    public function failedTransfer(Transaction $transaction = null, PaystackTransaction $paystackTransaction, $request)
    {
        if (!$transaction) {
            PaymentLogger::log('Transfer failed but no main transaction found');
            return;
        }

        DB::transaction( function() use ($transaction, $paystackTransaction) {
            # Refund wallet balance if it was debited
            if ($transaction->type === 'debit' && $transaction->payable_type === 'App\\Models\\Wallet') {
                $wallet = $transaction->payable;
                if ($wallet) {
                    $wallet->increment('amount', $transaction->amount);
                }
            }

            # Update main transaction
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            # Update paystack transaction
            $paystackTransaction->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
        });

        # Send notification email
//        if ($transaction->user) {
//            Mail::to($transaction->user)->send(new UnSuccessfulTransaction($transaction->user));
//        }

        PaymentLogger::log('Failed transfer processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);
    }

    public function reversedTransfer(Transaction $transaction = null, PaystackTransaction $paystackTransaction, $request)
    {
        if (!$transaction) {
            PaymentLogger::log('Transfer reversed but no main transaction found');
            return;
        }

        DB::transaction(function() use ($transaction, $paystackTransaction) {
            # Refund wallet balance if it was debited
            if ($transaction->type === 'debit' && $transaction->payable_type === 'App\\Models\\Wallet') {
                $wallet = $transaction->payable;
                if ($wallet) {
                    $wallet->increment('amount', $transaction->amount);
                }
            }

            # Update main transaction
            $transaction->update([
                'status' => 'reversed',
            ]);

            # Update paystack transaction
            $paystackTransaction->update([
                'status' => 'reversed',
            ]);
        });

        PaymentLogger::log('Reversed transfer processed', [
            'transaction_id' => $transaction->id,
            'paystack_transaction_id' => $paystackTransaction->id
        ]);
    }


    public function fundCustomerAccount(Request $request)
    {
        $data = $request->all();

        if (isset($data['data']['metadata']['receiver_account_number'])) {
            $accountNumber = $data['data']['metadata']['receiver_account_number'];
            $virtualAccount = VirtualAccount::where('account_number', $accountNumber)->first();

            if (!$virtualAccount?->exists()) {
                PaymentLogger::log("Trying to fund an account that does not exist", ["accountNumber" => $accountNumber]);
                return;
            }

            if ($data['event'] == 'charge.success') {
                $amount = $data['data']['amount'] / 100;

               #  Create main transaction record using your database fields
                $transaction = Transaction::create([
                    'amount' => $amount,
                    'currency' => $data['data']['currency'] ?? 'NGN',
                    'description' => 'Virtual account top-up via bank transfer',
                    'status' => 'success',
                    'purpose' => 'transfer',
                    'paystack_customer_id' => $virtualAccount->paystack_customer_id ?? null,
                    'user_id' => $virtualAccount->user_id,
                    'metadata' => json_encode([
                        'virtual_account_number' => $accountNumber,
                        'funding_source' => 'bank_transfer',
                        'original_webhook_data' => $data['data']
                    ]),
                    'payable_type' => 'App\\Models\\Wallet',#  Assuming wallet model
                    'payable_id' => $virtualAccount->wallet_id,
                    'provider' => 'paystack',
                    'reference' => Utility::txRef("transfer", 'paystack'),
                    'external_reference' => $data['data']['reference'],
                    'channel' => $data['data']['channel'] ?? 'bank_transfer',
                    'paid_at' => now(),
                    'type' => 'credit',
                ]);

               #  Create corresponding paystack_transactions record
                $paystackTransaction = PaystackTransaction::create([
                    'transaction_id' => $data['data']['id'],
                    'reference' => $data['data']['reference'],
                    'type' => 'payment',
                    'amount' => $amount,
                    'currency' => $data['data']['currency'] ?? 'NGN',
                    'fees' => ($data['data']['fees'] ?? 0) / 100,
                    'channel' => $data['data']['channel'] ?? 'bank_transfer',
                    'status' => 'success',
                    'gateway_response' => $data['data']['gateway_response'] ?? 'Successful',
                    'authorization_code' => $data['data']['authorization']['authorization_code'] ?? null,
                    'card_details' => isset($data['data']['authorization']) ? json_encode($data['data']['authorization']) : null,
                    'user_id' => $virtualAccount->user_id,
                    'paid_at' => now(),
                    'metadata' => json_encode([
                        'virtual_account_number' => $accountNumber,
                        'customer_data' => $data['data']['customer'] ?? null,
                        'original_metadata' => $data['data']['metadata'] ?? []
                    ]),
                ]);

               #  Update wallet balance
                $wallet = $virtualAccount->wallet;
                if ($wallet) {
                    PaymentLogger::log('Wallet before update', [
                        'wallet_id' => $wallet->id,
                        'current_balance' => $wallet->amount,
                        'amount_to_add' => $amount
                    ]);

                    $wallet->amount += $amount;
                    $wallet->save();

                    PaymentLogger::log('Wallet after update', [
                        'wallet_id' => $wallet->id,
                        'new_balance' => $wallet->amount,
                        'amount_added' => $amount
                    ]);
                }

               #  Send notification email if user exists
//                if ($virtualAccount->user) {
//                    try {
//                        Mail::to($virtualAccount->user)->send(new SuccessfulTransaction($virtualAccount->user, $transaction));
//                    } catch (\Exception $e) {
//                        PaymentLogger::log('Failed to send notification email', [
//                            'user_id' => $virtualAccount->user_id,
//                            'transaction_id' => $transaction->id,
//                            'error' => $e->getMessage()
//                        ]);
//                    }
//                }

                PaymentLogger::log('Transaction created successfully - virtual account funding', [
                    'transaction_id' => $transaction->id,
                    'paystack_transaction_id' => $paystackTransaction->id,
                    'amount' => $amount,
                    'currency' => $data['data']['currency'] ?? 'NGN',
                    'virtual_account' => $accountNumber,
                    'user_id' => $virtualAccount->user_id
                ]);

                return [
                    'transaction' => $transaction,
                    'paystack_transaction' => $paystackTransaction,
                    'wallet_updated' => true
                ];
            }
        }

        PaymentLogger::log('Invalid webhook data for funding customer account', [
            'event' => $data['event'] ?? 'unknown',
            'has_metadata' => isset($data['data']['metadata']),
            'has_account_number' => isset($data['data']['metadata']['receiver_account_number'])
        ]);

        return null;
    }







    public function initiateTransferWithRecipient(string $accountNumber, string $bankCode, string $name, int $amountInKobo, ?string $reason = null
    ): array {

        $recipientResult = $this->findOrCreateRecipient($accountNumber, $bankCode, $name);

        if (!$recipientResult['success']) {
            throw new \Exception('Failed to prepare recipient');
        }

        $transferResult = $this->initiateTransfer(
            $recipientResult['recipient_code'],
            $amountInKobo,
            $reason
        );

        if ($transferResult instanceof \Illuminate\Http\JsonResponse) {
            $transferResult = $transferResult->getData(true);
        }

        return [
            'success' => true,
            'data' => $transferResult['data'] ?? null,
            'message' => $transferResult['message'] ?? 'Transfer initiated'
        ];
    }

    public function findOrCreateRecipient(string $accountNumber, string $bankCode, string $name): array
    {
        $existingRecipient = TransferRecipient::where([
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'user_id' => Auth::id()
        ])->first();

        if ($existingRecipient) {
            return [
                'success' => true,
                'recipient_code' => $existingRecipient->recipient_code,
                'existing' => true,
                'message' => 'Using existing recipient'
            ];
        }
        return $this->createPaystackRecipient($accountNumber, $bankCode, $name);
    }


    protected function createPaystackRecipient(string $accountNumber, string $bankCode, string $name): array
    {
        try {
            $response = $this->client->post($this->base_url . '/transferrecipient', [
                'json' => [
                    'type' => 'nuban',
                    'name' => $name,
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'currency' => 'NGN',
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (!$responseData['status']) {
                if (str_contains($responseData['message'] ?? '', 'already exists')) {
                    preg_match('/RCP_\w+/', $responseData['message'], $matches);
                    if ($matches[0] ?? false) {
                        return $this->handleExistingPaystackRecipient($accountNumber, $bankCode, $matches[0]);
                    }
                }
                throw new \Exception($responseData['message'] ?? 'Failed to create recipient');
            }

            $recipient = TransferRecipient::create([
                'user_id' => Auth::id(),
                'recipient_code' => $responseData['data']['recipient_code'],
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'account_name' => $responseData['data']['details']['account_name'],
                'bank_name' => $responseData['data']['details']['bank_name'],
                'metadata' => [
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'source' => 'new'
                ]
            ]);

            return [
                'success' => true,
                'recipient_code' => $recipient->recipient_code,
                'existing' => false,
                'message' => 'Recipient created successfully'
            ];

        } catch (RequestException $e) {
            if ($e->getCode() === 409) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
                preg_match('/RCP_\w+/', $errorResponse['message'] ?? '', $matches);
                if ($matches[0] ?? false) {
                    return $this->handleExistingPaystackRecipient($accountNumber, $bankCode, $matches[0]);
                }
            }
            throw new \Exception('Service unavailable');
        }
    }

    protected function handleExistingPaystackRecipient(string $accountNumber, string $bankCode, string $recipientCode): array
    {
        $recipient = TransferRecipient::firstOrCreate([
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'user_id' => Auth::guard('api')->user()->id
        ], [
            'recipient_code' => $recipientCode,
            'account_name' => 'Existing Paystack Recipient',
            'bank_name' => 'Unknown Bank',
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'source' => 'existing_paystack'
            ]
        ]);

        return [
            'success' => true,
            'recipient_code' => $recipientCode,
            'existing' => true,
            'message' => 'Recipient already exists on Paystack'
        ];
    }


    public function initiateTransfer(string $recipientCode, int $amountInKobo, ?string $reason = null)
    {
        DB::beginTransaction();

        try {
            $response = $this->client->post($this->base_url . '/transfer', [
                'json' => [
                    'source' => 'balance',
                    'amount' => $amountInKobo,
                    'recipient' => $recipientCode,
                    'reason' => $reason ?? 'Transfer initiated via API',
                ]
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (!$responseData['status']) {
                throw new \Exception($responseData['message'] ?? 'Transfer failed');
            }

            $transaction =  Transaction::create([
                'amount' => $amountInKobo,
                'currency' => 'NGN',
                'status' => 'pending',
                'purpose' => 'transfer',
//                'customer_id' => 286931319,
                'payment_provider' => 'paystack',
                'provider_reference' => $responseData['data']['reference'],
                'metadata' => json_encode($responseData['data']),
            ]);

            $paystackTransaction = PaystackTransaction::create([
                'transaction_id' => $responseData['data']['id'],
                'reference' => $responseData['data']['reference'],
                'type' => 'transfer',
                'amount' => $amountInKobo,
                'status' => 'pending',
                'gateway_response' => $responseData['data']['gateway_response'] ?? null,
                'recipient_code' => $recipientCode,
                'transfer_reason' => $reason,
                'metadata' => json_encode($responseData['data']),
            ]);


            $transaction->payable()->associate($paystackTransaction);
            $transaction->save();

            DB::commit();

            return $this->success($responseData['data'], 'Transfer initiated successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Transfer initiation failed', [
                'error' => $e->getMessage(),
                'recipient_code' => $recipientCode,
                'amount' => $amountInKobo,
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = $e instanceof RequestException
                ? ($e->hasResponse() ? json_decode($e->getResponse()->getBody(), true)['message'] ?? 'Transfer service unavailable': 'Transfer service unavailable')
                : $e->getMessage();
            throw new \Exception($errorMessage);
        }
    }

    public function success($data, string $message, int $statusCode = 200)
    {
        return response()->json([
            'status'=>true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }



}
