<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('special_activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('place')->nullable();
            $table->string('theme')->nullable();
            $table->timestamps();

            $table->index(['special_activity_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_sessions');
    }
};
