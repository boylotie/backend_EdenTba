<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weeks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('year_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->timestamps();

            $table->unique(['year_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weeks');
    }
};
