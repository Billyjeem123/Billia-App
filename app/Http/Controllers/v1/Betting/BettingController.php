<?php

namespace App\Http\Controllers\v1\Betting;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\BettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BettingController extends Controller
{

    public BettingService $bettingsService;

    public function __construct(BettingService $bettingsService)
    {

        return $this->bettingsService = $bettingsService;

    }

    public function getBetSites()
    {
        return $this->bettingsService->getBetSites();
    }

    public function verifyBettingID(GlobalRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        return $this->bettingsService->verifyBettingID($validated);
    }

    public function fundBettingWallet(GlobalRequest $request)
    {
        $validated = $request->validated();

        $response = $this->bettingsService->fundWallet($validated);
        return response()->json($response);
    }

}
