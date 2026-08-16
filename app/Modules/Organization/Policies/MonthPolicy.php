<?php

namespace App\Modules\Organization\Policies;

use App\Models\User;

class MonthPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('schedule.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('schedule.manage');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('schedule.manage');
    }
}
