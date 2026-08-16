<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Jobs\SendPushNotifications;

/**
 * Diffusion d'un message d'administration (US-040) : crée une notification
 * interne pour chaque utilisateur actif et planifie l'envoi push sur les
 * tokens des utilisateurs actifs. Utilisé par l'envoi immédiat (via job) et
 * par la commande des notifications programmées.
 */
class AdminBroadcastService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly DeviceTokenService $devices,
    ) {}

    /**
     * Retourne le nombre de notifications internes créées.
     *
     * @param  string  $type  Type de notification interne (défaut : message
     *                        d'administration). Les rappels (MOD-10) passent
     *                        leur propre type pour permettre un rendu mobile
     *                        distinct.
     */
    public function broadcast(string $title, ?string $body = null, string $type = NotificationService::TYPE_ADMIN_MESSAGE): int
    {
        $created = $this->notifications->createForAllActiveUsers(
            $type,
            $title,
            $body,
        );

        $tokens = $this->devices->tokensOfActiveUsers($type);

        if ($tokens !== []) {
            SendPushNotifications::dispatch($tokens, $title, $body, type: $type);
        }

        return $created;
    }
}
