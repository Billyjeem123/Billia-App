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
        Schema::create('kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('SET NULL');
            $table->string('bvn')->nullable()->unique();
            $table->string('nin')->nullable();
            $table->string('id_image')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('selfie')->nullable();
            $table->string('utility_bill')->nullable();
            $table->string('address')->nullable();
            $table->string('admin_remark')->nullable();
            $table->enum('tier', ['tier_1', 'tier_2', 'tier_3'])->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('phone_number', 20)->nullable();
            $table->string('selfie_confidence')->nullable();
            $table->string('selfie_match')->nullable();
            $table->string('selfie_image')->nullable();
            $table->string('verification_image')->nullable();
            $table->string('nationality')->nullable();
            $table->string('dob')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc');
    }
};
