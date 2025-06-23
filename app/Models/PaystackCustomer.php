<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaystackCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'paystack_customer_id',
        'customer_code',
        'paystack_integration_id',
        'first_name',
        'last_name',
        'email',
        'user_id',
        'phone',
        'risk_action',
        'identified',
        'identifications',
        'authorizations',
        'paystack_raw_data',
        'paystack_created_at',
        'paystack_updated_at'
    ];


    public function user(){

        return $this->belongsTo(User::class, 'user_id');
    }


    protected $casts = [
        'identifications' => 'array',
        'authorizations' => 'array',
        'paystack_raw_data' => 'array',
        'identified' => 'boolean',
        'paystack_created_at' => 'datetime',
        'paystack_updated_at' => 'datetime'
    ];
}
