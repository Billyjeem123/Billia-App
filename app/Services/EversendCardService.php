<?php

namespace App\Services;

use App\Models\VirtualCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EversendCardService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.eversend.base_url', 'https://api.eversend.co/v1');
        $this->apiKey = config('services.eversend.api_key');
    }

    /**
     * Edit a card user via Eversend API
     *
     * @param array $userData
     * @return array
     */
    public function editCardUser(array $userData): array
    {
        DB::beginTransaction();

        try {
            // Validate that the ID is editable (doesn't start with AUI)
            if ($this->isIdNotEditable($userData['id'])) {
                return [
                    'success' => false,
                    'message' => 'Card user with ID starting with AUI cannot be edited',
                    'status_code' => 422
                ];
            }

            // Make API call to Eversend for update
            $response = $this->makeApiCall('/cards/user', $userData, 'PATCH');

            if ($response['success']) {
                // Update virtual card details in database
                $virtualCard = $this->updateVirtualCard($userData, $response['data']);

                DB::commit();

                return [
                    'success' => true,
                    'data' => [
                        'eversend_response' => $response['data'],
                        'virtual_card' => $virtualCard
                    ],
                    'message' => 'Card user updated successfully'
                ];
            }

            DB::rollBack();
            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating card user: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Create a card user via Eversend API
     *
     * @param array $userData
     * @return array
     */
    public function createCardUser(array $userData): array
    {
        DB::beginTransaction();

        try {
            // Make API call to Eversend
            $response = $this->makeApiCall('/cards/user', $userData);

            if ($response['success']) {
                // Store virtual card details in database
                $virtualCard = $this->storeVirtualCard($userData, $response['data']);

                DB::commit();

                return [
                    'success' => true,
                    'data' => [
                        'eversend_response' => $response['data'],
                        'virtual_card' => $virtualCard
                    ],
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

    /**
     * Make API call to Eversend
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     */
    protected function makeApiCall(string $endpoint, array $data, string $method = 'POST'): array
    {
        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ]);

            $response = match(strtoupper($method)) {
                'POST' => $http->post($this->baseUrl . $endpoint, $data),
                'PATCH' => $http->patch($this->baseUrl . $endpoint, $data),
                'PUT' => $http->put($this->baseUrl . $endpoint, $data),
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

    /**
     * Update virtual card details in database
     *
     * @param array $userData
     * @param array $apiResponse
     * @return VirtualCard|null
     */
    protected function updateVirtualCard(array $userData, array $apiResponse): ?VirtualCard
    {
        $virtualCard = VirtualCard::where('eversend_user_id', $userData['id'])->first();

        if (!$virtualCard) {
            // If not found by eversend_user_id, try to find by eversend_card_id
            $virtualCard = VirtualCard::where('eversend_card_id', $userData['id'])->first();
        }

        if ($virtualCard) {
            $virtualCard->update([
                'phone' => $userData['phone'],
                'country' => $userData['country'],
                'state' => $userData['state'],
                'city' => $userData['city'],
                'address' => $userData['address'],
                'zip_code' => $userData['zipCode'],
                'id_type' => $userData['idType'],
                'id_number' => $userData['idNumber'],
                'card_status' => $apiResponse['status'] ?? $virtualCard->card_status,
                'api_response' => json_encode($apiResponse),
                'updated_at' => now()
            ]);

            return $virtualCard->fresh();
        }

        return null;
    }

    /**
     * Check if ID is not editable (starts with AUI)
     *
     * @param string $id
     * @return bool
     */
    protected function isIdNotEditable(string $id): bool
    {
        return str_starts_with(strtoupper($id), 'AUI');
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
            'address' => $userData['address'],
            'zip_code' => $userData['zipCode'],
            'id_type' => $userData['idType'],
            'id_number' => $userData['idNumber'],
            'eversend_user_id' => $apiResponse['user_id'] ?? null,
            'eversend_card_id' => $apiResponse['card_id'] ?? null,
            'card_status' => $apiResponse['status'] ?? 'pending',
            'api_response' => json_encode($apiResponse),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Get virtual card details
     *
     * @param string|null $cardId
     * @param string|null $userId
     * @return VirtualCard|null
     */
    public function getVirtualCardDetails($cardId = null, $userId = null): ?VirtualCard
    {
        $query = VirtualCard::query();

        if ($cardId) {
            $query->where('eversend_card_id', $cardId);
        }

        if ($userId) {
            $query->where('eversend_user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Validate edit user data
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    protected function validateEditUserData(array $data): bool
    {
        $requiredFields = [
            'phone', 'country', 'state', 'city', 'address',
            'zipCode', 'idType', 'idNumber', 'id'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Validate ID type
        $validIdTypes = ['National_ID', 'Passport', 'Driving_License'];
        if (!in_array($data['idType'], $validIdTypes)) {
            throw new \Exception('Invalid ID type. Must be one of: ' . implode(', ', $validIdTypes));
        }

        // Validate phone format
        if (!str_starts_with($data['phone'], '+')) {
            throw new \Exception('Phone number must be in international format starting with +');
        }

        // Validate that ID is editable
        if ($this->isIdNotEditable($data['id'])) {
            throw new \Exception('Card user with ID starting with AUI cannot be edited');
        }

        return true;
    }

    /**
     * Validate user data
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    protected function validateUserData(array $data): bool
    {
        $requiredFields = [
            'firstName', 'lastName', 'email', 'phone', 'country',
            'state', 'city', 'address', 'zipCode', 'idType', 'idNumber'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Validate ID type
        $validIdTypes = ['National_ID', 'Passport', 'Driving_License'];
        if (!in_array($data['idType'], $validIdTypes)) {
            throw new \Exception('Invalid ID type. Must be one of: ' . implode(', ', $validIdTypes));
        }

        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        // Validate phone format
        if (!str_starts_with($data['phone'], '+')) {
            throw new \Exception('Phone number must be in international format starting with +');
        }

        return true;
    }
}

