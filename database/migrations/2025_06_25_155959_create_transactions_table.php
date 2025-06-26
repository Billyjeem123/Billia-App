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
//        Schema::dropIfExists('transactions');
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('description')->nullable();
            $table->string('external_reference')->nullable();


            $table->string('status')->default('pending');
            $table->string('purpose')->nullable()->comment('purchase, subscription, transfer etc.');

            $table->foreignId('paystack_customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('SET NULL');
            $table->json('metadata')->nullable();

            $table->string('payable_type')->nullable()->comment('Payment provider model class');
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->index(['payable_type', 'payable_id']);

            $table->string('provider')->comment('paystack, flutterwave, stripe');
            $table->string('reference')->nullable()->comment('Provider\'s transaction reference');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->index('status');
            $table->index('provider');
            $table->index('reference');
            $table->index('created_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
