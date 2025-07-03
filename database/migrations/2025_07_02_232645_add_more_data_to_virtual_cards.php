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
        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->string('card_id')->after('id')->nullable(); // 1st after 'id'
            $table->string('security_code')->nullable()->after('card_id');
            $table->string('expiration')->nullable()->after('security_code');
            $table->string('currency')->nullable()->after('expiration');
            $table->string('status')->nullable()->after('currency');
            $table->boolean('is_physical')->default(false)->after('status');
            $table->string('title')->nullable()->after('is_physical');
            $table->string('color')->nullable()->after('title');
            $table->string('name')->nullable()->after('color');
            $table->decimal('balance', 15, 2)->default(0.00)->after('name');
            $table->string('brand')->nullable()->after('balance');
            $table->string('mask')->nullable()->after('brand');
            $table->string('number')->nullable()->after('mask'); // consider encryption
            $table->string('owner_id')->nullable()->after('number');
            $table->string('last_used_on')->nullable()->after('owner_id');
            $table->boolean('is_non_subscription')->default(false)->after('last_used_on');

            // Billing address fields
            $table->string('billing_address')->nullable()->after('is_non_subscription');
            $table->string('billing_city')->nullable()->after('billing_address');
            $table->string('billing_state')->nullable()->after('billing_city');
            $table->string('billing_zip_code')->nullable()->after('billing_state');
            $table->string('billing_country')->nullable()->after('billing_zip_code');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('virtual_cards', function (Blueprint $table) {
            //
        });
    }
};
