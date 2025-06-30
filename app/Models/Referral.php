<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;


    protected $fillable = [
        'referral_code',
        'referrer_id',
        'referred_id',
        'reward_amount',
        'status',
        'referred_at',
        'rewarded_at',
        'device_info',
        'ip_address'
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'reward_amount' => 'decimal:2'
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public static function isIpSuspicious(string $ip, int $threshold = 2, string $interval = '6 hours'): bool
    {
        return self::where('ip_address', $ip)
                ->where('created_at', '>=', now()->subHours((int) $interval))
                ->count() > $threshold;
    }
}
