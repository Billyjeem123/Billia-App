<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\PaystackTransaction;
use App\Models\TransactionLog;
use App\Models\TransferRecipient;
use App\Services\ActivityTracker;
use App\Services\PaymentLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaystackController extends Controller
{

    public  $tracker;

    protected $client;
    protected $base_url;

    public function __construct(ActivityTracker $activityTracker)
    {
        $baseUrl = config('services.paystack.base_url');
        $secretKey = config('services.paystack.sk');
        $this->tracker = $activityTracker;

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
            $validated = $request->validated();

            return DB::transaction(function () use ($validated, $user) {
                $amount = $validated['amount'];
                $callbackUrl = route('paystack.callback');

                $reference = Utility::txRef('wallet-funding', 'paystack', false);

                [$limitOk, $limitMessage] = TransactionLog::checkLimits($user, $amount);
                if (!$limitOk) {
                    return Utility::outputData(false, $limitMessage, [], 403);
                }

                $response = $this->client->post("/transaction/initialize", [
                    'json' => [
                        'amount' => $amount * 100,
                        'email' => $user->email,
                        'reference' => $reference,
                        'currency' => "NGN",
                        'callback_url' => $callbackUrl,
                        'metadata' => [
                            'ip' => request()->ip(),
                            'user_id' => $user->id,
                            'user_email' => $user->email,
                        ],
                        'channels' => ['card'],
                    ]
                ]);

                $responseData = json_decode($response->getBody(), true);

                if (!($responseData['status'] ?? false)) {
                    return Utility::outputData(false, $responseData['message'] ?? 'Paystack API error', [], 400);
                }

                PaymentLogger::log('Paystack initialize response', $responseData);

                $transaction = TransactionLog::create([
                    'user_id' => $user->id,
                    'wallet_id' => $user->wallet->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'category' => 'deposit',
                    'transaction_reference' => $reference,
                    'service_type' => 'wallet_funding',
                    'amount_before' => $user->wallet->amount,
                    'amount_after' => 0.00,
                    'status' => 'pending',
                    'provider' => 'paystack',
                    'channel' => 'paystack_card',
                    'currency' => "NGN",
                    'description' => 'Wallet funding via Paystack',
                    'payload' => [ // Ensure this is casted as array in model
                        'initialized_at' => now(),
                        'ip' => request()->ip(),
                        'paystack_response' => $responseData
                    ],
                ]);

                PaystackTransaction::create([
                    'transaction_id' => $transaction->id,
                    'reference' => $reference,
                    'amount' => $amount,
                    'status' => 'pending',
                    'gateway_response' => $responseData['message'],
                    'metadata' => $responseData['data'], // Ensure this is casted to array in model
                ]);


                PaymentLogger::log('Transaction initialized', ['reference' => $reference]);

                $this->tracker->track(
                    'initialize_wallet_deposit',
                    "Initialized wallet deposit of ₦" . number_format($amount) . " via Paystack for {$user->first_name}",
                    [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'reference' => $reference,
                        'ip' => request()->ip(),
                        'provider' => 'paystack',
                        'status' => 'pending',
                        'effective' => true,
                    ]
                );



                return Utility::outputData(true, 'Transaction initialized successfully', [
                    'authorization_url' => $responseData['data']['authorization_url'],
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                ], 200);
            });
        } catch (\Exception $e) {
            PaymentLogger::error('Paystack initialization failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'amount' => $request->input('amount'),
                'trace' => $e->getTraceAsString()
            ]);

            return Utility::outputData(false, 'Payment initialization failed. Please try again.', [], 500);
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

            PaymentLogger::error('Transfer initiation failed', [
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
