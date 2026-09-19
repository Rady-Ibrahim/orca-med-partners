<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Participant;

class AuditLogPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('audit_logs.view') || $user->is_super_admin);
    }

    public function view(Admin|Participant $user, AuditLog $log): bool
    {
        return $user instanceof Admin && ($user->hasPermission('audit_logs.view') || $user->is_super_admin);
    }
}
