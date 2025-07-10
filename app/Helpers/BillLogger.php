<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class BillLogger
{

    public  static function log(string $message, array $context = []): void
    {
        Log::channel('bills')->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('bills')->error($message, $context);
    }

    public static  function debug(string $message, array $context = []): void
    {
        Log::channel('bills')->debug($message, $context);
    }
}
