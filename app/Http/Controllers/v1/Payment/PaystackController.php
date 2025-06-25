<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\PaystackTransaction;
use App\Services\PaymentLogger;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Database\Transaction;

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
                $reference = uniqid('trx-ps-');
                $callbackUrl = route('paystack.callback'); // or hardcoded for testing

                $response = $this->client->post("/transaction/initialize", [
                    'json' => [
                        'amount' => $amount * 100,
                        'email' => auth()->user->email,
                        'reference' => $reference,
                        'currency' => 'NGN',
                        'callback_url' => $callbackUrl,
                        'metadata' => [
                            'ip' => request()->ip(),
                            'user_id' => auth()->user->id,
                            'user_email' => $user->email
                        ],
                        'channels' => ['card']
                    ]
                ]);

                $responseData = json_decode($response->getBody(), true);

                if (!$responseData['status']) {
                    return Utility::outputData(false, $responseData['message'] ?? "Paystack API error", [], 400);
                }

                PaymentLogger::log('Paystack Response:', $responseData);

                $transaction = Transaction::create([
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'status' => 'pending',
                    'customer_id' => $user->id,
                    'payment_provider' => 'paystack',
                    'provider_reference' => $reference,
                    'metadata' => json_encode([
                        'initialized_at' => now(),
                        'ip' => request()->ip(),
                        'paystack_response' => $responseData
                    ])
                ]);

                $paystackTransaction = PaystackTransaction::create([
                    'transaction_id' => $responseData['data']['id'] ?? $reference,
                    'reference' => $reference,
                    'amount' => $amount,
                    'status' => 'pending',
                    'gateway_response' => $responseData['message'],
                    'metadata' => $responseData['data']
                ]);

                $transaction->payable()->associate($paystackTransaction);
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


    public function verifyTransaction(string $reference)
    {
        try {
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

}
