<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Models\Participant;

class MonthlyProfitPolicy
{
    public function create(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('profits.create') || $user->is_super_admin);
    }

    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('profits.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, MonthlyProfit $profit): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('profits.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $profit->allocations()->where('participant_id', $user->id)->exists();
    }

    public function approve(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('profits.approve') || $user->is_super_admin);
    }
}
