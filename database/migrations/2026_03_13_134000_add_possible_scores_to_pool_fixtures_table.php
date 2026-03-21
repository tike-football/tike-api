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
        Schema::table('pool_fixtures', function (Blueprint $table): void {
            $table->json('possible_scores')->nullable()->after('score_selection_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pool_fixtures', function (Blueprint $table): void {
            $table->dropColumn('possible_scores');
        });
    }
};
