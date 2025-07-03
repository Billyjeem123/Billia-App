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
        Schema::create('virtual_cards', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('country', 2);
            $table->string('state');
            $table->string('city');
            $table->string('provider')->nullable();
            $table->text('address');
            $table->string('zip_code');
            $table->enum('id_type', ['National_ID', 'Passport', 'Driving_License']);
            $table->string('id_number');
            $table->string('eversend_user_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('SET NULL');
            $table->string('card_status')->nullable();
            $table->json('api_response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'phone']);
            $table->index('eversend_user_id');
            $table->index('eversend_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_cards');
    }
};
