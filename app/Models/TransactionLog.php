<?php

namespace App\Models;

use App\Helpers\Utility;
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
        'vtpass_webhook_data',
        'wallet_id',
        'provider',
        'channel',
        'type',
        'description'
    ];

    protected $casts = [
        'vtpass_webhook_data' => 'array',
         'payload' => 'array',
    ];


    public static function create_transaction($data):array{

        $ref = Utility::txRef("bills", "system", true);
        $transaction = TransactionLog::create([
            'user_id'        => auth()->id(),
            'transaction_reference' => $ref,
            'service_type'   => $data['service_type'] ?? null,
            'amount'         => $data['amount'],
            'amount_after'   =>  $data['amount_after'] ?? 0,
            'payload'        => json_encode($data),
            'status'         => $data['status'] ?? 'pending',
             'wallet_id' => $data['wallet_id'] ?? null,
            'provider'  =>  $data['provider'],
            'channel' => 'Internal',
             'type' => $data['type'],
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
