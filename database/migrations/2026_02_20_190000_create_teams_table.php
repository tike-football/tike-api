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
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->unsignedBigInteger('provider_team_id');
            $table->foreignId('league_id')->nullable()->constrained('leagues')->nullOnDelete();
            $table->unsignedSmallInteger('season')->nullable();
            $table->string('name', 150);
            $table->string('code', 20)->nullable();
            $table->string('country_name', 150)->nullable();
            $table->unsignedSmallInteger('founded')->nullable();
            $table->boolean('national')->default(false);
            $table->string('logo', 500)->nullable();
            $table->unsignedBigInteger('venue_provider_id')->nullable();
            $table->string('venue_name', 150)->nullable();
            $table->string('venue_address', 255)->nullable();
            $table->string('venue_city', 120)->nullable();
            $table->unsignedInteger('venue_capacity')->nullable();
            $table->string('venue_surface', 80)->nullable();
            $table->string('venue_image', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_team_id']);
            $table->index('league_id');
            $table->index('season');
            $table->index('name');
            $table->index('country_name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
