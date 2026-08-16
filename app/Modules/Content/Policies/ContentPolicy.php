<?php

namespace App\Modules\Content\Policies;

use App\Models\User;

class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('content.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('content.update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('content.delete');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.publish');
    }
}
