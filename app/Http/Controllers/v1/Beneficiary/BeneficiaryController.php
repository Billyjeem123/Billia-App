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
use Throwable;

class BeneficiaryController extends Controller
{

    public BeneficaryService $beneficiaryervice;

    public function __construct(BeneficaryService $beneficiaryervice)
    {

        return $this->beneficiaryService = $beneficiaryervice;

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


    public function getBeneficiary(GlobalRequest $request, $id = null): JsonResponse
{
    try {
        $validatedData = $request->validated();
        $filters = $validatedData;

        if ($id) {
            $Beneficiary = $this->beneficiaryService->getUserBeneficiaryById($id);
            if (!$Beneficiary) {
                return Utility::outputData(false, "Beneficiary not found", null, 404);
            }
            return Utility::outputData(true, "Beneficiary retrieved successfully", new BeneficiaryResource($Beneficiary), 200);
        }

        $beneficiary = $this->beneficiaryService->getMyBeneficiaries($filters);
        return Utility::outputData(true, "Record retrieved successfully",
            BeneficiaryResource::collection($beneficiary)
            , 200);

    } catch (Throwable $e) {
        Log::error("Error fetching beneficiaries: " . $e->getMessage());
        return Utility::outputData(false, "Unable to process request, please try again later", [], 500);
    }
}



    public function deleteBeneficiary(GlobalRequest $request): JsonResponse
    {
        try {
            $validatedRequest = $request->validated();

            $deleted = $this->beneficiaryService->deleteUserBeneficiary($validatedRequest['id']);

            if (!$deleted) {
                return Utility::outputData(false, "Beneficiary not found", null, 404);
            }

            return Utility::outputData(true, "Beneficiary deleted successfully", null, 200);

        } catch (Throwable $e) {
            Log::error("Error deleting beneficiary: " . $e->getMessage());
            return Utility::outputData(false, "Unable to delete beneficiary. Please try again later", [], 500);
        }
    }


}
