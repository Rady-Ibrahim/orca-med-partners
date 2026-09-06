<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\DepreciationNote;
use App\Models\Participant;

class DepreciationNotePolicy
{
    public function viewAny(Admin|Participant $user): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('depreciation.view') || $user->is_super_admin;
        }

        return true;
    }

    public function view(Admin|Participant $user, DepreciationNote $note): bool
    {
        if ($user instanceof Admin) {
            return $user->hasPermission('depreciation.view') || $user->is_super_admin;
        }

        return $user instanceof Participant && $note->participant_id === $user->id;
    }
}
