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
        if (!Schema::hasColumn('transaction_logs', 'wallet_id')) {
            Schema::table('transaction_logs', function (Blueprint $table) {
                $table->foreignId('wallet_id')->nullable()->after('user_id')->constrained()->onDelete('SET NULL');
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
