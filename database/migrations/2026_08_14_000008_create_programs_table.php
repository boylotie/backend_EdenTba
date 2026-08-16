<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('type');
            $table->timestamps();

            $table->index(['week_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
