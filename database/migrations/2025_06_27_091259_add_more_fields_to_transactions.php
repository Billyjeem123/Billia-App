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
        if (!Schema::hasColumn('transaction_logs', 'wallet_id') ||
            !Schema::hasColumn('transaction_logs', 'provider') ||
            !Schema::hasColumn('transaction_logs', 'channel') ||
            !Schema::hasColumn('transaction_logs', 'type')) {

            Schema::table('transaction_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('transaction_logs', 'wallet_id')) {
                    $table->foreignId('wallet_id')->nullable()
                        ->after('id')
                        ->constrained('wallets')
                        ->onDelete('cascade');
                }

                if (!Schema::hasColumn('transaction_logs', 'provider')) {
                    $table->string('provider')
                        ->nullable()
                        ->after('wallet_id');
                }

                if (!Schema::hasColumn('transaction_logs', 'channel')) {
                    $table->string('channel')
                        ->nullable()
                        ->after('provider');
                }

                if (!Schema::hasColumn('transaction_logs', 'type')) {
                    $table->string('type')
                        ->nullable()
                        ->after('channel');
                }
            });
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_logs', 'wallet_id')) {
                $table->dropForeign(['wallet_id']);
                $table->dropColumn('wallet_id');
            }

            if (Schema::hasColumn('transaction_logs', 'provider')) {
                $table->dropColumn('provider');
            }

            if (Schema::hasColumn('transaction_logs', 'channel')) {
                $table->dropColumn('channel');
            }

            if (Schema::hasColumn('transaction_logs', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
