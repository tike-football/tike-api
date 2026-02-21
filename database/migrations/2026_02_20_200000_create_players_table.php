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
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->default('api_football');
            $table->unsignedBigInteger('provider_player_id');
            $table->string('firstname', 120)->nullable();
            $table->string('lastname', 120)->nullable();
            $table->string('full_name', 180)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 120)->nullable();
            $table->string('birth_country', 120)->nullable();
            $table->string('nationality', 120)->nullable();
            $table->string('height', 20)->nullable();
            $table->string('weight', 20)->nullable();
            $table->boolean('injured')->default(false);
            $table->string('photo', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('external_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_player_id']);
            $table->index('full_name');
            $table->index('nationality');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
