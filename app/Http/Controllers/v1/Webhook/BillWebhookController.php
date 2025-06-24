<?php

namespace App\Http\Controllers\v1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\TransactionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillWebhookController extends Controller
{
    public function handleVtpassWebhook(Request $request)
    {
        $payload = $request->all();

        if (!isset($payload['type']) || $payload['type'] !== 'transaction-update') {
            return response()->json(['message' => 'Invalid webhook'], 400);
        }

        $data = $payload['data'];
        $status = $data['content']['transactions']['status'];
        $requestId = $data['requestId'];
        $transactionId = $data['content']['transactions']['transactionId'];

        // Find the transaction in your DB
        $transaction = TransactionLog::where('request_id', $requestId)->first();

        if (!$transaction) {
            Log::warning("Unknown VTpass transaction", ['requestId' => $requestId]);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($status === 'delivered') {
            $transaction->status = 'successful';
        } elseif ($status === 'reversed') {
            $transaction->status = 'reversed';
            // Optional: refund user wallet
        } else {
            $transaction->status = 'failed';
        }

        $transaction->vtpass_transaction_id = $transactionId;
        $transaction->save();

        return response()->json(['message' => 'Processed'], 200);
    }

}
