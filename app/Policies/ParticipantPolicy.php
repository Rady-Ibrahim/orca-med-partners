<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Participant;

class ParticipantPolicy
{
    public function viewAny(Admin $user): bool
    {
        return $user->hasPermission('participants.view') || $user->is_super_admin;
    }

    public function view(Admin $user, Participant $participant): bool
    {
        return $user->hasPermission('participants.view') || $user->is_super_admin;
    }

    public function update(Admin $user, Participant $participant): bool
    {
        return $user->hasPermission('participants.update') || $user->is_super_admin;
    }

    public function manageOwnResource(Participant $user, Participant $participant): bool
    {
        return $user->id === $participant->id;
    }
}
