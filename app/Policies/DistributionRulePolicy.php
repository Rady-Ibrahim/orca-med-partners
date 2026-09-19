<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\DistributionRule;
use App\Models\Participant;

class DistributionRulePolicy
{
    public function create(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('distribution_rules.manage') || $user->is_super_admin);
    }

    public function viewAny(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('distribution_rules.view') || $user->is_super_admin);
    }

    public function view(Admin|Participant $user, DistributionRule $rule): bool
    {
        return $user instanceof Admin && ($user->hasPermission('distribution_rules.view') || $user->is_super_admin);
    }

    public function update(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('distribution_rules.manage') || $user->is_super_admin);
    }
}
