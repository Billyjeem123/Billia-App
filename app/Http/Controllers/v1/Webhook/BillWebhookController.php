<?php

namespace App\Http\Controllers\v1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\TransactionLog;
use App\Models\Wallet;
use App\Services\BillLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillWebhookController extends Controller
{


    public function verifyWebhookStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $response = $request->all();

        BillLogger::log('VTU Webhook - Initial Request Received', ['full_response' => $response]);

        if ($response['type'] != "transaction-update") {
            BillLogger::log('VTU Webhook - Invalid Type', [
                'received_type' => $response['type'] ?? 'null',
                'expected_type' => 'transaction-update'
            ]);
            return response()->json(['response'=>'error']);
        }

        $requestId = $response['data']['requestId'];
        BillLogger::log('VTU Webhook - Processing Request', ['request_id' => $requestId]);

        $trans = TransactionLog::where('vtpass_transaction_id', $requestId)->first();
        if (!$trans) {
            BillLogger::log('VTU Webhook - Transaction Not Found', [
                'request_id' => $requestId,
                'searched_field' => 'vtpass_transaction_id'
            ]);
            return response()->json(['response'=>'error']);
        }

        $user_id = $trans->user_id;
        $type = $response['data']['content']['transactions']['type'];
        $status = $response['data']['content']['transactions']['status'];

        BillLogger::log('VTU Webhook - Transaction Found', [
            'request_id' => $requestId,
            'user_id' => $user_id,
            'transaction_type' => $type,
            'status' => $status,
            'current_trans_status' => $trans->status
        ]);

        if ($status == "delivered") {
            BillLogger::log('VTU Webhook - Processing Successful Transaction', [
                'request_id' => $requestId,
                'user_id' => $user_id,
                'old_status' => $trans->status,
                'new_status' => 'successful'
            ]);

            $trans->update(['status' => 'successful', 'vtpass_webhook_data' => $response['data']]);

            BillLogger::log('VTU Webhook - Transaction Marked Successful', [
                'request_id' => $requestId,
                'user_id' => $user_id
            ]);

            return response()->json(['response'=>'success']);

        } elseif ($status == "reversed") {
            $amount = $response['data']['amount'];

            BillLogger::log('VTU Webhook - Processing Reversed Transaction', [
                'request_id' => $requestId,
                'user_id' => $user_id,
                'refund_amount' => $amount,
                'transaction_type' => $type,
                'old_status' => $trans->status
            ]);

            #  Credit wallet
            $creditResult = Wallet::credit_recipient($amount, $user_id);

            BillLogger::log('VTU Webhook - Wallet Credit Attempted', [
                'request_id' => $requestId,
                'user_id' => $user_id,
                'amount' => $amount,
                'credit_result' => $creditResult
            ]);

            #  Update transaction status
            $trans->update(['status' => 'reversed', 'vtpass_webhook_data' => $response['data']]);

            BillLogger::log('VTU Webhook - Transaction Reversed Successfully', [
                'request_id' => $requestId,
                'user_id' => $user_id,
                'refunded_amount' => $amount
            ]);

            return response()->json(['response'=>'success']);
        }

        BillLogger::log('VTU Webhook - Unhandled Status', [
            'request_id' => $requestId,
            'status' => $status,
            'user_id' => $user_id,
            'available_statuses' => ['delivered', 'reversed']
        ]);

        return response()->json(['response'=>'error']);
    }


}
