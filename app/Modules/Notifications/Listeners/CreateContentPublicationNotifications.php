<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Content\Events\ContentStatusChanged;
use App\Modules\Content\Models\Content;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\PushNotificationService;

/**
 * À la publication d'un contenu (transition vers `published`), crée la
 * notification interne « contenu publié » pour les utilisateurs actifs
 * (MOD-09-P1) et planifie l'envoi push (MOD-09-P2). Idempotent : une même
 * ressource ne génère qu'une notification par utilisateur.
 */
final class CreateContentPublicationNotifications
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PushNotificationService $push,
    ) {}

    public function handle(ContentStatusChanged $event): void
    {
        if ($event->to !== Content::STATUS_PUBLISHED) {
            return;
        }

        $this->notifications->createForContentPublished($event->content);
        $this->push->dispatchForContentPublished($event->content);
    }
}
