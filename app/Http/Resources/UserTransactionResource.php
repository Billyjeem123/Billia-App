<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Transaction',
            'id' => $this->id,
            'attributes' => [
                'user_id' => $this->user_id,
                'reference' => $this->transaction_reference,
                'service_type' => $this->service_type,
                'amount' => $this->amount,
                'amount_after' => $this->amount_after,
//                'payload' => $this->decodePayload($this->payload),
                //'provider_response' => json_decode($this->provider_response),
                'status' => $this->status,
                'provider' => $this->provider,
                'channel' => $this->channel,
                'type' => $this->type,
                'description' => $this->description,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]
        ];
    }

    private function decodePayload($payload)
    {
        $decoded = json_decode($payload, true);

        #  Try to decode nested payload inside the decoded result
        if (isset($decoded['payload']) && is_string($decoded['payload'])) {
            $nested = json_decode($decoded['payload'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded['payload'] = $nested;
            }
        }

        return $decoded;
    }

}
