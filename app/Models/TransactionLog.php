<?php

namespace App\Models;

use App\Helpers\Utility;
use App\Services\PaymentLogger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'amount_before',
        'description',
        'category'
    ];

    protected $casts = [
        'vtpass_webhook_data' => 'array',
         'payload' => 'array',
    ];


    public function user(){

        return $this->belongsTo(User::class, 'user_id');
    }

    public static function create_transaction($data):array{

        $ref = Utility::txRef("bills", "system", true);
        $transaction = TransactionLog::create([
                    'user_id'        => auth()->id(),
                    'transaction_reference' => $ref,
                    'service_type'   => $data['service_type'] ?? null,
                    'amount'         => $data['amount'],
                    'amount_before' => $data['amount_before'] ?? null,
                    'amount_after'   =>  $data['amount_after'] ?? 0,
                    'payload'        => json_encode($data),
                    'status'         => $data['status'] ?? 'pending',
                    'wallet_id' => $data['wallet_id'] ?? null,
                    'provider'  =>  $data['provider'],
                    'category'  =>  $data['service_type'],
                    'channel' => 'Internal',
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
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


    /**
     * Check user limits (wallet and daily).
     *
     * @param User $user
     * @param float $amount
     * @return array [bool status, string|null message]
     */
    public static function checkLimits(User $user, float $amount): array
    {
        $tier = $user->tier;

        if (!$tier) {
            PaymentLogger::log("Tier not found for user ID {$user->id}");
            return [false, 'No tier limits found for your account.'];
        }

        #  Wallet balance limit check (optional feature)
        if (!is_null($tier->wallet_balance)) {
            $walletAmount = optional($user->wallet)->amount ?? 0;
            $total = $walletAmount + $amount;

            if (($walletAmount + $amount) > $tier->wallet_balance) {
                PaymentLogger::log("User ID {$user->id} exceeded wallet balance limit. Attempted: ₦{$total}, Max: ₦{$tier->wallet_balance}");
                return [
                    false,
                    "Wallet limit exceeded. Max allowed: ₦" . number_format($tier->wallet_balance)
                ];
            }
        }

        #  Daily transaction total
        $todayTotal = $user->transactions()
            ->whereDate('created_at', now())
            ->sum('amount');
        $totalAmountToday = $todayTotal + $amount;

        if (($todayTotal + $amount) > $tier->daily_limit) {
            PaymentLogger::log("User ID {$user->id} exceeded daily limit. Attempted: ₦{$totalAmountToday}, Max: ₦{$tier->daily_limit}");
            return [
                false,
                "Daily transaction limit exceeded. Max allowed: ₦" . number_format($tier->daily_limit),
                [
                    'amount_used_today' => $todayTotal,
                    'attempted_transaction' => $amount,
                    'max_allowed' => $tier->daily_limit,
                    'remaining_quota' => $tier->daily_limit - $todayTotal
                ],
            ];
        }

        #  Passed all checks
        return [true, null];
    }


    #  In TransactionLog.php (Model)
    public static function isDuplicateTransfer($userId, $amount, $identifier): ?self
    {
        return self::where('user_id', $userId)
            ->where('amount', $amount)
            ->where('service_type', 'in-app-transfer')
            ->where('created_at', '>', now()->subSeconds(5))
            ->where('payload', 'LIKE', '%' . $identifier . '%')
            ->first();
    }






}
