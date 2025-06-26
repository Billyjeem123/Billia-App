<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualCard extends Model
{
    use HasFactory;

    protected $table = 'virtual_cards';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'state',
        'city',
        'address',
        'zip_code',
        'id_type',
        'id_number',
        'eversend_user_id',
        'eversend_card_id',
        'card_status',
        'api_response',
    ];

    protected $casts = [
        'api_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'id_number',
        'api_response',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isActive(): bool
    {
        return $this->card_status === 'active';

}
