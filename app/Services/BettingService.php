<?php

namespace App\Services;

use App\Models\TransactionLog;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BettingService
{
    private array $betsites;

    public function __construct()
    {
        $this->initializeBetSites();
    }

    private function initializeBetSites(): void
    {
        $this->betsites = [
            ['id' => 1, 'name' => 'Betnaija', 'img' => asset('assets/images/bet/betnaija.jpeg')],
            ['id' => 2, 'name' => 'Sportybet', 'img' => asset('assets/images/bet/sportybet.png')],
            ['id' => 3, 'name' => 'Nairabet', 'img' => asset('assets/images/bet/nairabet.jpg')],
            ['id' => 4, 'name' => 'Betking', 'img' => asset('assets/images/bet/betking.jpeg')],
            ['id' => 5, 'name' => 'Betway', 'img' => asset('assets/images/bet/betway.png')],
            ['id' => 6, 'name' => 'Betlion', 'img' => asset('assets/images/bet/betlion.jpg')],
            ['id' => 7, 'name' => 'Cloudbet', 'img' => asset('assets/images/bet/cloudbet.jpg')],
            ['id' => 8, 'name' => 'Livescorebet', 'img' => asset('assets/images/bet/livescorebet.jpg')],
            ['id' => 9, 'name' => 'Merrybet', 'img' => asset('assets/images/bet/merrybet.jpeg')],
            ['id' => 10, 'name' => 'Supabet', 'img' => asset('assets/images/bet/supabet.jpg')],
            ['id' => 11, 'name' => 'Betland', 'img' => asset('assets/images/bet/betland.jpg')],
            ['id' => 12, 'name' => 'Bangbet', 'img' => asset('assets/images/bet/bangbet.jpg')],
            ['id' => 14, 'name' => 'NaijaBet', 'img' => asset('assets/images/bet/naijabet.jpeg')],
        ];
    }

    public function getBetSites()
    {
        try {
            return response()->json([
                'status' => true,
                'message' => 'Betting sites fetched successfully',
                'data' => array_values($this->betsites)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch betting sites', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch betting sites',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyBettingID(array $data)
    {
        try {
            $response = Http::withHeaders([
                'trnx_pin' => config('services.betting_site.pin'),
                'Authorization' => config('services.betting_site.secret_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get(config('services.betting_site.base_url'), [
                'betsite_id' => $data['betsite_id'],
                'betting_number' => $data['betting_number'],
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to verify betting ID, please try again later.'
                ], $response->status());
            }

            return response()->json([
                'status' => true,
                'message' => $response->json('message'),
                'data' => $response->json('data'),
            ]);

        } catch (\Exception $e) {
            Log::error('Betting ID verification failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to verify betting ID',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function fundWallet(array $data): array
    {
        try {
            $amount = abs($data['amount']);
            $walletBalance = Wallet::check_balance();

            if ($amount > $walletBalance) {
                return ['status' => false, 'message' => 'Insufficient balance'];
            }

            $internalRef = 'Bet' . time() . rand(1000, 9999);
            $user = Auth::user();

            # Add internal ref to payload for tracking
            $data['internal_ref'] = $internalRef;

            Wallet::remove_From_wallet($amount);

            $bettingSiteName = $this->getBettingSiteName($data['betsite_id']);
            $description = "Wallet funding to {$bettingSiteName} ({$data['betting_number']})";

            # Initial transaction log
            $transaction = TransactionLog::create([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'transaction_reference' => $internalRef, # Initially use internal reference
                'service_type' => 'betting',
                'amount_after' => $user->wallet->fresh()->amount,
                'status' => 'pending',
                'provider' => 'ncwallet',
                'channel' => 'Internal',
                'currency' => "NGN",
                'description' => $description,
                'payload' => json_encode($data),
            ]);

            # Provider request
            $response = Http::withHeaders([
                'trnx_pin' => config('services.betting_site.pin'),
                'Authorization' => config('services.betting_site.secret_key'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("https://ncwallet.africa/api/v1/betting", [
                'amount' => $amount,
                'betting_number' => $data['betting_number'],
                'betsite_id' => $data['betsite_id'],
                'bypass' => true,
                'ref_id' => $internalRef,
            ]);

            $responseData = $response->json();
            $providerStatus = strtolower($responseData['status'] ?? 'error');
            $providerRef = $responseData['ref_id'] ?? $internalRef;

            # Prepare structured response for logging
            $providerResponse = [
                'ref_id' => $providerRef,
                'datetime' => $responseData['datetime'] ?? null,
                'betsite_company' => $responseData['data']['betsite_company'] ?? null,
                'betting_number' => $responseData['data']['betting_number'] ?? null,
                'amount' => $responseData['data']['amount'] ?? null,
                'oldbal' => $responseData['data']['oldbal'] ?? null,
                'newbal' => $responseData['data']['newbal'] ?? null,
                'ip' => $responseData['data']['request_ip'] ?? null,
                'raw' => $responseData,
                'internal_ref' => $responseData['internal_ref'] ?? null,
            ];

            # Update transaction with final status and reference
            $transaction->update([
                'transaction_reference' => $providerRef,
                'status' => $providerStatus === 'success' ? 'successful' : 'failed',
                'amount_after' => $user->wallet->fresh()->amount,
                'provider_response' => json_encode($providerResponse),
                'payload' => json_encode($data), # Save updated payload with internal ref
            ]);

            # Refund if failed
            if ($providerStatus !== 'success') {
                Wallet::add_to_wallet($amount);
                return [
                    'status' => false,
                    'message' => $responseData['message'] ?? 'Unable to fund wallet, please try again later.',
                    'error' => $providerStatus,
                ];
            }

            return [
                'status' => true,
                'message' => $responseData['message'] ?? 'Wallet funded successfully',
                'data' => $responseData['data'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Betting wallet funding error', [
                'error' => $e->getMessage(),
                'payload' => $data
            ]);

            return [
                'status' => false,
                'message' => 'Failed to fund wallet',
                'error' => $e->getMessage(),
            ];
        }
    }





    private function getBettingSiteName(int $betsiteId): string
    {
        foreach ($this->betsites as $site) {
            if ($site['id'] === $betsiteId) {
                return $site['name'];
            }
        }
        return 'Unknown Site';
    }

    public static function updateStatus(string $reference, array $data): bool
    {
        echo $reference;
        return TransactionLog::where('transaction_reference', $reference)->update($data);
    }


}

