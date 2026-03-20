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
        Schema::create('pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained('leagues')->nullOnDelete();
            $table->foreignId('league_season_id')->nullable()->constrained('league_seasons')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('scope', 50);
            $table->string('start_phase', 50)->nullable();
            $table->string('type', 100);
            $table->boolean('accepts_join_requests')->default(true);
            $table->boolean('requires_join_approval')->default(false);
            $table->string('code', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index('league_id');
            $table->index('league_season_id');
            $table->index('group_id');
            $table->index('scope');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pools');
    }
};
