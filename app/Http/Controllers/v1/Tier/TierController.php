<?php

namespace App\Http\Controllers\v1\Tier;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Resources\TierResource;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TierController extends Controller
{

    public function getAllTiers(): \Illuminate\Http\JsonResponse
    {
        try {
            $tiers = Tier::all();

            return Utility::outputData(
                true,
                "Tiers retrieved successfully",
                TierResource::collection($tiers),
                200
            );

        } catch (Throwable $e) {
            Log::error("Error fetching tiers: " . $e->getMessage());

            return Utility::outputData(
                false,
                "Unable to fetch tier data",
                [],
                500
            );
        }
    }

}
