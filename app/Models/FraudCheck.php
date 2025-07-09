<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraudCheck extends Model
{

    protected  $table = 'fraud_checks';

    protected $fillable = [
        'fraud_check_id',
        'user_id',
        'amount',
        'transaction_type',
        'status',
        'risk_score',
        'risk_factors',
        'check_details',
        'context',
        'action_taken',
        'message',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'check_details' => 'array',
        'context' => 'array',
        'amount' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   #  Scope for failed checks only
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

   #  Scope for specific user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

   #  Get risk factors as formatted string
    public function getRiskFactorsStringAttribute()
    {
        return is_array($this->risk_factors) ? implode(', ', $this->risk_factors) : $this->risk_factors;
    }
}
