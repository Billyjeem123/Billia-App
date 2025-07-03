<?php

namespace App\Http\Middleware;

use App\Helpers\Utility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;




class RestrictAccountTier
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredTier): Response
    {
        $user = Auth::user();

        $tiers = [
            'tier_1' => 1,
            'tier_2' => 2,
            'tier_3' => 3,
        ];

        $userTierLevel = $tiers[$user->account_level] ?? 0;
        $requiredTierLevel = $tiers[$requiredTier] ?? 0;

        if ($userTierLevel < $requiredTierLevel) {
            return Utility::outputData(
                false,
                'Access denied. Your tier level is unauthorized for this action.',
                null,
                403
            );
        }

        return $next($request);
    }

}
