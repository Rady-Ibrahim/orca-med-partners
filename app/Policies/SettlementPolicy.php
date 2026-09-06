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

        return $user instanceof Participant && $settlement->participantItems($user->id)->exists();
    }

    public function create(Admin $user): bool
    {
        return $user->hasPermission('settlements.create') || $user->is_super_admin;
    }

    public function approve(Admin $user): bool
    {
        return $user->hasPermission('settlements.approve') || $user->is_super_admin;
    }

    public function pay(Admin $user): bool
    {
        return $user->hasPermission('settlements.pay') || $user->is_super_admin;
    }

    public function revise(Admin $user): bool
    {
        return $user->hasPermission('settlements.update') || $user->is_super_admin;
    }

    public function cancel(Admin $user): bool
    {
        return $user->hasPermission('settlements.update') || $user->is_super_admin;
    }
}
