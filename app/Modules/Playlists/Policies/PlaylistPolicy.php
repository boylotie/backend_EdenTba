<?php

namespace App\Modules\Playlists\Policies;

use App\Models\User;

class PlaylistPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('playlist.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('playlist.manage');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('playlist.manage');
    }
}
