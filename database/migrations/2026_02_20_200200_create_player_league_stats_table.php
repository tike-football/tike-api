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
        Schema::create('player_league_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->unsignedSmallInteger('games_appearences')->nullable();
            $table->unsignedSmallInteger('games_lineups')->nullable();
            $table->unsignedInteger('games_minutes')->nullable();
            $table->unsignedSmallInteger('goals_total')->nullable();
            $table->unsignedSmallInteger('goals_assists')->nullable();
            $table->unsignedSmallInteger('cards_yellow')->nullable();
            $table->unsignedSmallInteger('cards_red')->nullable();
            $table->json('raw_statistics');
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'team_id', 'league_id', 'season'], 'player_league_stats_unique_row');
            $table->index(['league_id', 'season']);
            $table->index(['team_id', 'season']);
            $table->index(['player_id', 'season']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_league_stats');
    }
};
