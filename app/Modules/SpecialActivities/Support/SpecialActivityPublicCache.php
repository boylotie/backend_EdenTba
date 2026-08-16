<?php

namespace App\Modules\SpecialActivities\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version du cache public des activités spéciales (liste et détail, y compris
 * les sessions et les types d'activité) : incrémentée à chaque écriture pour
 * invalider les réponses publiques mises en cache (MOD-12-P2).
 */
final class SpecialActivityPublicCache
{
    public const VERSION_KEY = 'public.special-activities.version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function invalidate(): void
    {
        Cache::increment(self::VERSION_KEY);
    }
}
