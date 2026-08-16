<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Content\Models\Content;
use App\Modules\Notifications\Jobs\SendPushNotifications;

/**
 * Déclenchement des push pour les événements métier. Les destinataires sont
 * les utilisateurs actifs disposant d'un token d'appareil ; sans token,
 * aucun job n'est planifié.
 */
final class PushNotificationService
{
    public function __construct(private readonly DeviceTokenService $devices) {}

    public function dispatchForContentPublished(Content $content): void
    {
        $tokens = $this->devices->tokensOfActiveUsers(NotificationService::TYPE_CONTENT_PUBLISHED);

        if ($tokens === []) {
            return;
        }

        SendPushNotifications::dispatch(
            $tokens,
            $content->title,
            $content->description,
            NotificationService::ENTITY_CONTENT,
            $content->id,
            NotificationService::TYPE_CONTENT_PUBLISHED,
        );
    }
}
