<?php

namespace App\Http\Controllers\v1\Auth;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{

    private UserService $userService;

    public function __construct(UserService $userService){

        return $this->userService = $userService;

    }
    public function Register(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $user = $this->userService->processOnboarding($validatedData);

            return Utility::outputData(true, 'User registered successfully', $user, 201);
        } catch (\Throwable $e) {
            Log::error("Error during user registration: " . $e->getMessage());
            return Utility::outputData(false, "Unable to process request, please try again later", Utility::getExceptionDetails($e), 500);
        }
    }


    public function Login(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $user = $this->userService->authenticateUser($validatedData);
            if ($user instanceof JsonResponse) {
                return $user;
            }
            return Utility::outputData(true, 'Login successful', $user, 200);
        } catch (\Throwable $e) {
            Log::error("Error during login: " . $e->getMessage());
            return Utility::outputData(false, "Unable to login. Please check your credentials", [], 401);
        }
    }


    public function checkCredential(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $response = $this->userService->verifyCredential($validatedData);

            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return $response;
            }

            return Utility::outputData(true, 'Credential check complete', $response, 200);
        } catch (\Throwable $e) {
            Log::error('Credential check failed: ' . $e->getMessage());
            return Utility::outputData(false, 'Unable to check credential', [], 500);
        }
    }


    public function resendEmailOtp(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $response = $this->userService->resendEmailOtp($validated);

            if ($response instanceof JsonResponse) {
                return $response;
            }

            return Utility::outputData(true, 'OTP sent to your email address.', [], 200);

        } catch (\Throwable $e) {
            Log::error("Resend OTP error: " . $e->getMessage());
            return Utility::outputData(false, 'Something went wrong while sending OTP.', [], 500);
        }
    }



    public function confirmEmailOtp(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $response = $this->userService->verifyEmailOtp($validated);

            if ($response instanceof JsonResponse) {
                return $response;
            }

            return Utility::outputData(true, 'You have successfully verified your account.', $response, 200);
        } catch (\Throwable $e) {
            Log::error("Email OTP verification failed: " . $e->getMessage());
            return Utility::outputData(false, 'An error occurred. Please try again.', [], 500);
        }
    }





    public function updatePassword(GlobalRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $data =  $this->userService->processPasswordUpdate($validatedData);
            return  Utility::outputData(true, "Password updated successfully", $data, 200);
        } catch (Throwable $e) {
            return Utility::outputData(false, "Unable to process request, Please try again later", Utility::getExceptionDetails($e), 500);
        }
    }

    public function forgetPassword(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->userService->forgetPassword($validatedData);
            return Utility::outputData($result['success'], $result['message'], ($result['data']), 200);
        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, Please try again later.', [], 422);
        }
    }

    public function updateTransactionPin(GlobalRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            $data = $this->userService->processTransactionPinUpdate($validatedData);
            if($data instanceof JsonResponse){
                return $data;
            }

            return Utility::outputData(true, "Transaction PIN updated successfully", $data, 200);
        } catch (Throwable $e) {
            return Utility::outputData(false, "Unable to process request. Please try again later", Utility::getExceptionDetails($e), 500);
        }
    }

    public function saveToken(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->userService->processSavingToken($validatedData);

            return Utility::outputData($result['success'], $result['message'], $result['data'], $result['status']);
        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later.', [], 500);
        }
    }




}
