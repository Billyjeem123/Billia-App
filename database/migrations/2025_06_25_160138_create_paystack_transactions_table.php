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
        Schema::create('paystack_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 50)->unique();
            $table->string('reference', 50)->unique();
            $table->string('webhook_event')->nullable();
            $table->string('type')->default('payment')->comment('payment, transfer, etc.');

            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('fees', 20, 2)->nullable();
            $table->string('channel', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('gateway_response', 255)->nullable();

            $table->string('authorization_code', 100)->nullable();
            $table->json('card_details')->nullable();

            $table->string('recipient_code')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('account_number')->nullable();
            $table->string('reason')->nullable();
            $table->string('transfer_code')->nullable();
            $table->string('account_name')->nullable();
            $table->string('transfer_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('SET NULL');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('reference');
            $table->index('status');
            $table->index('type');
            $table->index('recipient_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_transactions');
    }
};
