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
        Schema::create('fixture_team_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->foreignId('fixture_id')->constrained('fixtures')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->boolean('is_home')->default(false);
            $table->boolean('winner')->nullable();
            $table->smallInteger('goals')->nullable();
            $table->json('raw_lineup')->nullable();
            $table->json('raw_statistics')->nullable();
            $table->json('raw_events')->nullable();
            $table->json('raw_players')->nullable();
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['fixture_id', 'team_id']);
            $table->index('team_id');
            $table->index('is_home');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixture_team_stats');
    }
};
