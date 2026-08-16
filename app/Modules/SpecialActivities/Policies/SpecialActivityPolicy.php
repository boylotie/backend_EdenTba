<?php

namespace App\Modules\SpecialActivities\Policies;

use App\Models\User;

class SpecialActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('special_activity.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('special_activity.manage');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('special_activity.manage');
    }
}
