<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Participant;

class ParticipantPolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        return $user instanceof Admin && ($user->hasPermission('participants.view') || $user->is_super_admin);
    }

    public function view(Admin|Participant $user, Participant $participant): bool
    {
        return $user instanceof Admin && ($user->hasPermission('participants.view') || $user->is_super_admin);
    }

    public function update(Admin|Participant $user, Participant $participant): bool
    {
        return $user instanceof Admin && ($user->hasPermission('participants.update') || $user->is_super_admin);
    }

    public function manageOwnResource(Admin|Participant $user, Participant $participant): bool
    {
        return $user instanceof Participant && $user->id === $participant->id;
    }
}
