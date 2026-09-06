<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Models\Participant;

class MonthlyProfitPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('monthly_profits.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, MonthlyProfit $profit): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('monthly_profits.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $profit->participant_id === $user->id;
    }

    public function approve(Admin $user): bool
    {
        return $user->hasPermission('monthly_profits.approve') || $user->is_super_admin;
    }
}
