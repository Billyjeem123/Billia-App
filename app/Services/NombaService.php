<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NombaService
{

    protected string  $baseUrl;
    protected string $clientId;
    protected string  $clientSecret;
    protected string  $accountId;

    protected string  $provider = "nomba";

    public function __construct()
    {
        $this->baseUrl = config('services.nomba.base_url');
        $this->clientId = config('services.nomba.client_id');
        $this->clientSecret = config('services.nomba.secret_key');
        $this->accountId = config('services.nomba.account_id');
    }

    public function getAccessToken()
    {
        return Cache::remember('nomba_access_token', 3500, function () {
            $headers = [
                'Content-Type' => 'application/json',
                'accountId' => $this->accountId,
            ];
            $response = Http::withHeaders($headers)->post($this->baseUrl . '/auth/token/issue', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ])->body();

            $response = json_decode($response, true);

            if ($response['code'] != 00) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to retrieve Nomba access token.',
                    'error' => $response['message'] ?? 'Unknown error',
                ], 419);

            }

            return $response['data']['access_token'];
        });
    }

    protected function withAuth()
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'accountId' => $this->accountId,
        ])
            ->withToken($this->getAccessToken())
            ->baseUrl($this->baseUrl);
    }




    public function createVirtualAccount(array $user, $userId): \Illuminate\Http\JsonResponse
    {
        try {
            #  Check if user already has a virtual account with this provider
            $existing = VirtualAccount::where('user_id', $userId)
                ->where('provider', $this->provider)
                ->exists();

            if ($existing) {
                return Utility::outputData(false, 'Virtual account already exists.', null, 409);
            }

            $response = $this->withAuth()->post('/accounts/virtual', [
                'accountRef' => 'Ref-' . Str::upper(Str::random(10)) . '-' . time(),
                'accountName' => $user['first_name'] . ' ' . $user['last_name'],
            ]);

            if (!$response->successful()) {
                Log::error('Virtual account creation failed', ['response' => $response->body()]);
                throw new \Exception('Failed to create virtual account: ' . ($response->json('message') ?? 'Unknown error'));

            }

            #  Save account details
            $this->saveVirtualAccount($response->json(), $user['id'], $this->provider);

            return Utility::outputData(true, 'Virtual account created successfully', $response->json('data'), 201);

        } catch (\Exception $e) {
            Log::error('Exception creating virtual account', ['error' => $e->getMessage()]);
            return Utility::outputData(false, 'An error occurred during virtual account creation', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function saveVirtualAccount(array $data, int $userId, string $provider): void
    {
        try {
            $virtualData = $data['data'] ?? null;

            if (!$virtualData) {
                Log::error('Missing virtual account data.');
                return;
            }

            VirtualAccount::create([
                'account_number'     => $virtualData['bankAccountNumber'] ?? null,
                'account_name'       => $virtualData['bankAccountName'] ?? null,
                'bank_name'          => $virtualData['bankName'] ?? 'Unknown',
                'provider'           => $provider,
                'user_id'            => $userId,
                'raw_response'  => $virtualData,
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
