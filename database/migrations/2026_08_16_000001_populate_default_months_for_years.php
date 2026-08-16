<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Toute année possède désormais ses 12 mois structurels (janvier à
     * décembre). Les années créées avant ce changement reçoivent ici leurs
     * mois manquants.
     */
    public function up(): void
    {
        foreach (DB::table('years')->pluck('id') as $yearId) {
            $rows = [];

            foreach (range(1, 12) as $monthNumber) {
                $rows[] = [
                    'year_id' => $yearId,
                    'month_number' => $monthNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('months')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Migration de données : pas de retour arrière.
    }
};
