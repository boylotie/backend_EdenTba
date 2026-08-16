<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Models\UserNotificationPreference;

/**
 * Préférences de notification par type (MOD-09-P4, US-041) : lecture et
 * remplacement complet. L'absence de préférence vaut « activé » — les
 * nouveaux comptes reçoivent tout par défaut, sans ligne en base.
 */
final class NotificationPreferenceService
{
    /**
     * Préférences de l'utilisateur pour tous les types connus, activées par
     * défaut.
     *
     * @return array<string, bool>
     */
    public function allFor(User $user): array
    {
        $stored = $user->notificationPreferences()->pluck('enabled', 'type')->all();

        $preferences = [];
        foreach (NotificationService::types() as $type) {
            $preferences[$type] = $stored[$type] ?? true;
        }

        return $preferences;
    }

    /**
     * Remplace les préférences (sémantique de remplacement complet, un type
     * connu par clé). Les types inconnus sont ignorés par sécurité.
     *
     * @param  array<string, bool>  $values
     */
    public function replaceFor(User $user, array $values): void
    {
        foreach ($values as $type => $enabled) {
            if (! NotificationService::isKnownType((string) $type)) {
                continue;
            }

            $user->notificationPreferences()->updateOrCreate(
                ['type' => $type],
                ['enabled' => (bool) $enabled],
            );
        }
    }

    /**
     * Une préférence explicitement désactivée exclut ; sinon, activé.
     */
    public function isEnabled(int $userId, string $type): bool
    {
        $preference = UserNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();

        return $preference->enabled ?? true;
    }
}
