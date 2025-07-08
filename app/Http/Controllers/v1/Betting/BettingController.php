<?php

namespace App\Http\Controllers\v1\Betting;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\ActivityTracker;
use App\Services\BettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BettingController extends Controller
{

    public BettingService $bettingsService;
    public $tracker;

    public function __construct(BettingService $bettingsService,  ActivityTracker $activityTracker)
    {

         $this->bettingsService = $bettingsService;
        $this->tracker = $activityTracker;

    }

    public function getBetSites()
    {
        $this->tracker->track('all_betting_sites', "viewed  list of all betting sites", [
            "effective" => true,
        ]);
        return $this->bettingsService->getBetSites();
    }

    public function verifyBettingID(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        $this->tracker->track('betting_verification_details', "proceeded to verify betting credentials", [
            "effective" => true,
        ]);

        return $this->bettingsService->verifyBettingID($validated);
    }

    public function fundBettingWallet(GlobalRequest $request)
    {
        $validated = $request->validated();

        $response = $this->bettingsService->fundWallet($validated);

        $this->tracker->track('betting_wallet_funded', "betting wallet funded", [
            "effective" => true,
        ]);
        return response()->json($response);
    }

}
