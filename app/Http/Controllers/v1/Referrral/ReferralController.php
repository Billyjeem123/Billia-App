<?php

namespace App\Http\Controllers\v1\Referrral;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function getReferralLink(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $links = $user->getReferralLink(); // Now called directly on user

            return response()->json([
                'success' => true,
                'message' => 'Referral links generated successfully',
                'data' => $links
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to generate referral links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getReferralHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = $request->get('per_page', 20);

            $history = $this->referralService->getReferralHistory($user, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Referral history retrieved successfully',
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve referral history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getReferralStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $user->getReferralStats();

            return response()->json([
                'success' => true,
                'message' => 'Referral stats retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve referral stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
