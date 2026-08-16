<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('image_path')->nullable();
            $table->string('speaker')->nullable();
            $table->foreignId('year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('month_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('week_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('special_activity_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('year_id');
            $table->index('month_id');
            $table->index('week_id');
            $table->index('special_activity_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
