<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'transaction_reference',
        'service_type',
        'amount',
        'amount_after',
        'payload',
        'provider_response',
        'status',
    ];


    public static function create_transaction($data):array{

        $transaction = TransactionLog::create([
            'user_id'        => auth()->id(),
            'transaction_reference' => $data['ref'] ??Str::uuid(),
            'service_type'   => $data['service_type'] ?? null,
            'amount'         => $data['amount'],
            'amount_after'   =>  $data['amount_after'] ?? 0,
            'payload'        => json_encode($data),
            'status'         => $data['status'] ?? 'pending',
        ]);
        return [
            'transaction_id' => $transaction->id
        ];
    }

    public static function update_info(string $transactionId, array $data): void
    {
        TransactionLog::where('id', $transactionId)->orWhere('transaction_reference', $transactionId)->update($data);
    }
}
