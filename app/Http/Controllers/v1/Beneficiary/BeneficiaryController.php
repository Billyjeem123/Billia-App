<?php

namespace App\Http\Controllers\v1\Beneficiary;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Http\Resources\BeneficiaryResource;
use App\Services\BeneficaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BeneficiaryController extends Controller
{

    public BeneficaryService $beneficiaryService;

    public function __construct(BeneficaryService $beneficiaryService)
    {

        return $this->beneficiaryService = $beneficiaryService;

    }

    public function createBeneficiary(GlobalRequest $request): JsonResponse
    {
        try {
            $validatedRequest = $request->validated();

            $beneficiary = $this->beneficiaryService->createBeneficiary($validatedRequest);
            if ($beneficiary instanceof JsonResponse) {
                return $beneficiary;
            }

            return Utility::outputData(true, "Beneficiary created successfully", new BeneficiaryResource($beneficiary->load('user')), 201);
        } catch (\Exception $e) {
            Log::error("Error creating beneficiary: " . $e->getMessage());
            return Utility::outputData(false, "An error occurred while creating beneficiary", [], 500);
        }
    }

}
