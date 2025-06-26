<?php

namespace App\Services;

use App\Events\ReferralRewardEarned;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    const REFERRAL_REWARD = 50.00;

    public function processReferral(string $referralCode, User $newUser, array $deviceInfo = []): ?Referral
    {
        return DB::transaction(function () use ($referralCode, $newUser, $deviceInfo) {
            // Find the referrer by their referral code
            $referrer = User::where('referral_code', $referralCode)->first();

            if (!$referrer) {
                return null;
            }

            // Prevent self-referral
            if ($referrer->id === $newUser->id) {
                return null;
            }

            // Check if user was already referred
            if ($newUser->referredBy()->exists()) {
                return null;
            }

            // Create referral record
            $referral = Referral::create([
                'referral_code' => $referralCode,
                'referrer_id' => $referrer->id,
                'referred_id' => $newUser->id,
                'reward_amount' => self::REFERRAL_REWARD,
                'status' => 'completed', // Set to completed when user is verified
                'referred_at' => now(),
                'rewarded_at' => now(),
                'device_info' => json_encode($deviceInfo),
                'ip_address' => request()->ip()
            ]);

            // Credit referrer's wallet
            $this->creditReferrerWallet($referrer, self::REFERRAL_REWARD);

            return $referral;
        });
    }

    private function creditReferrerWallet(User $referrer, float $amount): void
    {
        $wallet = $referrer->wallet;
        $wallet->increment('amount', $amount);

        // You can fire an event here for notifications
         event(new ReferralRewardEarned($referrer, $amount));
    }

    public function getReferralHistory(User $user, int $perPage = 20): array
    {
        $referrals = $user->referrals()
            ->with('referred:id,first_name,last_name,email,created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'referrals' => $referrals,
            'stats' => $user->getReferralStats()
        ];
    }
}
