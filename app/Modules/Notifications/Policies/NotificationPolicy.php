<?php

namespace App\Modules\Notifications\Policies;

use App\Models\User;

/**
 * Permissions d'envoi manuel de notifications (US-040) : envoi immédiat
 * (`notification.send`) et programmation (`notification.schedule`).
 */
class NotificationPolicy
{
    public function send(User $user): bool
    {
        return $user->hasPermission('notification.send');
    }

    public function schedule(User $user): bool
    {
        return $user->hasPermission('notification.schedule');
    }
}
