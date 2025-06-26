<?php

namespace App\Listeners;

use App\Events\ReferralRewardEarned;
use App\Helpers\Utility;
use App\Models\Transaction;
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

        // Log transaction
        Transaction::create([
            'user_id' => $referrer->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'type' => 'credit',
            'status' => 'successful',
            'purpose' => 'referral_bonus',
            'provider' => 'system',
            'channel' => 'internal',
            'currency' => 'NGN',
            'reference' => $reference,
            'description' => 'Referral bonus reward',
            'metadata' => json_encode([
                'source' => 'referral_program',
                'referrer_email' => $referrer->email,
            ])
        ]);

        // Send email notification
        Mail::to("billyhadiattaofeeq@gmail.com")->send(new \App\Mail\ReferralBonusEarned($referrer, $amount));
    }
}
