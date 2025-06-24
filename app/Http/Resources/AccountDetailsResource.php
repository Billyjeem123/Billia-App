<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Account Details',
            'id' => $this->id,
            'attributes' => [
                'account_name' => $this->account_name,
                'bank_name' => $this->bank_name,
                'account_number' => $this->account_number,
                'provider' => $this->provider,
            ]
        ];
    }
}
