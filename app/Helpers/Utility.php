<?php

namespace App\Helpers;

use App\Services\Enums\PaymentChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Auth;


class Utility
{
    public static function outputData($boolean, $message, $data, $statusCode): JsonResponse
    {
        return response()->json([
            'status' => $boolean,
            'message' => $message,
            'data' => $data,
            'status_code' => $statusCode
        ], $statusCode);
    }


    public static function token($length = 6): string
    {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }





    public static function getExceptionDetails(Throwable $e): array
    {
        // Log the exception details
        Log::error('Exception occurred', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'code' => $e->getCode(),
            'message' => $e->getMessage()
        ];
    }



    /**
     * Get the authenticated admin's ID.
     *
     * @return int|null
     */
    public static function getHospitalAdminId(): ?int
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin')) {
            return null;
        }

        return $user ? $user->admin_hospital_id : null;
    }

    public static function getAuthorizedHospitalId()
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin')) {
            return null;
        }
        return $user ? $user->admin_hospital_id : null;
    }





    public static function txRef(string $payment_channel = null, string $provider = null, bool $usePipe = true): string
    {
        $payment_channels = [
            'card' => 'CARD',
            'bank' => 'BANK',
            'bank-transfer' => 'BKTRF',
            'mobile-money' => 'MOBILE',
            'in-app' => 'INAPP',
            "referral" => "REF",
            "bills" => "BILL",
            "bet" => "BET",
        ];

        $leading = 'BILLIA';
        $time = substr(strval(time()), -4);
        $str = Str::upper(Str::random(4));
        $payment_type = array_key_exists($payment_channel, $payment_channels) ? $payment_channels[$payment_channel] : 'TRNX';

        return sprintf($usePipe ? '%s|%s|%s%s' : '%s-%s-%s%s', $leading, $payment_type, $time, $str);
    }


}
