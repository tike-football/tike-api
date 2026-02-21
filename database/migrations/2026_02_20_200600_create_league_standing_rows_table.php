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
        Schema::create('league_standing_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_id')->constrained('league_standings')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank_position');
            $table->unsignedSmallInteger('points')->nullable();
            $table->smallInteger('goals_diff')->nullable();
            $table->unsignedSmallInteger('matches_played')->nullable();
            $table->unsignedSmallInteger('matches_win')->nullable();
            $table->unsignedSmallInteger('matches_draw')->nullable();
            $table->unsignedSmallInteger('matches_lose')->nullable();
            $table->unsignedSmallInteger('goals_for')->nullable();
            $table->unsignedSmallInteger('goals_against')->nullable();
            $table->string('row_form', 50)->nullable();
            $table->string('status', 120)->nullable();
            $table->string('row_description', 255)->nullable();
            $table->unsignedSmallInteger('home_played')->nullable();
            $table->unsignedSmallInteger('home_win')->nullable();
            $table->unsignedSmallInteger('home_draw')->nullable();
            $table->unsignedSmallInteger('home_lose')->nullable();
            $table->unsignedSmallInteger('home_goals_for')->nullable();
            $table->unsignedSmallInteger('home_goals_against')->nullable();
            $table->unsignedSmallInteger('away_played')->nullable();
            $table->unsignedSmallInteger('away_win')->nullable();
            $table->unsignedSmallInteger('away_draw')->nullable();
            $table->unsignedSmallInteger('away_lose')->nullable();
            $table->unsignedSmallInteger('away_goals_for')->nullable();
            $table->unsignedSmallInteger('away_goals_against')->nullable();
            $table->json('raw_row_payload');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['standing_id', 'team_id']);
            $table->index(['standing_id', 'rank_position']);
            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_standing_rows');
    }
};
