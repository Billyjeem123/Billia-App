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
                'created_at' => optional($this->created_at)->format('M d, Y'),
            ]
        ];
    }
}
