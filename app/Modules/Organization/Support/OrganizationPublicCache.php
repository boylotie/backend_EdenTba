<?php

namespace App\Modules\Organization\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version du cache public de navigation (années, mois, semaines, programmes) :
 * incrémentée à chaque écriture pour invalider les réponses publiques mises
 * en cache (MOD-12-P2).
 */
final class OrganizationPublicCache
{
    public const VERSION_KEY = 'public.organization.version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function invalidate(): void
    {
        Cache::increment(self::VERSION_KEY);
    }
}
