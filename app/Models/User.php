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

}
