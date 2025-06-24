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
        'vtpass_transaction_id',
        'vtpass_webhook_data'
    ];

    protected $casts = [
        'vtpass_webhook_data' => 'array',
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
        $providerData = json_decode($data['provider_response'] ?? '{}', true);

        if (isset($providerData['content']['transactions']['transactionId'])) {
            $data['vtpass_transaction_id'] = $providerData['content']['transactions']['transactionId'];
        }

        TransactionLog::where('id', $transactionId)
            ->orWhere('transaction_reference', $transactionId)
            ->update($data);
    }
}
