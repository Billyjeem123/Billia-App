<?php

use App\Http\Controllers\v1\Auth\UserController;
use App\Http\Controllers\v1\Beneficiary\BeneficiaryController;
use App\Http\Controllers\v1\Betting\BettingController;
use App\Http\Controllers\v1\Bill\BillController;
use App\Http\Controllers\v1\Kyc\KycController;
use App\Http\Controllers\v1\Payment\PaystackController;
use App\Http\Controllers\v1\Payment\PaystackTransferController;
use App\Http\Controllers\v1\Referrral\ReferralController;
use App\Http\Controllers\v1\Tier\TierController;
use App\Http\Controllers\v1\Transaction\TransactionController;
use App\Http\Controllers\v1\VirtualCard\EversendCardController;
use App\Http\Controllers\v1\Webhook\BillWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    $user = Auth::user()->load(['wallet', 'virtual_accounts']);
    return new \App\Http\Resources\UserResource($user);
});

# Authentication
Route::prefix('auth')->group(function () {
    Route::post('register', [UserController::class, 'Register']);
    Route::post('login', [UserController::class, 'Login'])->name('login');
    Route::post('/resend-email-otp', [UserController::class, 'resendEmailOTP']);
    Route::post('/credential-exists', [UserController::class, 'checkCredential']);
    Route::post('/verify-email', [UserController::class, 'confirmEmailOtp']);
    Route::post('/change-password', [UserController::class, 'updatePassword'])->middleware('auth:sanctum');
    Route::post('forget-password', [UserController::class, 'forgetPassword']);
    Route::post('/change-pin', [UserController::class, 'updateTransactionPin'])->middleware('auth:sanctum');
});


# Bill Payment
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

    # Electricity
    Route::get('/get-electricity-lists-option', [BillController::class, 'get_electricity_lists_option']);
    Route::get('/get-electricity-lists', [BillController::class, 'get_electricity_lists']);
    Route::get('/verify-electricity', [BillController::class, 'verify_electricity']);
    Route::post('/buy-electricity', [BillController::class, 'buy_electricity']);

    Route::get('/get-jamb-lists-option', [BillController::class, 'get_jamb_lists_option']);
    Route::get('/get-jamb-lists', [BillController::class, 'get_jamb_list']);
    Route::post('/buy-jamb', [BillController::class, 'buy_jamb']);
    Route::get('/verify-jamb', [BillController::class, 'verify_jamb']);

    Route::get('/get-waec-lists-option', [BillController::class, 'get_waec_lists_option']);
    Route::get('/get-waec-lists', [BillController::class, 'get_waec_list']);
    Route::post('/buy-waec', [BillController::class, 'buy_waec_direct']);


    Route::get('/get-broadband-lists-option', [BillController::class, 'get_broadband_lists_option']);
    Route::get('/get-broadband-lists', [BillController::class, 'get_broadband_list']);
    Route::post('/buy-broadband-spectranent', [BillController::class, 'buy_broadband_spectranent']);
    Route::post('/buy-broadband-smile', [BillController::class, 'buy_broadband_smile']);
    Route::post('/verify-broadband-smile', [BillController::class, 'verify_broadband_smile']);


    Route::get('/get-international-countries', [BillController::class, 'get_international_countries']);
    Route::get('/get-international-airtime-product-types', [BillController::class, 'get_international_airtime_product_types']);
    Route::get('/get-international-airtime-operators', [BillController::class, 'get_international_airtime_operators']);
    Route::get('/get-international-airtime-variation', [BillController::class, 'get_international_airtime_variation']);
    Route::post('/buy-international-airtime', [BillController::class, 'buy_international_airtime']);


    Route::get('/get-giftcard-lists', [BillController::class, 'get_giftcard_list']);
    Route::post('/buy-giftcard', [BillController::class, 'buy_giftcard']);
});


# Transactions
Route::prefix('transaction')->middleware('auth:sanctum')->group(function () {
    Route::get('/get/user/history/{id?}', [TransactionController::class, 'myTransactionHistory']);
});


# Beneficiary
Route::prefix('beneficiary')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/create', [BeneficiaryController::class, 'createBeneficiary']);
    Route::post('/delete', [BeneficiaryController::class, 'deleteBeneficiary']);
    Route::get('/user/all/{id?}', [BeneficiaryController::class, 'getBeneficiary']);
});

Route::prefix('kyc')->middleware('auth:sanctum')->group(function () {
    Route::post('/verify-bvn', [KycController::class, 'verifyBvn']);
    Route::post('/verify-nin', [KycController::class, 'verifyNin']);
    Route::get('/tiers-list', [TierController::class, 'getAllTiers']);
});



Route::prefix('webhook')->group(function () {
    Route::post('/verify-bills', [BillWebhookController::class, 'verifyWebhookStatus']);
    Route::post('/paystack', [\App\Http\Controllers\v1\Payment\PaystackWebhookController::class, 'paystackWebhook']);
});


Route::prefix('payment')->group(function () {
    Route::post('/paystack-initiate-payment', [PaystackController::class, 'initializeTransaction'])->middleware('auth:sanctum');
    Route::get('/paystack-callback', [PayStackController::class, 'verifyTransaction'])->name('paystack.callback');
//    Route::post('/in-app-transfer', [\App\Http\Controllers\v1\Payment\InAppTransferController::class, 'inAppTransfer'])->middleware('auth:sanctum');
    Route::post('/in-app-transfer',  [\App\Http\Controllers\SecureInAppTransferController::class, 'InAppTransferNow'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->prefix('transfer')->group(function () {
        #  Transfer to bank account
        Route::post('/bank', [PaystackTransferController::class, 'transferToBank']);
        #  Get supported banks
        Route::get('/banks', [PaystackTransferController::class, 'getBanks']);
        #  Resolve account number
        Route::post('/resolve-account', [PaystackTransferController::class, 'resolveAccount']);
        #  Transfer history
        Route::get('/history', [PaystackTransferController::class, 'getTransferHistory']);
        #  Get transfer details
        Route::get('/details/{reference}', [PaystackTransferController::class, 'getTransferDetails']);

        Route::get('/status/{reference}', [PaystackTransferController::class, 'verifyTransferStatus']);
    });

});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/referral/link', [ReferralController::class, 'getReferralLink']);
    Route::get('/referral/history', [ReferralController::class, 'getReferralHistory']);
    Route::get('/referral/stats', [ReferralController::class, 'getReferralStats']);
});


Route::prefix('eversend')->group(function () {
    Route::post('/cards/user', [EversendCardController::class, 'createCardUser']);
    Route::get('/cards/virtual', [EversendCardController::class, 'getVirtualCard']);
});


Route::prefix('betting')->middleware('auth:sanctum')->group(function () {
    Route::get('/betsites', [BettingController::class, 'getBetSites']);
    Route::post('/verify-betting-id', [BettingController::class, 'verifyBettingID']);
    Route::post('/fund-wallet', [BettingController::class, 'fundBettingWallet'])->middleware(['throttle:5,1', 'api']);
});


