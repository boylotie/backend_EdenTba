<?php

namespace App\Modules\Analytics\Policies;

use App\Models\User;

class StatisticsReportPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('statistics.view');
    }
}
