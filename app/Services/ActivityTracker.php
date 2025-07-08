<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;

class ActivityTracker
{
    public function track(string $activity, string $description = null, array $properties = [])
    {
        UserActivityLog::create([
            'user_id' => Auth::id() ?? $properties['user_id'] ?? null,
            'activity' => $activity,
            'description' => $description ?: $this->getDefaultDescription($activity),
            'page_url' => request()->fullUrl(), // Use request() helper instead
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() // Use
        ]);
    }

    private function getDefaultDescription(string $activity): string
    {
        $descriptions = [
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'view_dashboard' => 'Viewed dashboard',
            'view_account' => 'Viewed account details',
            'view_transactions' => 'Viewed transaction history',
            'transfer_money' => 'Initiated money transfer',
            'pay_bill' => 'Paid a bill',
            'add_beneficiary' => 'Added new beneficiary',
            'update_profile' => 'Updated profile information',
            'change_password' => 'Changed password',
            'change_translation_pin' => 'Changed Transaction Pin',
            'register' => 'User registered successfully',
            'enable_2fa' => 'Enabled two-factor authentication',
            'disable_2fa' => 'Disabled two-factor authentication',
            'download_statement' => 'Downloaded account statement',
            'contact_support' => 'Contacted customer support',
        ];

        return $descriptions[$activity] ?? ucfirst(str_replace('_', ' ', $activity));
    }
}
