<?php

namespace App\Http\Controllers\v1\Kyc;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Models\KYC;
use App\Notifications\TierThreeUpgradeNotifcation;
use App\Notifications\TierTwoUpgradeNotifcation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    /**
     * Verify BVN with selfie image
     */
    public function verifyBvn(GlobalRequest $request): JsonResponse
    {

        $validated = $request->validated();

        $user = Auth::user();
        $bvn = $validated['bvn'];
        $selfieImage =  $validated['selfie_image'];

        try {
            #  Call the BVN verification API
            $response = $this->callDojahApi('/api/v1/kyc/bvn/verify', [
                'bvn' => $bvn,
                'selfie_image' => $this->cleanBase64Image($selfieImage),
            ]);

            if (!$response->successful()) {
                return Utility::outputData(false, 'BVN verification failed',  $response->json(), 400);
            }

            $bvnData = $response->json();
            $entity = $bvnData['entity'] ?? null;
            if (!$entity) {
                return Utility::outputData(false, 'Invalid response from BVN service',  [], 400);
            }

            #  Validate selfie verification
            $selfieVerification = $entity['selfie_verification'] ?? null;
            if (!$this->isSelfieVerificationValid($selfieVerification)) {
                return $this->selfieVerificationFailedResponse($selfieVerification);
            }

            #  Save images
            $imagePaths = $this->saveKycImages($user->id, $selfieImage, $entity['image'] ?? null, 'bvn');

            #  Create KYC record
            $this->createOrUpdateKycRecord($user, [
                'bvn' => $bvn,
                'phone_number' => $entity['phone_number'] ?? null,
                'verification_image' => $imagePaths['verification_image'],
                'selfie' => $imagePaths['selfie_image'],
                'id_image' => $imagePaths['selfie_image'],
                'selfie_confidence' => $selfieVerification['confidence_value'],
                'selfie_match' => $selfieVerification['match'] ? 1 : 0,
                'nationality' => 'NIGERIAN',
                'dob' => $entity['date_of_birth'],
                'address' => $validated['address'],
                'zipcode' => $validated['zipcode'],
            ]);

            #  Update user
            $this->updateUserAfterVerification($user, $entity, 'bvn');


            $user->notify(new TierTwoUpgradeNotifcation($user));

            return Utility::outputData(
                true,
                'BVN verification successful',
                [
                    'bvn_details' => $this->formatEntityResponse($entity),
                    'selfie_verification' => $selfieVerification,
                    'kyc_status' => 'approved',
                    'images' => [
                        'bvn_photo_url' => $imagePaths['verification_image'] ? asset('storage/' . $imagePaths['verification_image']) : null,
                        'selfie_image_url' => $imagePaths['selfie_image'] ? asset('storage/' . $imagePaths['selfie_image']) : null,
                    ]
                ],
                200
            );





        } catch (\Exception $e) {
            Log::error('BVN Verification Error: ' . $e->getMessage());
            return Utility::outputData(false, 'An error occurred during BVN verification', ['error' => $e->getMessage()],
                500
            );

        }
    }

    /**
     * Save KYC images (selfie and verification image)
     */
    private function saveKycImages(int $userId, string $selfieImage, ?string $verificationImage, string $type): array
    {
        #  Ensure directory exists
        $this->ensureKycDirectoryExists();

        $timestamp = now()->format('YmdHis');
        $imagePaths = [
            'selfie_image' => null,
            'verification_image' => null
        ];

        #  Save selfie image
        if ($selfieImage) {
            $selfieFilename = "selfie_{$userId}_{$timestamp}.jpg";
            $imagePaths['selfie_image'] = $this->saveBase64Image(
                $selfieImage,
                $selfieFilename,
                'selfie'
            );
        }

        #  Save verification image (BVN/NIN photo)
        if ($verificationImage) {
            $verificationFilename = "{$type}_photo_{$userId}_{$timestamp}.jpg";
            $imagePaths['verification_image'] = $this->saveBase64Image(
                $verificationImage,
                $verificationFilename,
                $type . ' photo'
            );
        }

        return $imagePaths;
    }

    /**
     * Save a single base64 image to storage
     */
    private function saveBase64Image(string $base64Image, string $filename, string $type): ?string
    {
        try {
            $cleanedImage = $this->cleanBase64Image($base64Image);
            $imageData = base64_decode($cleanedImage);

            if ($imageData === false) {
                Log::warning("Failed to decode base64 {$type} image");
                return null;
            }

            $kycImagesPath = storage_path('app/public/kyc_images');
            $fullPath = $kycImagesPath . '/' . $filename;

            if (file_put_contents($fullPath, $imageData) === false) {
                Log::error("Failed to save {$type} image to {$fullPath}");
                return null;
            }

            return 'kyc_images/' . $filename;

        } catch (\Exception $e) {
            Log::error("Error saving {$type} image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ensure KYC images directory exists
     */
    private function ensureKycDirectoryExists(): void
    {
        $kycImagesPath = storage_path('app/public/kyc_images');
        if (!file_exists($kycImagesPath)) {
            mkdir($kycImagesPath, 0755, true);
        }
    }

    /**
     * Clean base64 image string
     */
    private function cleanBase64Image(string $base64Image): string
    {
        return preg_replace('/^data:image\/[a-zA-Z]+;base64,/', '', $base64Image);
    }

    /**
     * Call Dojah API
     */
    private function callDojahApi(string $endpoint, array $payload = [], string $method = 'POST')
    {
        $http = Http::withHeaders([
            'AppId' => config('services.dojah.app_id'),
            'Authorization' => config('services.dojah.secret_key'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30);

        $url = config('services.dojah.base_url') . $endpoint;

        return match (strtoupper($method)) {
            'GET' => $http->get($url, $payload),
            'POST' => $http->post($url, $payload),
            'PUT' => $http->put($url, $payload),
            'PATCH' => $http->patch($url, $payload),
            'DELETE' => $http->delete($url, $payload),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }


    /**
     * Check if selfie verification is valid
     */
    private function isSelfieVerificationValid(?array $selfieVerification): bool
    {
        return $selfieVerification && ($selfieVerification['match'] ?? false);
    }

    /**
     * Return selfie verification failed response
     */
    private function selfieVerificationFailedResponse(?array $selfieVerification): JsonResponse
    {
        $confidenceValue = $selfieVerification['confidence_value'] ?? 0;

        return Utility::outputData(
            false,
            'Selfie verification failed - face does not match BVN photo',
            [
                'confidence_value' => $confidenceValue,
                'match' => false
            ],
            400
        );
    }


    /**
     * Create or update KYC record
     */
    private function createOrUpdateKycRecord($user, array $additionalData): void
    {
        $kycData = array_merge([
            'user_id' => $user->id,
            'tier' => 'tier_2',
            'status' => 'approved',
        ], $additionalData);

        KYC::updateOrCreate(
            ['user_id' => $user->id],
            $kycData
        );
    }

    /**
     * Update user after successful verification
     */
    private function updateUserAfterVerification($user, array $entity, string $verificationField): void
    {
        $user->update([
            'first_name' => $entity['first_name'] ?? $user->first_name,
            'last_name' => $entity['last_name'] ?? $user->last_name,
            $verificationField => true,
            'account_level' => 'tier_2',
            'kyc_status' => 'verified',
            'kyc_type' => $verificationField,
        ]);
    }

    /**
     * Format entity response data
     */
    private function formatEntityResponse(array $entity): array
    {
        return [
            'first_name' => $entity['first_name'] ?? null,
            'last_name' => $entity['last_name'] ?? null,
            'middle_name' => $entity['middle_name'] ?? null,
            'gender' => $entity['gender'] ?? null,
            'phone_number' => $entity['phone_number'] ?? null,
            'date_of_birth' => $entity['date_of_birth'] ?? null,
            'bvn' => $entity['bvn'] ?? null,
        ];
    }

    /**
     * Verify NIN with selfie image (using the same reusable methods)
     */
    public function verifyNin(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = Auth::user();
        $nin = $validated['nin'] ?? null;
        $selfieImage = $validated['selfie_image'] ?? null;
        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $address = $validated['address'] ?? null;

        try {
            #  Prepare payload
            $payload = [
                'nin' => $nin,
                'selfie_image' => $this->cleanBase64Image($selfieImage),
            ];

            #  Add optional parameters
            if ($firstName) $payload['first_name'] = $firstName;
            if ($lastName) $payload['last_name'] = $lastName;

            #  Call the NIN verification API
            $response = $this->callDojahApi('/api/v1/kyc/nin/verify', $payload);

            if (!$response->successful()) {
                return Utility::outputData(false, 'NIN verification failed', $response->json(), 400);

            }

            $ninData = $response->json();
            $entity = $ninData['entity'] ?? null;

            if (!$entity) {
                return  Utility::outputData(false, 'Invalid response from NIN service', null, 400 );
            }

            #  Validate selfie verification
            $selfieVerification = $entity['selfie_verification'] ?? null;
            if (!$this->isSelfieVerificationValid($selfieVerification)) {
                return $this->selfieVerificationFailedResponse($selfieVerification);
            }

            #  Save images
            $imagePaths = $this->saveKycImages($user->id, $selfieImage, $entity['image'] ?? null, 'nin');

            #  Create KYC record
            $this->createOrUpdateKycRecord($user, [
                'nin' => $nin,
                'phone_number' => $entity['phone_number'] ?? null,
                'image' => $imagePaths['verification_image'], #  NIN uses 'image' field
                'selfie_image' => $imagePaths['selfie_image'],
                'selfie_confidence' => $selfieVerification['confidence_value'],
                'selfie_match' => $selfieVerification['match'] ? 1 : 0,
                'nationality' => 'NIGERIAN',
                'dob' => $entity['date_of_birth'] ?? null,
                'address' => $address,
                'zipcode' => $validated['zipcode'] ?? null,
            ]);

            #  Update user
            $this->updateUserAfterVerification($user, $entity, 'nin');


            $user->notify(new TierTwoUpgradeNotifcation($user));

            return Utility::outputData(
                true,
                'NIN verification with selfie successful',
                [
                    'nin_details' => array_merge(
                        $this->formatEntityResponse($entity),
                        ['nin' => $entity['nin'] ?? null]
                    ),
                    'selfie_verification' => $selfieVerification,
                    'kyc_status' => 'approved',
                    'images' => [
                        'nin_photo_url' => $imagePaths['verification_image'] ? asset('storage/' . $imagePaths['verification_image']) : null,
                        'selfie_image_url' => $imagePaths['selfie_image'] ? asset('storage/' . $imagePaths['selfie_image']) : null,
                    ]
                ],
                200
            );


        } catch (\Exception $e) {
            Log::error('NIN Verification Error: ' . $e->getMessage());
            return Utility::outputData(false, 'An error occurred during NIN verification', [Utility::getExceptionDetails($e)], 500);

        }
    }


    public function verifyDriverLicense(GlobalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $license_number = $validated['license_number'] ?? null;

        try {
            # Prepare query parameters
            $payload = [
                'license_number' => $license_number,
            ];

            # Call the DL verification API (GET with query string)
            $response = $this->callDojahApi('/api/v1/kyc/dl', $payload, "GET");

            if (!$response->successful()) {
                return Utility::outputData(false, 'Driver License verification failed', $response->json(), 400);
            }

            $dlData = $response->json();
            $entity = $dlData['entity'] ?? null;

            if (!$entity) {
                return Utility::outputData(false, 'Invalid response from provider, try again later', null, 400);
            }

            # Create or update KYC record
            $user = Auth::user();
            $this->updateDriverLicenseRecords($user, [
                'dl_uuid' => $entity['uuid'] ?? null,
                'dl_licenseNo' => $entity['licenseNo'] ?? null,
                'dl_issuedDate' => $entity['issuedDate'] ?? null,
                'dl_expiryDate' => $entity['expiryDate'] ?? null,
                'dl_stateOfIssue' => $entity['stateOfIssue'] ?? null,
            ]);

            $user->notify(new TierThreeUpgradeNotifcation($user));

            return Utility::outputData(true, 'Driver license verification successful', [], 200);

        } catch (\Exception $e) {
            Log::error('DL Verification Error: ' . $e->getMessage());
            return Utility::outputData(false, 'An error occurred during verification', [Utility::getExceptionDetails($e)], 500);
        }
    }




    private function updateDriverLicenseRecords($user, array $additionalData): void
    {
        $kycData = array_merge([
            'user_id' => $user->id,
            'tier' => 'tier_3',
            'status' => 'approved',
        ], $additionalData);

        $user->update([
            'account_level' => 'tier_3',
        ]);

        KYC::updateOrCreate(
            ['user_id' => $user->id],
            $kycData
        );
    }



}
