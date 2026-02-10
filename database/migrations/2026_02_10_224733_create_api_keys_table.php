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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Friendly name for the API key');
            $table->string('key', 64)->unique()->comment('The API key itself');
            $table->string('platform', 50)->comment('Platform: ios, android, web, etc');
            $table->boolean('is_active')->default(true)->comment('Whether the key is active');
            $table->integer('rate_limit')->default(100)->comment('Requests per minute');
            $table->timestamp('last_used_at')->nullable()->comment('Last time this key was used');
            $table->timestamp('expires_at')->nullable()->comment('Expiration date');
            $table->timestamps();
            
            // Indexes
            $table->index('key');
            $table->index('platform');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
