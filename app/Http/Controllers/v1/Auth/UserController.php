<?php

namespace App\Http\Controllers\v1\Auth;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\UserService;
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
            return Utility::outputData(false, "Unable to process request, please try again later", [], 500);
        }
    }





}
