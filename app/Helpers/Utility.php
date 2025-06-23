<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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




    public static function paymentReference($length = 6)
    {
        $prefix = 'TRX-';
        # Generate a random numeric string with the given length, padded with leading zeros
        $randomNumber = str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);

        # Return the formatted payment reference
        return $prefix . $randomNumber;
    }


}
