<?php

namespace App\Models;

use App\Helpers\Utility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';

    public $fillable = [
        'user_id',
        'amount'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public static function getBalance(): float
    {
        $user = Auth::user();
        $wallet = self::where('user_id', $user->id)->first();
        if (!$wallet) {
            return 0.00;
        }
        return $wallet->amount;
    }

    public static function check_balance()
    {
        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->id)->first();
        if (!$wallet) {
            return 0;
        }
        return $wallet->amount;
    }


    public static function credit_recipient(float $amount, int $receiverId): ?float
    {
        $wallet = self::where('user_id', $receiverId)->first();

        if (!$wallet) {
            return null;
        }

        $wallet->amount += $amount;
        $wallet->save();

        return $wallet->amount;
    }


    public static function add_to_wallet(float $amount): ?float
    {
        $user = Auth::user();
        $wallet = self::where('user_id', $user->id)->first();

        if (!$wallet) {
            return null;
        }

        $wallet->amount += $amount;
        $wallet->save();

        return $wallet->amount;
    }


    public static function remove_From_wallet($amount): float
    {
        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            return 0.00;
        }

        try {
            $currentBalance = $wallet->amount;
        } catch (\Throwable $e) {
            return 0.00;
        }

        if ($currentBalance < $amount) {
            return $currentBalance; // return current balance if insufficient
        }

        try {
           // ReferralController::handleReferralBonus('downline_referral', $user);
        } catch (\Throwable $e) {
            // silently fail
        }

        $newBalance = $currentBalance - $amount;
        $wallet->amount = $newBalance;
        $wallet->save();

        return $newBalance;
    }


    public static function credit_user_wallet(float $amount, int $user_id): ?float
    {
        $wallet = self::where('user_id', $user_id)->first();

        if (!$wallet) {
            return null;
        }

        $wallet->amount += $amount;
        $wallet->save();

        return $wallet->amount;
    }




}
