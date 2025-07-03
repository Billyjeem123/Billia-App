<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Virtual Card',
            'id' => $this->id,
            'attributes' => [
                'card_id' => $this->card_id,
                'expiration' => $this->expiration,
                'currency' => $this->currency,
                'status' => $this->status,
                'name' => $this->name,
                'balance' => $this->balance,
                'brand' => $this->brand,
                'mask' => $this->mask,
                'number' => $this->number,
                "billingAddress" =>  [
                "address" => $this->billing_address,
                "city" => $this->billing_city,
                "state" => $this->billing_state,
                "zipCode" => $this->billing_zip_code,
      ],
            ]
        ];
    }
}
