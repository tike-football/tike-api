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
        Schema::create('leagues', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->unsignedBigInteger('provider_league_id');
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('country_name', 150)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('flag', 500)->nullable();
            $table->boolean('current')->default(true);
            $table->boolean('is_active')->default(false);
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_league_id']);
            $table->index('name');
            $table->index('country_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};

