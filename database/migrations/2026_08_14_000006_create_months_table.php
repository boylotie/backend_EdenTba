<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('months', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->string('theme')->nullable();
            $table->timestamps();

            $table->unique(['year_id', 'month_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('months');
    }
};
