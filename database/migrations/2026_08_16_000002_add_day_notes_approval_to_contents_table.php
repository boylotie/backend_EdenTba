<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->unsignedTinyInteger('day_of_week')->nullable()->after('special_activity_id');
            $table->text('notes')->nullable()->after('day_of_week');
            $table->string('approved_by')->nullable()->after('notes');
            $table->text('approval_comment')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('approval_comment');

            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            $table->dropIndex(['day_of_week']);
            $table->dropColumn(['day_of_week', 'notes', 'approved_by', 'approval_comment', 'approved_at']);
        });
    }
};
