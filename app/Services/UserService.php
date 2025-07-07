<?php

namespace App\Services;

use App\Events\AccountRegistered;
use App\Events\PushNotificationEvent;
use App\Helpers\Utility;
use App\Http\Resources\UserResource;
use App\Mail\SendOtpMail;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\ForgetPasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{


    public function processOnboarding001(array $validatedData){

        $user = User::create([
            'first_name' => $validatedData['first_name'],
            'last_name'  => $validatedData['last_name'],
            'email'      => $validatedData['email'],
            'password'   => Hash::make($validatedData['password']),
            'phone'      => $validatedData['phone_number'] ?? null,
            'role'       => 'user',
            'username'   => $validatedData['username'] ?? null,
            'pin'        => Hash::make($validatedData['transaction_pin']),
            'device_token' => $validatedData['device_token'] ?? null,
            'device_type' => $validatedData['device_type'] ?? null,
            'referral_code' => 0,
        ]);



        Log::info('Onboarding started for: ' . $validatedData['email']);


        event(new PushNotificationEvent($user, 'Deposit Successful', 'Your wallet has been credited.'));

    }


    public function processOnboarding(array $validatedData)
    {
        return DB::transaction(function () use ($validatedData) {
            #  Create user
            $user = $this->createUser($validatedData);

            #  Assign role
            $this->assignUserRole($user);

            #  Create wallet
            $this->createUserWallet($user);

            #  Process referral if exists
            $this->processReferralIfExists($validatedData, $user);

            #  Create payment provider customer
            $this->createPaymentProviderCustomer($user);

            #  Generate response
            return $this->generateOnboardingResponse($user);
        });
    }

    private function createUser(array $validatedData): User
    {
        $referralCode = $this->generateUserReferralCode($validatedData);
        $userData = $this->prepareUserData($validatedData, $referralCode);

        return User::create($userData);
    }

    private function generateUserReferralCode(array $validatedData): string
    {
        return User::generateUniqueReferralCode(
            $validatedData['first_name'],
            $validatedData['last_name']
        );
    }

    private function prepareUserData(array $validatedData, string $referralCode): array
    {
        return [
            'first_name' => $validatedData['first_name'],
            'last_name' => $validatedData['last_name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone_number'] ?? null,
            'role' => 'user',
            'username' => $validatedData['username'] ?? null,
            'pin' => Hash::make($validatedData['transaction_pin']),
            'device_token' => $validatedData['device_token'] ?? null,
            'device_type' => $validatedData['device_type'] ?? null,
            'referral_code' => $referralCode,
            'account_level' => 'tier_1'
        ];
    }

    private function assignUserRole(User $user): void
    {
        $user->assignRole('user');
    }

    private function createUserWallet(User $user): void
    {
        $user->wallet()->create([
            'user_id' => $user->id,
            'amount' => 0,
        ]);
    }

    private function processReferralIfExists(array $validatedData, User $user): void
    {
        if (empty($validatedData['referral_code'])) {
            return;
        }

        $referralService = $this->getReferralService();
        $deviceInfo = $this->extractDeviceInfo($validatedData);

        $referralService->processReferral(
            $validatedData['referral_code'],
            $user,
            $deviceInfo
        );
    }

    private function getReferralService(): \App\Services\ReferralService
    {
        return new \App\Services\ReferralService();
    }

    private function extractDeviceInfo(array $validatedData): array
    {
        return [
            'device_type' => $validatedData['device_type'] ?? null,
            'device_token' => $validatedData['device_token'] ?? null,
        ];
    }

    /**
     * @throws \Exception
     */
    private function createPaymentProviderCustomer(User $user): void
    {
        $customerData = $this->formatCustomerData($user);
        $paystackResult = $this->callPaystackService($customerData, $user->id);

        $this->validatePaystackResult($paystackResult);
    }

    private function callPaystackService(array $customerData, int $userId): mixed
    {
        $paystackService = new PaystackService();
        return $paystackService->createCustomer($customerData, $userId);
    }

    private function validatePaystackResult(mixed $paystackResult): void
    {
        if (is_array($paystackResult) && !$paystackResult['success']) {
            throw new \Exception('Failed to create Paystack customer: ' . $paystackResult['message']);
        }
    }

    private function formatCustomerData(User $user): array
    {
        return [
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'id' => $user->id,
        ];
    }

    private function generateOnboardingResponse(User $user): array
    {
        return [
            'user' => new UserResource($user),
            'token' => $this->generateAuthToken($user),
        ];
    }

    private function generateAuthToken(User $user): string
    {
        return $user->createToken('authToken')->plainTextToken;
    }




    public function authenticateUser(array $credentials): \Illuminate\Http\JsonResponse|array
    {
        $loginField = filter_var($credentials['email_or_username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $credentials['email_or_username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return Utility::outputData(false, 'Invalid credentials', [], 401);
        }

        # 🔒 Revoke all previous tokens,Maintaining one device per login policy
        $user->tokens()->delete();

        return [
            'user' => new UserResource($user),
            'token' => $this->generateAuthToken($user),
        ];
    }

    public function verifyCredential(array $data): \Illuminate\Http\JsonResponse|array
    {
        $fields = array_filter([
            'email' => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
        ]);

        if (count($fields) !== 1) {
            return Utility::outputData(false, 'Provide exactly one of: email, username, or phone_number.', [], 400);
        }

        $key = array_key_first($fields);
        $value = $fields[$key];
        $exists = User::where($key, $value)->exists();

        return [
            'field' => $key,
            'value' => $value,
            'it_exists' => $exists
        ];
    }

    public function verifyEmailOtp(array $data): \Illuminate\Http\JsonResponse|array
    {
        $email = $data['email'];
        $otp = $data['otp'];

        $storedOtp = Cache::get('verify_email_' . $email);

        if (!$storedOtp) {
            return Utility::outputData(false, 'OTP has expired or is invalid.', [], 400);
        }

        if ($storedOtp != $otp) {
            return Utility::outputData(false, 'Invalid OTP.', [], 400);
        }

        Cache::forget('verify_email_' . $email);

        $user = User::where('email', $email)->first();
        $user->email_verified_at = now();
        $user->save();

        return [
            'email' => $email
        ];
    }

    public function resendEmailOtp(array $data): bool|\Illuminate\Http\JsonResponse
    {
        $email = $data['email'];

        $user = User::where('email', $email)->first();
        if (!$user) {
            return Utility::outputData(false, 'Email not found. Please check and try again.', [], 404);
        }

        $otp = rand(100000, 999999);
        Cache::put('verify_email_' . $email, $otp, now()->addMinutes(10));

        try {
            Mail::to($email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            Log::error("Mail sending failed: " . $e->getMessage());
            return Utility::outputData(false, 'Failed to send OTP. Try again later.', [], 500);
        }

        return true;
    }


    public function processPasswordUpdate(array $validatedData): UserResource
    {
        $user = Auth::user();
        $user->password = \Hash::make($validatedData['new_password']);
        $user->save();

        return new UserResource($user);
    }

    public function forgetPassword(array $data): array
    {

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return ['success' => true,  'data' => [], 'message' => "If an account exists for {$data['email']} , you will receive password reset instructions", 'status' => 200];
        }
        $token =  Utility::token();
        $hashedPassword = Hash::make($token);
        $user->password = $hashedPassword;
        $user->save();

        $user->notify(new ForgetPasswordNotification($user, $token));

        return ['success' => true, 'message' => 'Password sent to mail',  'data' => [], 'status' => 200];

    }

    public function processTransactionPinUpdate(array $validatedData)
    {
        $user = Auth::user();

        #  Verify current transaction pin
        if (!\Hash::check($validatedData['current_pin'], $user->pin)) {
            return Utility::outputData(false, 'Current transaction PIN is incorrect', [], 400);
        }

        #  Save new transaction pin securely
        $user->pin = \Hash::make($validatedData['new_pin']);
        $user->save();

        return new UserResource($user);
    }


    public function processSavingToken(array $data): array
    {
        $user = Auth::user();
        $user->update(['device_token' => $data['device_token']]);

        return [
            'success' => true,
            'message' => 'Token save successfully.',
            'data' => [$data['device_token']],
            'status' => 200
        ];
    }



}
