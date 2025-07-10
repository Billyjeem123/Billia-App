<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FraudLogger
{
    /**
     * Log fraud detection events
     */
    public static function logFraudDetection(string $event, array $data = []): void
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'data' => $data
        ];

        #  Log to fraud channel
        Log::channel('fraud')->info($event, $logData);

    }

    /**
     * Log high-priority fraud alerts
     */
    public static function logFraudAlert(string $alert, array $data = []): void
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'alert' => $alert,
            'priority' => 'HIGH',
            'ip_address' => request()->ip(),
            'data' => $data
        ];

        #  Log to fraud channel with alert level
        Log::channel('fraud')->alert($alert, $logData);
    }

    /**
     * Log user account actions related to fraud
     */
    public static function logAccountAction(int $userId, string $action, array $context = []): void
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'user_id' => $userId,
            'action' => $action,
            'context' => $context,
            'ip_address' => request()->ip(),
            'performed_by' => auth()->id() ?? 'system'
        ];

        Log::channel('fraud')->warning("Account action: {$action}", $logData);
    }

}
