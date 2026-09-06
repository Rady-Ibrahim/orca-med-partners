<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('audit_logs.view') || $user->is_super_admin;
    }

    public function view(Admin $user, AuditLog $log): bool
    {
        return $user->hasPermission('audit_logs.view') || $user->is_super_admin;
    }
}
