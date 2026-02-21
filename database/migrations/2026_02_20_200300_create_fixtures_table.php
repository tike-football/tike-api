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
        Schema::create('fixtures', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->unsignedBigInteger('provider_fixture_id');
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->string('round', 120)->nullable();
            $table->string('referee', 120)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->dateTime('fixture_date')->nullable();
            $table->unsignedBigInteger('timestamp')->nullable();
            $table->unsignedBigInteger('venue_provider_id')->nullable();
            $table->string('venue_name', 150)->nullable();
            $table->string('venue_city', 120)->nullable();
            $table->string('status_long', 80)->nullable();
            $table->string('status_short', 10)->nullable();
            $table->unsignedSmallInteger('status_elapsed')->nullable();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->smallInteger('home_goals')->nullable();
            $table->smallInteger('away_goals')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_fixture_id']);
            $table->index(['league_id', 'season']);
            $table->index('fixture_date');
            $table->index('status_short');
            $table->index('home_team_id');
            $table->index('away_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
