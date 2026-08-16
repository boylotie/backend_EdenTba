<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Suivi de la dernière visite (MOD-10-P2) : met à jour `users.last_seen_at`
 * à chaque connexion — référence du « rappel d'inactivité ».
 */
final class UpdateLastSeenAt
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->update(['last_seen_at' => now()]);
    }
}
