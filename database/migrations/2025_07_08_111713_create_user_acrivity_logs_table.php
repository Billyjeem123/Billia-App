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
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity')->index(); // What they did
            $table->string('description')->nullable(); // Human readable description
            $table->string('page_url')->nullable(); // Where they were
            $table->json('properties')->nullable(); // Additional data
            $table->ipAddress('ip_address');
            $table->string('user_agent');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['activity']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
