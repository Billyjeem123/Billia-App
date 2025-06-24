<?php

use App\Http\Controllers\v1\Auth\UserController;
use App\Http\Controllers\v1\Beneficiary\BeneficiaryController;
use App\Http\Controllers\v1\Bill\BillController;
use App\Http\Controllers\v1\Kyc\KycController;
use App\Http\Controllers\v1\Transaction\TransactionController;
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

//Authentication
Route::prefix('auth')->group(function () {
    Route::post('register', [UserController::class, 'Register']);
    Route::post('login', [UserController::class, 'Login'])->name('login');
    Route::post('/resend-email-otp', [UserController::class, 'resendEmailOTP']);
    Route::post('/credential-exists', [UserController::class, 'checkCredential']);
    Route::post('/verify-email', [UserController::class, 'confirmEmailOtp']);
});


//Bill Payment


Route::prefix('bill')->middleware('auth:sanctum')->group(function () {
    Route::get('/get-airtime-list', [BillController::class, 'get_airtime_list']);
    Route::get('/get-data-list', [BillController::class, 'get_data_list']);
    Route::get('/get-data-sub-option', [BillController::class, 'get_data_sub_option']);
    Route::post('/buy-airtime', [BillController::class, 'buyAirtime']);
    Route::post('/buy-data', [BillController::class, 'buyData']);
    Route::get('/get-cable-lists-option', [BillController::class, 'get_cable_lists_option']);
    Route::get('/get-cable-lists', [BillController::class, 'get_cable_lists']);
    Route::get('/verify-cable', [BillController::class, 'verify_cable']);
    Route::post('/buy-cable', [BillController::class, 'buy_cable']);
});


//Transactions

Route::prefix('transaction')->middleware('auth:sanctum')->group(function () {
    Route::get('/get/user/history/{id?}', [TransactionController::class, 'myTransactionHistory']);
    Route::get('/get/detail', [TransactionController::class, 'user_transaction_detail']);
});


//Beneficiary
Route::prefix('beneficiary')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/create', [BeneficiaryController::class, 'createBeneficiary']);
    Route::post('/delete', [BeneficiaryController::class, 'delete_beneficiary']);
    Route::get('/user/all', [BeneficiaryController::class, 'user_get_all']);
});

Route::prefix('kyc')->middleware('auth:sanctum')->group(function () {
    Route::post('/verify-bvn', [KycController::class, 'verifyBvn']);
    Route::post('/verify-nin', [KycController::class, 'verifyNin']);
});

