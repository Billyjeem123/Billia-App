<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    public  static function log(string $message, array $context = []): void
    {
        Log::channel('payment')->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('payment')->error($message, $context);
    }

    public static  function debug(string $message, array $context = []): void
    {
        Log::channel('payment')->debug($message, $context);
    }
}
