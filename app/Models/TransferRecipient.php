<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferRecipient extends Model
{
    use HasFactory;

    public $table = 'transfer_recipients';

    protected $fillable = [
        'account_number',
        'account_name',
        'bank_code',
        'bank_name',
        'recipient_code',
        'is_active',
        'user_id',
        'currency',
        'type',
        'metadata',
    ];


    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope for active recipients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

}
