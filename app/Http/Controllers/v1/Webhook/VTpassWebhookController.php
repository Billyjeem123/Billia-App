<?php

namespace App\Http\Controllers\v1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\TransactionLog;
use App\Models\Wallet;
use App\Services\BillLogger;
use App\Services\VTpassWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VTpassWebhookController  extends Controller
{

    protected $webhookService;

    public function __construct(VTpassWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function processVtPassWebHook(Request $request)
    {
        try {
           #  Log the incoming webhook for debugging
            BillLogger::log('VTpass Webhook Received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all()
            ]);

           #  Validate the webhook payload
            $validatedData = $request->all();

           #  Process the webhook based on type
            if ($validatedData['type'] === 'transaction-update') {
                $this->webhookService->handleTransactionUpdate($validatedData['data']);
            }

           #  Return 200 OK to acknowledge receipt
            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
           BillLogger::error('VTpass Webhook Error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

           #  Return 200 to prevent VTpass from retrying
            return response()->json(['status' => 'error', 'message' => 'Webhook processed'], 200);
        }
    }


}
