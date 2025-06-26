<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'maiden',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'pin',
        'referral_code',
        'referral_bonus',
        'account_level',
        'remember_token',
        'username',
        'otp',
        'role',
        'kyc_status',
        'kyc_type',
        'device_token',
        'device_type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function virtual_accounts()
    {
        return $this->hasMany(VirtualAccount::class, 'user_id')->select('id','user_id', 'account_name', 'bank_name', 'account_number', 'provider');
    }

    public function routeNotificationForFcm()
    {
        return $this->device_token;
    }

    // app/Models/User.php

    public static function findByEmailOrAccountNumber(string $identifier)
    {
        return self::where('email', $identifier)
            ->orWhereHas('virtual_accounts', function ($query) use ($identifier) {
                $query->where('account_number', $identifier);
            })
            ->first();
    }


    public function transactions()
    {
        return $this->hasMany(TransactionLog::class, 'user_id');
    }


    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    public static function generateUniqueReferralCode(string $firstName, string $lastName): string
    {
        do {
            $code = strtoupper(substr($firstName, 0, 3) . substr($lastName, 0, 3) . rand(1000, 9999));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    public function getReferralLink(): array
    {
        return [
            'referral_code' => $this->referral_code,
            'ios_link' => "https://apps.apple.com/app/yourapp?ref={$this->referral_code}",
            'android_link' => "https://play.google.com/store/apps/details?id=com.yourapp&ref={$this->referral_code}",
            'web_link' => url("/register?ref={$this->referral_code}")
        ];
    }

    public function getReferralStats(): array
    {
        $totalReferrals = $this->referrals()->count();
        $completedReferrals = $this->referrals()->completed()->count();
        $pendingReferrals = $this->referrals()->pending()->count();
        $totalEarnings = $this->referrals()->completed()->sum('reward_amount');

        return [
            'total_referrals' => $totalReferrals,
            'completed_referrals' => $completedReferrals,
            'pending_referrals' => $pendingReferrals,
            'total_earnings' => $totalEarnings,
            'referral_code' => $this->referral_code
        ];
    }


    public function tier()
    {
        return $this->hasOne(Tier::class, 'name', 'account_level');
    }




}
