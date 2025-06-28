<?php

namespace App\Http\Controllers\v1\Payment;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalRequest;
use App\Services\PaystackTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaystackTransferController extends Controller
{
    private $transferService;

    public function __construct(PaystackTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Transfer funds from wallet to bank
     */

    public function transferToBank(GlobalRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

       #  Verify transaction PIN
        if (!$this->verifyTransactionPin($user, $validated['transaction_pin'])) {
            return Utility::outputData(false, 'Invalid transaction PIN', null, 403);
        }

        $transferData = [
            'amount' => $validated['amount'],
            'account_number' => $validated['account_number'],
            'bank_code' => $validated['bank_code'],
            'account_name' => $validated['account_name'],
            'bank_name' => $validated['bank_name'] ?? null,
            'narration' => $validated['narration']
        ];

        $result = $this->transferService->transferToBank($user, $transferData);

        return Utility::outputData(
            $result['success'],
            $result['message'] ?? ($result['success'] ? 'Transfer successful' : 'Transfer failed'),
            $result['data'] ?? null,
            $result['success'] ? 200 : 400
        );
    }


    /**
     * Get list of supported banks
     */
    public function getBanks(): JsonResponse
    {
        $banks = $this->transferService->getBanks();

        return response()->json([
            'success' => true,
            'message' => 'Banks retrieved successfully',
            'data' => $banks['data'] ?? []
        ]);
    }

    /**
     * Resolve account number to get account name
     */
    public function resolveAccount(GlobalRequest $request)
    {
        $validated = $request->validated();

        // Return the JsonResponse directly
        return $this->transferService->resolveAccountNumber(
            $validated['account_number'],
            $validated['bank_code']
        );
    }


    /**
     * Get user's transfer history
     */
    public function getTransferHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        $transfers = $user->transactions()
            ->where('service_type', 'wallet_transfer')
            ->where('type', 'debit')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Transfer history retrieved successfully',
            'data' => $transfers
        ]);
    }

    /**
     * Get transfer details by reference
     */
    public function getTransferDetails(Request $request, string $reference): JsonResponse
    {
        $user = Auth::user();

        $transfer = $user->transactions()
            ->where('transaction_reference', $reference)
            ->where('service_type', 'wallet_transfer')
            ->first();

        if (!$transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfer details retrieved successfully',
            'data' => $transfer
        ]);
    }

    /**
     * Verify user's transaction PIN
     */
    private function verifyTransactionPin($user, string $pin): bool
    {
       #  Implement your PIN verification logic here
       #  This could be hashed PIN comparison
        return password_verify($pin, $user->pin);
    }

    public function verifyTransferStatus(Request $request)
    {
        return $this->transferService->verifyTransfer($request->reference);

    }
}
