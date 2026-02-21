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
        Schema::create('league_standings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->string('standing_type', 60)->nullable();
            $table->string('standing_group', 120)->nullable();
            $table->string('standing_stage', 120)->nullable();
            $table->string('form', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'league_id', 'season', 'standing_group', 'standing_stage'],
                'league_standings_unique_scope'
            );
            $table->index(['league_id', 'season']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_standings');
    }
};
