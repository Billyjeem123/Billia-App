<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];


    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function user(){

        return $this->belongsTo(User::class);
    }

    public function wallet(){

        return $this->belongsTo(Wallet::class);
    }


    public static  function checkLimits($user, float $amount): array
    {
        $tier = $user->tier;
        if (!$tier) {
            return [false, 'No tier limits found for your account.'];
        }
        if (!is_null($tier->wallet_balance) && ($user->wallet->amount + $amount) > $tier->wallet_balance) {
            return [false, "Wallet limit exceeded. Max: ₦" . number_format($tier->wallet_balance)];
        }

        # Check daily transaction limit
        $todayTotal = $user->transactions()
            ->whereDate('created_at', now())
            ->sum('amount');

        if (($todayTotal + $amount) > $tier->daily_limit) {
            return [false, "Daily limit exceeded. Max: ₦" . number_format($tier->daily_limit)];
        }

        return [true, null];
    }

}
