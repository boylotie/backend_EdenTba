<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gestion des tokens d'appareil (US-039) : enregistrement (upsert par token,
 * le ré-enregistrement transfère la propriété au dernier utilisateur),
 * retrait, et liste des tokens des utilisateurs actifs pour la diffusion.
 */
final class DeviceTokenService
{
    public const PROVIDER_EXPO = 'expo';

    public function register(User $user, string $token, ?string $platform = null): UserDevice
    {
        $values = [
            'user_id' => $user->id,
            'provider' => self::PROVIDER_EXPO,
            'last_used_at' => now(),
        ];

        if ($platform !== null) {
            $values['platform'] = $platform;
        }

        return UserDevice::updateOrCreate(['token' => $token], $values);
    }

    public function removeForUser(User $user, string $token): bool
    {
        return $user->devices()->where('token', $token)->delete() > 0;
    }

    /**
     * Retire un token invalide/expiré détecté à l'envoi (scénario A1),
     * quel que soit son propriétaire.
     */
    public function removeByToken(string $token): void
    {
        UserDevice::query()->where('token', $token)->delete();
    }

    /**
     * Tokens des utilisateurs actifs uniquement (les comptes désactivés ne
     * reçoivent plus de push). Si un type est fourni, seuls les utilisateurs
     * qui ne l'ont pas désactivé dans leurs préférences sont ciblés
     * (MOD-09-P4) ; l'absence de préférence vaut « activé ».
     *
     * @return list<string>
     */
    public function tokensOfActiveUsers(?string $type = null): array
    {
        return array_values(UserDevice::query()
            ->whereHas('user', function (Builder $query) use ($type): void {
                $query->where('is_active', true);

                if ($type !== null) {
                    $query->whereDoesntHave('notificationPreferences', fn (Builder $preference): Builder => $preference
                        ->where('type', $type)
                        ->where('enabled', false));
                }
            })
            ->get(['token'])
            ->map(fn (UserDevice $device): string => $device->token)
            ->all());
    }

    /**
     * Tokens des utilisateurs ciblés (tous actifs, MOD-10-P2) : le rappel
     * d'inactivité ne pousse que vers les utilisateurs concernés.
     *
     * @param  list<int>  $userIds
     * @return list<string>
     */
    public function tokensOfUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return array_values(UserDevice::query()
            ->whereIn('user_id', $userIds)
            ->whereHas('user', fn (Builder $query): Builder => $query->where('is_active', true))
            ->get(['token'])
            ->map(fn (UserDevice $device): string => $device->token)
            ->all());
    }
}
