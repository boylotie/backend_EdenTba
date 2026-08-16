<?php

namespace App\Modules\Content\Events;

use App\Modules\Content\Models\Content;

/**
 * Événement de domaine émis à chaque transition de statut d'un contenu
 * (US-025). Consommé notamment par les notifications (MOD-09).
 */
final class ContentStatusChanged
{
    public function __construct(
        public readonly Content $content,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
