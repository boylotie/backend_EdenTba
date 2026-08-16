<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'content_id']);
        });

        Schema::create('listening_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listening_histories');
        Schema::dropIfExists('favorites');
    }
};
