<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\Participant;

class CapitalSnapshotPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('capital.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, CapitalSnapshot $snapshot): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('capital.view') || $user->is_super_admin;
        }

        return $user instanceof Participant;
    }
}
