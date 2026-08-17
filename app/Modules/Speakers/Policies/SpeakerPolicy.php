<?php

namespace App\Modules\Speakers\Policies;

use App\Models\User;

class SpeakerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('speaker.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('speaker.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('speaker.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('speaker.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('speaker.delete');
    }
}
