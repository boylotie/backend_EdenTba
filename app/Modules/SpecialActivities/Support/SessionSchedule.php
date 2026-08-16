<?php

namespace App\Modules\SpecialActivities\Support;

use App\Modules\SpecialActivities\Models\SpecialActivity;
use Illuminate\Support\Carbon;

final class SessionSchedule
{
    /**
     * Vérifie si un créneau (jour, heure de début, durée) chevauche une
     * session existante de la même activité. L'identifiant optionnel
     * $ignoreSessionId exclut une session (cas d'une mise à jour).
     */
    public static function hasOverlap(SpecialActivity $activity, ?int $ignoreSessionId, int $dayOfWeek, string $startTime, int $durationMinutes): bool
    {
        $newStart = Carbon::createFromFormat('H:i', $startTime);
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);

        $sessions = $activity->sessions()
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreSessionId !== null, fn ($query) => $query->where('id', '!=', $ignoreSessionId))
            ->get();

        foreach ($sessions as $session) {
            $start = Carbon::createFromFormat('H:i', $session->start_time);
            $end = $start->copy()->addMinutes($session->duration_minutes);

            if ($newStart->lt($end) && $start->lt($newEnd)) {
                return true;
            }
        }

        return false;
    }
}
