<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'occurrence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_reminders');
    }
};
