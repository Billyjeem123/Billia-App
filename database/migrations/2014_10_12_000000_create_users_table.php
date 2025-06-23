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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('maiden')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->unique();
            $table->string('pin');
            $table->string('referral_code')->nullable();
            $table->string('referral_bonus')->nullable();
            $table->string('username')->unique();
            $table->string('otp')->nullable();
            $table->string('role')->default('user');
            $table->enum('kyc_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->enum('kyc_type', ['none', 'nin', 'bvn', 'both'])->default('none');
            $table->enum('account_level', ['tier_1', 'tier_2', 'tier_3'])->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
