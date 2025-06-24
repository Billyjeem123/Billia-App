<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'daily_limit',
        'wallet_balance',
    ];

    /**
     * Users belonging to this tier.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'account_level', 'name');
    }

    /**
     * Get formatted daily limit.
     */
    public function getFormattedDailyLimitAttribute()
    {
        return '₦' . number_format($this->daily_limit, 2);
    }

    /**
     * Get formatted wallet balance limit.
     */
    public function getFormattedWalletBalanceAttribute()
    {
        return is_null($this->wallet_balance)
            ? 'Unlimited'
            : '₦' . number_format($this->wallet_balance, 2);
    }

    public function getWalletBalanceAttribute($value)
    {
        return $value === null ? 'Unlimited' : '₦' . number_format($value, 2);
    }





}
