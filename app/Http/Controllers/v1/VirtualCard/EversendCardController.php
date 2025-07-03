<?php

namespace App\Http\Controllers\v1\VirtualCard;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\EversendCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $result = $this->eversendService->createCardUser();

            if ($result['success']) {
                return Utility::outputData(true , 'Card user created successfully', $result['data'],  201);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [], 500);
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
            return Utility::outputData(false, 'Unable to process request, please try again later', [], 500);
        }
    }

    public function createVirtualCard(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->eversendService->createVirtualCard($validated);

            if ($result['success']) {
                return Utility::outputData(true,  'Card created successfully',  $result['data'], 200);
            }

            return Utility::outputData(false , $result['message'], [], $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false , 'Unable to process request, please try again later',  [],  500);
        }
    }


    public function getCardId($cardId): JsonResponse
    {
        try {
           $response =  $this->eversendService->getVirtualCardInfo($cardId);
            if ($response['success']) {
                return Utility::outputData(true, $response['message'], $response['data'], 200);
            }

            return Utility::outputData(false, $response['message'], [], 400);
        } catch (\Exception $e) {
            return Utility::outputData(false , 'Unable to process request, please try again later', [],  500);
        }
    }


    public function getCardTransactions($cardId): JsonResponse
    {
        try {
            $response = $this->eversendService->getCardTransactions($cardId);

            if ($response['success']) {
                return Utility::outputData(true, $response['message'], $response['data'], 200);
            }

            return Utility::outputData(false, $response['message'], [], 400);
        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [], 500);
        }
    }


    public function FundWallet(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->eversendService->processCardFunding($validated);

            if ($result['success']) {
                return Utility::outputData(true , 'Transaction successful', $result['data'],  201);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [], 500);
        }
    }


    public function Withdrawal(GlobalRequest $request): JsonResponse
{
    try {
        $validated = $request->validated();
        $result = $this->eversendService->processCardFunding($validated);

        if ($result['success']) {
            return Utility::outputData(true , 'Transaction successful', $result['data'],  201);
        }

        return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

    } catch (\Exception $e) {
        return Utility::outputData(false, 'Unable to process request, please try again later', [], 500);
    }
}


    public function FreezeACard(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->eversendService->processCardFreezing($validated);

            if ($result['success']) {
                return Utility::outputData(true , $result['message'], [],  200);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [Utility::getExceptionDetails($e)], 500);
        }
    }


    public function UnFreezeACard(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->eversendService->processCardUnFreezing($validated);

            if ($result['success']) {
                return Utility::outputData(true , $result['message'], [],  200);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [Utility::getExceptionDetails($e)], 500);
        }
    }



    public function terminateACard(GlobalRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->eversendService->processCardTermination($validated);

            if ($result['success']) {
                return Utility::outputData(true , $result['message'], [],  200);
            }

            return Utility::outputData(true,  $result['message'], [],  $result['status_code']);

        } catch (\Exception $e) {
            return Utility::outputData(false, 'Unable to process request, please try again later', [Utility::getExceptionDetails($e)], 500);
        }
    }


}
