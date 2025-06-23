<?php

namespace App\Services;

use App\Events\AccountRegistered;
use App\Helpers\Utility;
use App\Http\Resources\UserResource;
use App\Mail\SendOtpMail;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public function processOnboarding(array $validatedData)
    {
        return DB::transaction(function () use ($validatedData) {
            $user = User::create([
                'first_name' => $validatedData['first_name'],
                'last_name'  => $validatedData['last_name'],
                'email'      => $validatedData['email'],
                'password'   => Hash::make($validatedData['password']),
                'phone'      => $validatedData['phone_number'] ?? null,
                'role'       => 'user',
                'username'   => $validatedData['username'] ?? null,
                'pin'        => Hash::make($validatedData['transaction_pin']),
            ]);

            $user->assignRole('user');

            $user->wallet()->create([
                'user_id' => $user->id,
                'amount' => 0,
            ]);
            event(new AccountRegistered($user));

            return ['user' => new UserResource($user) , 'token' => $user->createToken('authToken')->plainTextToken,];
        });
    }

    public function authenticateUser(array $credentials): \Illuminate\Http\JsonResponse|array
    {
        $loginField = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return Utility::outputData(false, 'Invalid credentials', [], 401);
        }

        return [
            'user' => new UserResource($user),
            'token' => $user->createToken('authToken')->plainTextToken,
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



}
