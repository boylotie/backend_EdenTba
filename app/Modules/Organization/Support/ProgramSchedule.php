<?php

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Models\Week;
use Illuminate\Support\Carbon;

final class ProgramSchedule
{
    /**
     * Vérifie si un créneau (jour, heure de début, durée) chevauche un
     * programme existant de la même semaine. L'identifiant optionnel
     * $ignoreProgramId exclut un programme (cas d'une mise à jour).
     */
    public static function hasOverlap(Week $week, ?int $ignoreProgramId, int $dayOfWeek, string $startTime, int $durationMinutes): bool
    {
        $newStart = Carbon::createFromFormat('H:i', $startTime);
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);

        $programs = $week->programs()
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreProgramId !== null, fn ($query) => $query->where('id', '!=', $ignoreProgramId))
            ->get();

        foreach ($programs as $program) {
            $start = Carbon::createFromFormat('H:i', $program->start_time);
            $end = $start->copy()->addMinutes($program->duration_minutes);

            if ($newStart->lt($end) && $start->lt($newEnd)) {
                return true;
            }
        }

        return false;
    }
}
