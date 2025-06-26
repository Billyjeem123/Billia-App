<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code');
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->decimal('reward_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('referred_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index(['referred_id']);
            $table->index('referral_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
