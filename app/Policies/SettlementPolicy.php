<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\Settlement;

class SettlementPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('settlements.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, Settlement $settlement): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('settlements.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $settlement->participant_id === $user->id;
    }

    public function approve(Admin $user): bool
    {
        return $user->hasPermission('settlements.approve') || $user->is_super_admin;
    }
}
