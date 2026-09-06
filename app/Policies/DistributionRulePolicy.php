<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\DistributionRule;

class DistributionRulePolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('distribution_rules.view') || $user->is_super_admin;
    }

    public function view(Admin $user, DistributionRule $rule): bool
    {
        return $user->hasPermission('distribution_rules.view') || $user->is_super_admin;
    }

    public function update(Admin $user): bool
    {
        return $user->hasPermission('distribution_rules.update') || $user->is_super_admin;
    }
}
