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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('key'); // e.g., 'language', 'theme', etc.
            $table->string('value'); // e.g., 'es', 'en', 'dark', 'light', etc.
            $table->timestamps();

            // Unique constraint: one setting key per user
            $table->unique(['user_id', 'key']);
            
            // Index for faster queries
            $table->index(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
