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
        Schema::create('team_player_season', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->unsignedSmallInteger('shirt_number')->nullable();
            $table->string('position', 60)->nullable();
            $table->boolean('is_current')->default(true);
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'team_id', 'season']);
            $table->index(['team_id', 'season']);
            $table->index(['player_id', 'season']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_player_season');
    }
};
