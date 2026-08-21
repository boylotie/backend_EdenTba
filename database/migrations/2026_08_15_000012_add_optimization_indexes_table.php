<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index d'optimisation (MOD-12-P2) : couvre l'ordre de tri des lectures
     * publiques (`ORDER BY day_of_week, start_time`) pour éviter les fichiers
     * de tri temporaires, et le filtrage « publié » des contenus.
     */
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            if (Schema::hasIndex('programs', 'programs_week_id_day_of_week_start_time_index')) {
                $table->dropIndex('programs_week_id_day_of_week_start_time_index');
            }
            if (!Schema::hasIndex('programs', 'programs_week_day_time_idx')) {
                $table->index(['week_id', 'day_of_week', 'start_time'], 'programs_week_day_time_idx');
            }
        });

        Schema::table('activity_sessions', function (Blueprint $table): void {
            $table->index(['special_activity_id', 'day_of_week', 'start_time'], 'activity_sess_idx');
        });

        Schema::table('contents', function (Blueprint $table): void {
            $table->index(['status', 'sort_order'], 'contents_status_sort_idx');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table): void {
            $table->dropIndex('programs_week_day_time_idx');
            $table->index(['week_id', 'day_of_week']);
        });

        Schema::table('activity_sessions', function (Blueprint $table): void {
            $table->dropIndex('activity_sess_idx');
            $table->index(['special_activity_id', 'day_of_week']);
        });

        Schema::table('contents', function (Blueprint $table): void {
            $table->dropIndex('contents_status_sort_idx');
        });
    }
};
