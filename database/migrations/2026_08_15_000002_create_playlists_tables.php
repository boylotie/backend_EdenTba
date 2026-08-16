<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('special_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('month_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('week_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('playlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['playlist_id', 'content_id']);
            $table->unique(['playlist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
    }
};
