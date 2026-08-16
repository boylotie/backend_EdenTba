<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->enum('mode', ['replace', 'complement'])->default('complement');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_activities');
    }
};
