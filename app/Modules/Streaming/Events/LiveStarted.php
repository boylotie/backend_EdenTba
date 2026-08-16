<?php

namespace App\Modules\Streaming\Events;

use App\Modules\Streaming\Models\LiveSession;

/**
 * Événement de domaine émis au démarrage d'une session de direct (US-046).
 * Consommé ultérieurement par la notification de démarrage (MOD-09).
 */
final class LiveStarted
{
    public function __construct(
        public readonly LiveSession $session,
    ) {}
}
