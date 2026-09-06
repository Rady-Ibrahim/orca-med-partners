<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Participant;

class NotificationPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        return $user instanceof Admin ? ($user->hasPermission('participants.view') || $user->is_super_admin) : true;
    }

    public function view(Admin|Participant $user, Notification $notification): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('participants.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $notification->participant_id === $user->id;
    }
}
