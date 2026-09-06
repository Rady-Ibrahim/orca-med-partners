<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class AdminPolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('participants.view') || $user->is_super_admin;
    }

    public function view(Admin $user, Admin $admin): bool
    {
        return $user->id === $admin->id || $user->hasPermission('participants.view') || $user->is_super_admin;
    }

    public function update(Admin $user, Admin $admin): bool
    {
        return $user->id === $admin->id || $user->hasPermission('participants.update') || $user->is_super_admin;
    }
}
