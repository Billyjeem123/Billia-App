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
        Schema::create('fraud_checks', function (Blueprint $table) {
            $table->id();
            $table->string('fraud_check_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 15, 2);
            $table->string('transaction_type');
            $table->enum('status', ['passed', 'failed', 'blocked']);
            $table->integer('risk_score');
            $table->json('risk_factors');
            $table->json('check_details');
            $table->json('context')->nullable();
            $table->enum('action_taken', ['none', 'block_transaction', 'restrict_account', 'ban_account']);
            $table->string('message');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'created_at']);
            $table->index(['fraud_check_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_checks');
    }
};
