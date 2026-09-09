<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Collection;

final class ListParticipantSettlementsAction
{
    /** @return Collection<int, Settlement> */
    public function execute(Participant $participant): Collection
    {
        return Settlement::query()
            ->whereHas('items', fn($query) => $query->where('participant_id', $participant->id))
            ->with(['items' => fn($query) => $query->where('participant_id', $participant->id), 'payments'])
            ->latest('year')
            ->get();
    }
}
