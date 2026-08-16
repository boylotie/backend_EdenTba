<?php

namespace App\Modules\Streaming\Events;

use App\Modules\Streaming\Models\LiveSession;

/**
 * Événement de domaine émis à l'arrêt d'une session de direct (US-046).
 * Consommé ultérieurement par la notification de fin (MOD-09).
 */
final class LiveStopped
{
    public function __construct(
        public readonly LiveSession $session,
    ) {}
}
