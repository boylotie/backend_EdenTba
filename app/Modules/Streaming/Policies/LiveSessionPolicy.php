<?php

namespace App\Modules\Streaming\Policies;

use App\Models\User;

class LiveSessionPolicy
{
    public function start(User $user): bool
    {
        return $user->hasPermission('streaming.start');
    }

    public function stop(User $user): bool
    {
        return $user->hasPermission('streaming.stop');
    }
}
