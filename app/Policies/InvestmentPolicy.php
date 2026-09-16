<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Investment;
use App\Models\Participant;

class InvestmentPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('investments.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, Investment $investment): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('investments.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $investment->participant_id === $user->id;
    }

    public function create(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('investments.create') || $user->is_super_admin;
        }

        return false;
    }

    public function approve(Admin|Participant $user, Investment $investment): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('investments.update') || $user->is_super_admin;
        }

        return false;
    }

    public function update(Admin|Participant $user, Investment $investment): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('investments.update') || $user->is_super_admin;
        }

        return false;
    }
}
