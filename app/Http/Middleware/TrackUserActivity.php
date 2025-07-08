<?php

namespace App\Http\Middleware;

use App\Services\ActivityTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    private $tracker;

    public function __construct(ActivityTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track for authenticated users and successful requests
        if (Auth::check() && $response->isSuccessful()) {
            $this->trackPageActivity($request);
        }

        return $response;
    }

    private function trackPageActivity(Request $request): void
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;

        $activityMap = [
            'dashboard' => 'view_dashboard',
            'account.show' => 'view_account',
            'transactions.index' => 'view_transactions',
            'profile.show' => 'view_profile',
            'settings.index' => 'view_settings',
            'transfers.index' => 'view_transfers',
            'bills.index' => 'view_bills',
            'beneficiaries.index' => 'view_beneficiaries',
            'statements.index' => 'view_statements',
            'support.index' => 'view_support',
        ];

        if ($routeName && isset($activityMap[$routeName])) {
            $this->tracker->track($activityMap[$routeName]);
        }
    }
}
