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
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_comments')->default(false);
            $table->boolean('accepts_join_requests')->default(true);
            $table->boolean('requires_join_approval')->default(false);
            $table->string('language', 2)->default((string) config('settings.language.default', 'es'));
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('name');
            $table->index('is_active');
            $table->index('accepts_join_requests');
            $table->index('requires_join_approval');
            $table->index('language');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
