<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaystackTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Paystack Transactions',
            'id' => $this->id,
            'attributes' => [
                'transaction_id' => $this->transaction_id,
                'reference' => $this->reference,
                'type' => $this->type,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'fees' => $this->fees,
                'channel' => $this->channel,
                'status' => $this->status,
                'gateway_response' => $this->gateway_response,
                'authorization_code' => $this->authorization_code,
                'card_details' => $this->card_details,
                'recipient_code' => $this->recipient_code,
                'bank_code' => $this->bank_code,
                'webhook_event' => $this->webhook_event,
                'transfer_reason' => $this->transfer_reason,
                'user_id' => $this->user_id,
                'reason' => $this->reason,
                'transfer_code' => $this->transfer_code,
                'paid_at' => $this->paid_at,
                'failed_at' => $this->failed_at,
                'metadata' => $this->metadata,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }

}

