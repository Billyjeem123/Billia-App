<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'User',
            'id' => $this->resource->id,
            'attributes' => [
                # User-related fields
                'first_name' => $this->first_name ?? null,
                'maiden_name' => $this->maiden_name ?? null,
                'last_name'  => $this->last_name ?? null,
                'email'      => $this->email ?? null,
                'phone'      => $this->phone ?? null,
                'username'    => $this->username ?? null,
                'kyc_status' => $this->kyc_status,
                'kyc_type'    => $this->kyc_type,
                'has_virtual_card' => $this->whenLoaded('virtual_cards', fn () => !is_null($this->virtual_cards)),
             //   'virtual_card' => $this->whenLoaded('virtual_cards', fn () => $this->virtual_cards ?? null),
                'account_level' => $this->account_level ?? null,
                'account_balance' => $this->whenLoaded('wallet', function () {
                    return $this->wallet->amount ?? 0;
                }),
                'account_details' => $this->whenLoaded('virtual_accounts', function () {
                    return  AccountDetailsResource::collection($this->virtual_accounts);
                }),
                'created_at' => optional($this->created_at)->format('M d, Y'),
            ]
        ];
    }
}
