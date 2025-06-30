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
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('locked_amount', 20, 2)
                ->default(0)
                ->after('user_id');

            $table->enum('status', ['active', 'locked', 'suspended'])
                ->default('active')
                ->after('amount'); // Adds status after amount
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet', function (Blueprint $table) {
            //
        });
    }
};
