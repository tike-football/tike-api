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
        Schema::create('league_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('start')->nullable();
            $table->date('end')->nullable();
            $table->boolean('current')->default(false);
            $table->json('structure')->nullable()->default(null);
            $table->timestamps();

            $table->unique(['league_id', 'year']);
            $table->index(['league_id', 'current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_seasons');
    }
};
