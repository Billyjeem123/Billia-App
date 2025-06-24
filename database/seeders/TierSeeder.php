<?php

namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Tier::updateOrCreate(['name' => 'tier_1'], [
            'daily_limit' => 50000,
            'wallet_balance' => 100000,
        ]);

        Tier::updateOrCreate(['name' => 'tier_2'], [
            'daily_limit' => 200000,
            'wallet_balance' => 500000,
        ]);

        Tier::updateOrCreate(['name' => 'tier_3'], [
            'daily_limit' => 5000000,
            'wallet_balance' => null,
        ]);
    }
}
