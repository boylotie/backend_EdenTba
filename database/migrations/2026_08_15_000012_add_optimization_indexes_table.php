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
        });

        if (Schema::hasIndex('programs', 'programs_week_id_day_of_week_index')) {
            $fkName = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_NAME = 'program_reminders'
                 AND REFERENCED_TABLE_NAME = 'programs'
                 AND REFERENCED_COLUMN_NAME = 'id'
                 LIMIT 1"
            );
            if ($fkName) {
                DB::statement("ALTER TABLE program_reminders DROP FOREIGN KEY `{$fkName->CONSTRAINT_NAME}`");
            }
            DB::statement('ALTER TABLE programs DROP INDEX programs_week_id_day_of_week_index');
            if ($fkName) {
                DB::statement("ALTER TABLE program_reminders ADD CONSTRAINT `{$fkName->CONSTRAINT_NAME}` FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE");
            }
        }

        Schema::table('programs', function (Blueprint $table): void {
            $table->index(['week_id', 'day_of_week', 'start_time'], 'programs_week_day_time_idx');
        });

        Schema::table('activity_sessions', function (Blueprint $table): void {
            $table->index(['special_activity_id', 'day_of_week', 'start_time'], 'activity_sess_idx');
        });

        Schema::table('contents', function (Blueprint $table): void {
            $table->index(['status', 'sort_order'], 'contents_status_sort_idx');
        });
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
