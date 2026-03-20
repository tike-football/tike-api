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
        Schema::create('pool_fixtures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('pools')->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained('fixtures')->cascadeOnDelete();
            $table->boolean('allows_repeated_scores')->default(true);
            $table->unsignedInteger('score_repeat_limit')->nullable();
            $table->string('score_selection_type', 50);
            $table->timestamps();

            $table->unique(['pool_id', 'fixture_id']);
            $table->index('fixture_id');
            $table->index('score_selection_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_fixtures');
    }
};
