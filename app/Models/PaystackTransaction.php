<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaystackTransaction extends Model
{
    use HasFactory;


    protected $guarded = [];


    protected $casts = [
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'log_json' => 'array'
    ];


    public function user(){

        return $this->belongsTo(User::class);
    }
}
