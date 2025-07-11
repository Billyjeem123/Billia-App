<?php

namespace App\Http\Controllers\v1\Transaction;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Http\Resources\UserTransactionResource;
use App\Services\ActivityTracker;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    protected ActivityTracker $tracker;
    public function __construct(TransactionService $transactionService, ActivityTracker $activityTracker)
    {
        $this->transactionService = $transactionService;
        $this->tracker = $activityTracker;
    }


    public function myTransactionHistory(GlobalRequest $request, $id = null): JsonResponse
    {
        try {
               $validatedData = $request->validated();
               $filters = $validatedData;

            if ($id) {
                $transaction = $this->transactionService->getUserTransactionById($id);
                if (!$transaction) {
                    return Utility::outputData(false, "Transaction not found", null, 404);
                }
                return Utility::outputData(true, "Transaction retrieved successfully", new UserTransactionResource($transaction), 200);
            }

            $transactions = $this->transactionService->getAllUserTransactions();


            $this->tracker->track('transaction_history', "viewed transaction history", [
                "effective" => true,
            ]);
            return Utility::outputData(true, "Transactions retrieved successfully", [
                'data' => UserTransactionResource::collection($transactions['data']),
                'pagination' => $transactions['pagination']
            ], 200);

        } catch (Throwable $e) {
            Log::error("Error fetching transactions: " . $e->getMessage());
            return Utility::outputData(false, "Unable to process request, please try again later", [], 500);
        }
    }

}
