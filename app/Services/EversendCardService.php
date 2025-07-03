<?php

namespace App\Services;

use App\Models\VirtualCard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function PHPUnit\Framework\throwException;

class EversendCardService
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;
    protected $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.eversend.base_url', 'https://api.eversend.co/v1');
        $this->clientId = config('services.eversend.client_id');
        $this->clientSecret = config('services.eversend.client_secret');
        $this->accessToken = $this->generateAccessToken();# fetch token on init
    }

    /**
     * Edit a card user via Eversend API
     *
     * @param array $userData
     * @return array
     */


    /**
     * Create a card user via Eversend API
     *
     * @param array $userData
     * @return array
     */
    public function createCardUser(): array
    {
        DB::beginTransaction();
        $user = Auth::user();

        try {
            $userData = $this->prepareVirtualCardUserData($user);
            $response = $this->makeApiCall('/cards/user', $userData);

            if ($response['success']) {
               # Store virtual card details in database
                 $this->storeVirtualCard($userData, $response['data']);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Card user created successfully'
                ];
            }

            DB::rollBack();
            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating card user: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }


    private function prepareVirtualCardUserData($user): array
    {
        return [
            'firstName'   => $user->first_name,
            'lastName'    => $user->last_name,
            'email'       => $user->email,
            'phone'       =>  $user->phone,
            'country'     => 'NG',
            'state'       => 'Lagos',
            'city'        => 'Ikeja',
            'address'     =>  $user->kyc->address,
            'zipCode'     =>  $user->kyc->zipcode,
            'idType'      => 'Driving_License',# Options: National_ID, Passport, Driving_License
            'idNumber'    => $user->kyc->dl_licenseNo,
        ];
    }



    /**
     * Make API call to Eversend
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */

    protected function makeApiCall(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ]);

            $response = match (strtoupper($method)) {
                'POST' => $http->post($this->baseUrl . $endpoint, $data),
                'PATCH' => $http->patch($this->baseUrl . $endpoint, $data),
                'PUT' => $http->put($this->baseUrl . $endpoint, $data),
                'GET' => $http->get($this->baseUrl . $endpoint),
                default => throw new \Exception("Unsupported HTTP method: {$method}")
            };

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status_code' => $response->status()
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'API request failed',
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Eversend API call failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'API connection failed: ' . $e->getMessage(),
                'status_code' => 500
            ];
        }
    }


    protected function generateAccessToken(): ?string
    {
        try {
            $response = Http::withHeaders([
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ])->get($this->baseUrl . '/auth/token', []);

            if ($response->successful()) {
                return $response->json()['token'] ?? null;
            }

            Log::error('Failed to get Eversend token: ' . json_encode($response->json()));
            return null;
        } catch (\Exception $e) {
            Log::error('Exception while getting Eversend token: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Store virtual card details in database
     *
     * @param array $userData
     * @param array $apiResponse
     * @return VirtualCard
     */
    protected function storeVirtualCard(array $userData, array $apiResponse): VirtualCard
    {
        return VirtualCard::create([
            'first_name' => $userData['firstName'],
            'last_name' => $userData['lastName'],
            'email' => $userData['email'],
            'phone' => $userData['phone'],
            'country' => $userData['country'],
            'state' => $userData['state'],
            'city' => $userData['city'],
            'provider' => "eversend",
            'address' => $userData['address'],
            'zip_code' => $userData['zipCode'],
            'id_type' => $userData['idType'],
            'id_number' => $userData['idNumber'],
            'user_id' => Auth::id(),
            'eversend_user_id' => $apiResponse['data']['data']['userId'] ?? null,
            'card_status' => null,
            'api_response' => [],
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }




    protected function updateVirtualCard(array $userData, array $apiResponse): VirtualCard
    {
        return VirtualCard::createOrUpdate([
            'first_name' => $userData['firstName'],
            'last_name' => $userData['lastName'],
            'email' => $userData['email'],
            'phone' => $userData['phone'],
            'country' => $userData['country'],
            'state' => $userData['state'],
            'city' => $userData['city'],
            'provider' => "eversend",
            'address' => $userData['address'],
            'zip_code' => $userData['zipCode'],
            'id_type' => $userData['idType'],
            'id_number' => $userData['idNumber'],
            'user_id' => Auth::id(),
            'eversend_user_id' => $apiResponse['data']['data']['userId'] ?? null,
            'card_status' => null,
            'api_response' => [],
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    public function createVirtualCard(mixed $validated): array
    {
        DB::beginTransaction();

        try {
            $user = Auth::user()->loadMissing('virtual_cards');
            $userId = $user->id;

            $data = $this->prepareVirtualCardCreation($validated);
//            $response = $this->makeApiCall('/cards', $data);
            $response = json_decode(json_encode([
                "success" => true,
                "data" => [
                    "code" => 201,
                    "data" => [
                        "message" => "Card created successfully",
                        "card" => [
                            "securityCode" => "056",
                            "expiration" => "0728",
                            "currency" => "USD",
                            "status" => "active",
                            "isPhysical" => false,
                            "title" => "My first card",
                            "color" => "Blue",
                            "name" => "John Doe",
                            "balance" => 1.1,
                            "createdAt" => "2025-07-03T00:25:51.039Z",
                            "updatedAt" => "2025-07-03T00:25:59.746Z",
                            "id" => "b7620bce-04cc-444d-3458-08ddb7700091",
                            "brand" => "Visa",
                            "mask" => "485997******1469",
                            "number" => "4859970002321469",
                            "ownerId" => "CUI776",
                            "lastUsedOn" => "2025-07-03T00:25:59.746Z",
                            "isNonSubscription" => false,
                            "billingAddress" => [
                                "address" => "447 Broadway, 2nd Floor",
                                "city" => "New York",
                                "state" => "NY",
                                "zipCode" => "10013",
                                "country" => "US",
                            ]
                        ]
                    ],
                    "success" => true
                ]
            ]), true); // <<< THIS is important



            if ($response['success']) {
                $card = $response['data']['data']['card'] ?? null;

                if (!$card) {
                    throw new \Exception("Card data missing from API response.");
                }

                $this->storeVirtualCardFromApiResponse($userId, $card);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Card created successfully',
                    'data' => $card,
                ];
            }

            DB::rollBack();
            return [
                'success' => false,
                'message' => $response['message'] ?? 'Card creation failed',
                'status_code' => $response['status_code'] ?? 400,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Virtual card creation error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => 500,
            ];
        }
    }


    private function storeVirtualCardFromApiResponse(int $userId, array $card): void
    {
        $billing = $card['billingAddress'] ?? [];

        VirtualCard::updateOrCreate(
            ['user_id' => $userId],
            [
                'card_id' => $card['id'] ?? null,
                'security_code' => $card['securityCode'] ?? null,
                'expiration' => $card['expiration'] ?? null,
                'currency' => $card['currency'] ?? null,
                'status' => $card['status'] ?? null,
                'is_physical' => $card['isPhysical'] ?? false,
                'title' => $card['title'] ?? null,
                'color' => $card['color'] ?? null,
                'name' => $card['name'] ?? null,
                'balance' => $card['balance'] ?? 0.00,
                'brand' => $card['brand'] ?? null,
                'mask' => $card['mask'] ?? null,
                'number' => $card['number'] ?? null,
                'owner_id' => $card['ownerId'] ?? null,
                'last_used_on' => $card['lastUsedOn'] ?? null,
                'is_non_subscription' => $card['isNonSubscription'] ?? false,

                # Billing fields
                'billing_address' => $billing['address'] ?? null,
                'billing_city' => $billing['city'] ?? null,
                'billing_state' => $billing['state'] ?? null,
                'billing_zip_code' => $billing['zipCode'] ?? null,
                'billing_country' => $billing['country'] ?? null,
            ]
        );
    }



    private function prepareVirtualCardCreation(array $validated): array
    {
        $user = Auth::user()->loadMissing('virtual_cards');

        return [
            'title'             => $validated['title'],
            'color'             => $validated['color'],
            'amount'            => $validated['amount'],
            'userId'            => optional($user->virtual_cards)->eversend_user_id,
            'currency'          => $validated['currency'],
            'brand'             => $validated['brand'],
            'isNonSubscription' => false,
        ];
    }


}


