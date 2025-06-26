<?php

namespace App\Http\Controllers\v1\VirtualCard;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\EversendCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EversendCardController extends Controller
{
    protected $eversendService;

    public function __construct(EversendCardService $eversendService)
    {
        $this->eversendService = $eversendService;
    }

    /**
     * Create a new card user
     *
     * @param GlobalRequest $request
     * @return JsonResponse
     */
    public function createCardUser(GlobalRequest $request): JsonResponse
    {
        try {
            $result = $this->eversendService->createCardUser($request->validated());

            if ($result['success']) {
                return Utility::outputData(true , 'Card user created successfully', $result['data'],  201);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Failed to create card user: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get virtual card details
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVirtualCard(Request $request): JsonResponse
    {
        try {
            $cardId = $request->input('card_id');
            $userId = $request->input('user_id');

            $card = $this->eversendService->getVirtualCardDetails($cardId, $userId);

            if ($card) {
                return Utility::outputData(true, 'Virtual card retrieved successfully', $card, 201);
            }

            return Utility::outputData(true, 'Virtual card not found', [],  404);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Failed to retrieve card: ' . $e->getMessage(), [], 500);
        }
    }

    public function editCardUser(EditCardUserRequest $request): JsonResponse
    {
        try {
            $result = $this->eversendService->editCardUser($request->validated());

            if ($result['success']) {
                return Utility::outData(true,  'Card user updated successfully',  $result['data'], 200);
            }

            return Utility::outData(false , $result['message'], [], $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outData(false , 'Failed to update card user: ' . $e->getMessage(), [],  500);
        }
    }
}
