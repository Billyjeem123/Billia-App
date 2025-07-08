<?php

namespace App\Listeners;

use App\Events\ReferralRewardEarned;
use App\Helpers\Utility;
use App\Models\Transaction;
use App\Models\TransactionLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class HandleReferralReward
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ReferralRewardEarned $event): void
    {
        $referrer = $event->referrer;
        $amount = $event->amount;
        $wallet = $referrer->wallet;

        $reference = Utility::txRef("referral", "system", true);
        TransactionLog::create([
            'user_id' => $referrer->id,
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'category' => 'referral',
            'amount' => $amount,
            'transaction_reference' => $reference,
            'service_type' => 'referral bonus',
            'amount_after' => $wallet->fresh()->amount + $amount,
            'status' => 'successful',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'description' => 'Referral bonus reward',
            'payload' => json_encode([
                'source' => 'referral_program',
                'referrer_email' => $referrer->email,
            ])
        ]);

        // Send email notification
       // Mail::to($referrer->email)->send(new \App\Mail\ReferralBonusEarned($referrer, $amount));
    }
}
