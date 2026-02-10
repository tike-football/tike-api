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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique()->comment('ISO 3166-1 alpha-2 code');
            $table->string('code_alpha3', 3)->unique()->comment('ISO 3166-1 alpha-3 code');
            $table->string('name')->comment('Country name in English');
            $table->string('native_name')->nullable()->comment('Country name in native language');
            $table->string('phone_code', 10)->comment('International dialing code');
            $table->string('flag_emoji', 10)->comment('Country flag emoji');
            $table->string('currency_code', 3)->nullable()->comment('ISO 4217 currency code');
            $table->string('region')->nullable()->comment('Geographic region');
            $table->timestamps();
            
            // Indexes
            $table->index('code');
            $table->index('phone_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
