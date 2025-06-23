<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    use HasFactory;


    protected $table = 'kyc';

    protected $fillable = [
        'user_id',
        'bvn',
        'nin',
        'selfie',
        'utility_bill',
        'address',
        'admin_remark',
        'tier',
        'state',
        'zipcode',
        'id_image',
        'house_number',
        'status',
        'phone_number',
        'verification_image',
        'selfie_match',
        'selfie_confidence',
        'nationality',
        'dob'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
