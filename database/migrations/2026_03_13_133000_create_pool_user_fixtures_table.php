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
        Schema::create('pool_user_fixtures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('pools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained('fixtures')->cascadeOnDelete();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedInteger('season');
            $table->string('round')->nullable();
            $table->string('timezone', 100)->nullable();
            $table->dateTime('fixture_date')->nullable();
            $table->unsignedBigInteger('timestamp')->nullable();
            $table->string('status_long', 100)->nullable();
            $table->string('status_short', 20)->nullable();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->integer('home_goals')->nullable();
            $table->integer('away_goals')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('entry_order')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['pool_id', 'user_id', 'fixture_id']);
            $table->index('league_id');
            $table->index('season');
            $table->index('entry_order');
            $table->index('is_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_user_fixtures');
    }
};
