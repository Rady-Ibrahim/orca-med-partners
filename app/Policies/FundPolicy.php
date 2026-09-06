<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Fund;
use App\Models\Participant;

class FundPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('funds.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, Fund $fund): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('funds.view') || $user->is_super_admin;
        }

        return $user instanceof Participant;
    }
}
