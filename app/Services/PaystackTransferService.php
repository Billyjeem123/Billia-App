<?php

namespace App\Services;

use App\Helpers\PaymentLogger;
use App\Helpers\Utility;
use App\Models\PaystackTransaction;
use App\Models\TransactionLog;
use App\Models\TransferRecipient;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaystackTransferService
{
    private $secretKey;
    private $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.sk');
    }

    /**
     * Transfer funds from user wallet to bank account
     */
    public function transferToBank(User $user, array $transferData)
    {

        try {
            #  Validate transfer data
            $this->validateTransferData($transferData);

            #  Check wallet balance
            $this->checkWalletBalance($user, $transferData['amount']);

            DB::beginTransaction();

            #  Create or get transfer recipient
            $recipient = $this->createOrGetRecipient($transferData);

            #  Generate unique reference
            $reference = $this->generateReference();

            #  Create pending transaction log
            $transaction = $this->createTransactionLog($user, $transferData, $reference, 'pending');

            #  Create pending Paystack transaction
            $paystackTransaction = $this->createPaystackTransaction($transaction, $reference, $transferData, 'pending');

            #  Debit wallet (hold funds)
            $this->debitWallet($user, $transferData['amount']);

            #  Initiate transfer with Paystack
            $transferResponse = $this->initiatePaystackTransfer($recipient, $transferData, $reference);

            if ($transferResponse['status']) {
                #  Update transaction records with Paystack response
                $this->updateTransactionSuccess($transaction, $paystackTransaction, $transferResponse);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Transfer initiated successfully',
                    'data' => [
                        'reference' => $reference,
                        'transfer_code' => $transferResponse['data']['transfer_code'],
                        'amount' => $transferData['amount'],
                        'recipient_name' => $transferData['account_name'],
                        'bank_name' => $transferData['bank_name']
                    ]
                ];
            } else {
                throw new Exception($transferResponse['message'] ?? 'Transfer failed');
            }

        } catch (Exception $e) {
            DB::rollBack();

            #  Revert wallet debit if transaction exists
            if (isset($transaction)) {
                $this->revertWalletDebit($user, $transferData['amount']);
                $this->updateTransactionFailed($transaction, $paystackTransaction ?? null, $e->getMessage());
            }

           PaymentLogger::error('Transfer failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'transfer_data' => $transferData
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Validate transfer data
     */
    private function validateTransferData(array $data)
    {
        $required = ['amount', 'account_number', 'bank_code', 'account_name', 'narration'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }

        if ($data['amount'] <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        if ($data['amount'] < 100) { #  Minimum transfer amount
            throw new Exception('Minimum transfer amount is ₦100');
        }
    }

    /**
     * Check if user has sufficient wallet balance
     */
    private function checkWalletBalance(User $user, float $amount)
    {
        $wallet = $user->wallet;

        if (!$wallet) {
            throw new Exception('Wallet not found');
        }

        if ($wallet->amount < $amount) {
            throw new Exception('Insufficient wallet balance');
        }
    }

    /**
     * Create or get transfer recipient
     */
    private function createOrGetRecipient(array $transferData)
    {
        #  Check if recipient already exists
        $existingRecipient = TransferRecipient::where([
            'account_number' => $transferData['account_number'],
            'bank_code' => $transferData['bank_code']
        ])->first();

        if ($existingRecipient && $existingRecipient->recipient_code) {
            return $existingRecipient;
        }

        #  Create new recipient with Paystack
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/transferrecipient', [
            'type' => 'nuban',
            'name' => $transferData['account_name'],
            'account_number' => $transferData['account_number'],
            'bank_code' => $transferData['bank_code'],
            'currency' => 'NGN'
        ]);

        $responseData = $response->json();

        if (!$response->successful() || !$responseData['status']) {
            throw new Exception($responseData['message'] ?? 'Failed to create transfer recipient');
        }

        #  Save recipient to database
        return TransferRecipient::updateOrCreate([
            'account_number' => $transferData['account_number'],
            'bank_code' => $transferData['bank_code'],

        ], [
            'account_name' => $transferData['account_name'],
            'bank_name' => $transferData['bank_name'] ?? '',
            'recipient_code' => $responseData['data']['recipient_code'],
            'is_active' => true,
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Generate unique transfer reference
     */
    private function generateReference()
    {
        return Utility::txRef("bank-transfer", "paystack", false);
    }

    /**
     * Create transaction log
     */
    private function createTransactionLog(User $user, array $transferData, string $reference, string $status)
    {
        return TransactionLog::create([
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'type' => 'debit',
            'category' => 'external_bank_transfer',
            'amount' => $transferData['amount'],
            'transaction_reference' => $reference,
            'service_type' => 'external_bank_transfer',
            'amount_before' => $user->wallet->amount,
            'amount_after' =>  $user->wallet->amount - $transferData['amount'],
            'status' => $status,
            'provider' => 'paystack',
            'channel' => 'paystack_transfer',
            'currency' => 'NGN',
            'description' => 'Sent to'. $transferData['account_name'] ,
            'payload' => [
                'initiated_at' => now(),
                'ip' => request()->ip(),
                'transfer_details' => [
                    'account_number' => $transferData['account_number'],
                    'account_name' => $transferData['account_name'],
                    'bank_code' => $transferData['bank_code'],
                    'bank_name' => $transferData['bank_name'] ?? ''
                ]
            ],
        ]);
    }

    /**
     * Create Paystack transaction record
     */
    private function createPaystackTransaction($transaction, string $reference, array $transferData, string $status)
    {
        return PaystackTransaction::create([
            'transaction_id' => $transaction->id,
            'reference' => $reference,
            'amount' => $transferData['amount'],
            'status' => $status,
            'gateway_response' => 'Transfer initiated',
            'metadata' => [
                'type' => 'transfer',
                'account_number' => $transferData['account_number'],
                'account_name' => $transferData['account_name'],
                'bank_code' => $transferData['bank_code']
            ],
        ]);
    }

    /**
     * Debit user wallet
     */
    private function debitWallet(User $user, float $amount)
    {
        $wallet = $user->wallet;
        $wallet->amount -= $amount;
        $wallet->save();
    }

    /**
     * Revert wallet debit
     */
    private function revertWalletDebit(User $user, float $amount)
    {
        $wallet = $user->wallet;
        $wallet->amount += $amount;
        $wallet->save();
    }

    /**
     * Initiate transfer with Paystack
     */

    private function initiatePaystackTransfer($recipient, array $transferData, string $reference)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/transfer', [
            'source' => 'balance',
            'amount' => $transferData['amount'] * 100, // convert to kobo
            'recipient' => $recipient->recipient_code,
            'reason' => $transferData['narration'] ?? 'Wallet transfer',
            'reference' => $reference
        ]);

        $responseData = $response->json();

        if (!$response->successful() || !($responseData['status'] ?? false)) {
            $message = $responseData['message'] ?? 'Unknown error';
            $errors = $responseData['data']['errors'] ?? null;

            // Combine message and specific field errors if any
            $detailedError = is_array($errors)
                ? $message . ' - ' . json_encode($errors)
                : $message;

            throw new \Exception('Paystack transfer failed: ' . $detailedError);
        }

        return $responseData;
    }


    /**
     * Update transaction records on success
     */



    private function updateTransactionSuccess($transaction, $paystackTransaction, $transferResponse)
    {
        $transaction->update([
            'status' => 'success',
            'payload' => array_merge($transaction->payload, [
                'completed_at' => now(),
                'paystack_response' => $transferResponse
            ])
        ]);

        $paystackTransaction->update([
            'status' => 'success',
            'gateway_response' => $transferResponse['message'],
            'metadata' => array_merge($paystackTransaction->metadata, [
                'transfer_code' => $transferResponse['data']['transfer_code'] ?? null,
                'paystack_data' => $transferResponse['data']
            ])
        ]);
    }

    /**
     * Update transaction records on failure
     */
    private function updateTransactionFailed($transaction, $paystackTransaction, string $errorMessage): void
    {
        $transaction->update([
            'status' => 'failed',
            'amount_before' => $transaction->wallet->amount,
            'amount_after' =>  $transaction->wallet->amount + $transaction->amount,
            'payload' => array_merge($transaction->payload, [
                'failed_at' => now(),
                'error_message' => $errorMessage
            ])
        ]);

        if ($paystackTransaction) {
            $paystackTransaction->update([
                'status' => 'failed',
                'gateway_response' => $errorMessage
            ]);
        }
    }

    /**
     * Verify transfer status (for webhook or manual verification)
     */
    public function verifyTransfer(string $transferCode)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey
        ])->get($this->baseUrl . '/transfer/' . $transferCode);

        return $response->json();
    }

    /**
     * Get list of supported banks
     */
    public function getBanks()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey
        ])->get($this->baseUrl . '/bank');

        return $response->json();
    }

    /**
     * Resolve account number
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): \Illuminate\Http\JsonResponse
    {
        try {
            // Step 1: Resolve the account number
            $resolveResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey
            ])->get($this->baseUrl . '/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode
            ]);

            $resolveData = $resolveResponse->json();

            if (!($resolveData['status'] ?? false)) {
                return Utility::outputData(false, $resolveData['message'] ?? 'Failed to resolve account.', null, 400);
            }

            // Step 2: Get all banks and match by bank_id
            $banksResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey
            ])->get($this->baseUrl . '/bank', [
                'country' => 'nigeria',
                'currency' => 'NGN'
            ]);

            $banks = $banksResponse->json()['data'] ?? [];

            $bankId = $resolveData['data']['bank_id'] ?? null;
            $bankName = collect($banks)->firstWhere('id', $bankId)['name'] ?? null;

            $resolvedData = [
                'account_number' => $resolveData['data']['account_number'],
                'account_name' => $resolveData['data']['account_name'],
                'bank_code' => $bankCode,
                'bank_name' => $bankName
            ];

            return Utility::outputData(true, 'Account resolved successfully', $resolvedData, 200);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'An error occurred while resolving account.', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
