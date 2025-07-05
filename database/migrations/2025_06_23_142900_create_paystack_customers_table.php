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
        Schema::create('paystack_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('paystack_customer_id')->unique()->comment('Paystack customer ID');
            $table->string('customer_code')->unique()->comment('Paystack customer code');
            $table->integer('paystack_integration_id')->nullable();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();

            $table->string('risk_action')->default('default');
            $table->boolean('identified')->default(false);
            $table->json('identifications')->nullable();

            $table->json('authorizations')->nullable()->comment('Payment authorizations');
            $table->json('paystack_raw_data')->nullable()->comment('Original Paystack response');

            $table->timestamps();
            $table->timestamp('paystack_created_at')->nullable();
            $table->timestamp('paystack_updated_at')->nullable();

            $table->index('email');
            $table->index('customer_code');
            $table->index('paystack_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
