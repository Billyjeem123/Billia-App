<?php

use App\Http\Controllers\v1\Auth\UserController;
use App\Http\Controllers\v1\Kyc\KycController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::prefix('auth')->group(function () {
    Route::post('register', [UserController::class, 'Register']);
    Route::post('login', [UserController::class, 'Login']);
    Route::post('/resend-email-otp', [UserController::class, 'resendEmailOTP']);
    Route::post('/credential-exists', [UserController::class, 'checkCredential']);
    Route::post('/verify-email', [UserController::class, 'confirmEmailOtp']);
});


Route::prefix('kyc')->middleware('auth:sanctum')->group(function () {
    Route::post('/verify-bvn', [KycController::class, 'verifyBvn']);
    Route::post('/verify-nin', [KycController::class, 'verifyNin']);
});

