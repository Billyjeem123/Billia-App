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
        Schema::table('kyc', function (Blueprint $table) {
            $table->string('dl_uuid')->after('id')->nullable();
            $table->string('dl_licenseNo')->after('dl_uuid')->nullable();
            $table->string('dl_issuedDate')->after('dl_licenseNo')->nullable();
            $table->string('dl_expiryDate')->after('dl_issuedDate')->nullable();
            $table->string('dl_issuedBy')->after('dl_expiryDate')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc', function (Blueprint $table) {
            //
        });
    }
};
