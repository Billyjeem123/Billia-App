<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Tiers',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'daily_limit' => $this->daily_limit,
                'balance' => $this->balance,
            ]
        ];
    }
}
