<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Models\PaystackCustomer;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.paystack.base_url'), '/');
        $this->secretKey = config('services.paystack.sk');
        $this->timeout = config('services.paystack.timeout', 30);
    }

    /**
     * Generic method to call any Paystack API endpoint
     */
    public function callApi(string $endpoint, array $payload = [], string $method = 'POST'): Response
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $client = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Cache-Control' => 'no-cache',
        ])->timeout($this->timeout);

        return match (strtoupper($method)) {
            'GET' => $client->get($url, $payload),
            'PUT' => $client->put($url, $payload),
            'DELETE' => $client->delete($url, $payload),
            default => $client->post($url, $payload)
        };
    }

    /**
     * Create customer with proper error handling and validation
     */
    public function createCustomer(array $data, ?int $userId = null)
    {
        try {
            #  Validate required fields
            $this->validateCustomerData($data);

            $response = $this->callApi('/customer', $data);

            if (!$response->successful()) {
                return $this->handleApiError($response, 'Failed to create customer');
            }

            $responseData = $response->json();

            if (!isset($responseData['data'])) {
                return $this->handleInvalidResponse($responseData);
            }

            $customer = $this->saveCustomer($responseData['data'], $userId);

            #  Create dedicated account after customer creation
            $dedicatedAccountResult = $this->createDedicatedAccount($customer->customer_code, $userId);
            $response = $dedicatedAccountResult->getData(true); // returns as associative array

            if (!$response['status']) {
                Log::warning('Customer created but dedicated account failed', [
                    'customer_code' => $customer->customer_code,
                    'error' => $response['message']
                ]);
            }

            return Utility::outputData(
                true,
                "Customer created successfully",
                [
                ],
                200
            );

        } catch (\Exception $e) {
            Log::error('Paystack createCustomer error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->handleException($e);
        }
    }



    /**
     * Initialize transaction
     */
    public function initializeTransaction(array $data)
    {
        try {
            $this->validateTransactionData($data);

            $response = $this->callApi('/transaction/initialize', $data);

            if (!$response->successful()) {
                return $this->handleApiError($response, 'Failed to initialize transaction');
            }

            $responseData = $response->json();

            return Utility::outputData(
                true,
                "Transaction initialized successfully",
                $responseData['data'],
                200
            );

        } catch (\Exception $e) {
            Log::error('Paystack initializeTransaction error: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Verify transaction
     */
    public function verifyTransaction(string $reference)
    {
        try {
            $response = $this->callApi("/transaction/verify/{$reference}", [], 'GET');

            if (!$response->successful()) {
                return $this->handleApiError($response, 'Transaction verification failed');
            }

            $responseData = $response->json();

            return Utility::outputData(
                true,
                "Transaction verified successfully",
                $responseData['data'],
                200
            );

        } catch (\Exception $e) {
            Log::error('Paystack verifyTransaction error: ' . $e->getMessage());
            return $this->handleException($e);
        }
    }

    /**
     * Validate customer data
     */
    private function validateCustomerData(array $data): void
    {
        $required = ['email', 'first_name', 'last_name'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format");
        }
    }

    /**
     * Validate transaction data
     */
    private function validateTransactionData(array $data): void
    {
        $required = ['email', 'amount'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new \InvalidArgumentException("Amount must be a positive number");
        }
    }

    /**
     * Save customer to database
     */
    private function saveCustomer(array $customerData, $userId): PaystackCustomer
    {
        return PaystackCustomer::create([
            'paystack_customer_id' => $customerData['id'],
            'customer_code' => $customerData['customer_code'],
            'first_name' => $customerData['first_name'],
            'last_name' => $customerData['last_name'],
            'user_id' => $userId,
            'email' => $customerData['email'],
            'risk_action' => $customerData['risk_action'] ?? null,
            'identified' => $customerData['identified'] ?? false,
            'identifications' => $customerData['identifications'] ?? null,
            'paystack_raw_data' => $customerData,
            'paystack_created_at' => $customerData['createdAt'] ?? null,
            'paystack_updated_at' => $customerData['updatedAt'] ?? null
        ]);
    }

    /**
     * Handle API errors consistently
     */
    private function handleApiError(Response $response, string $defaultMessage)
    {
        $errorData = $response->json();
        $message = $errorData['message'] ?? $defaultMessage;

        Log::error('Paystack API Error', [
            'status' => $response->status(),
            'response' => $errorData,
            'message' => $message
        ]);

        return Utility::outputData(false, $message, [], $response->status());
    }

    /**
     * Handle invalid API responses
     */
    private function handleInvalidResponse(array $responseData)
    {
        Log::error('Invalid Paystack response structure', $responseData);

        return Utility::outputData(false, 'Invalid response from payment provider', [], 422);
    }

    /**
     * Handle exceptions consistently
     */
    private function handleException(\Exception $e)
    {
        $message = $e instanceof \InvalidArgumentException
            ? $e->getMessage()
            : 'An unexpected error occurred';

        return Utility::outputData(false, $message, [], 500);
    }

    /**
     * Create dedicated account for a customer
     */

    public function createDedicatedAccount(string $customerCode, ?int $userId = null, array $options = [])
    {
        try {
            $payload = [
                'customer' => $customerCode,
                'preferred_bank' => $options['preferred_bank'] ?? 'test-bank',
                'country' => $options['country'] ?? 'NG',
            ];

            $response = $this->callApi('/dedicated_account', $payload);

            if (!$response->successful()) {
                $errorData = $response->json();

                Log::error('Paystack dedicated account creation failed', [
                    'customer_code' => $customerCode,
                    'response' => $errorData,
                ]);

                return Utility::outputData(
                    false,
                    $errorData['message'] ?? 'Failed to create dedicated account',
                    null,
                    422
                );
            }

            $responseData = $response->json();

            if ($responseData['status'] === true && isset($responseData['data'])) {
                if ($userId) {
                    $this->saveVirtualAccount($responseData, $userId, 'paystack');
                }

                return Utility::outputData(
                    true,
                    'Dedicated account created successfully',
                    [
                        'account_number' => $responseData['data']['account_number'],
                        'bank_name' => $responseData['data']['bank']['name'] ?? '',
                        'account_name' => $responseData['data']['account_name'] ?? '',
                    ],
                    200
                );
            }

            return Utility::outputData(
                false,
                'Invalid response from Paystack',
                null,
                500
            );

        } catch (\Exception $e) {
            Log::error('Paystack createDedicatedAccount exception: ' . $e->getMessage(), [
                'customer_code' => $customerCode,
                'user_id' => $userId,
            ]);

            return Utility::outputData(
                false,
                'Unable to create dedicated account: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Save virtual account to database
     */
    private function saveVirtualAccount(array $data, int $userId, string $provider): void
    {
        try {
            $virtualData = $data['data'] ?? null;

            if (!$virtualData) {
                Log::error('Missing virtual account data.');
                return;
            }

            VirtualAccount::create([
                'account_number' => $virtualData['account_number'] ?? null,
                'bank_name' => $virtualData['bank']['name'] ?? 'Unknown',
                'account_name' => $virtualData['account_name'] ?? null,
                'provider' => $provider,
                'user_id' => $userId,
                'wallet_id' => User::getWalletIdByUserId($userId),
                'paystack_raw_data' => $virtualData
            ]);

            Log::info('Virtual account saved successfully for user ID ' . $userId);

        } catch (\Exception $e) {
            Log::error('Failed to save virtual account: ' . $e->getMessage(), [
                'user_id' => $userId,
                'data' => $data
            ]);
        }
    }
}
