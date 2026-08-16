<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageRoles(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage');
    }
}
