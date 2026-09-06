<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Collection;

final class ListAnnualSettlementsAction
{
    /** @return Collection<int, Settlement> */
    public function execute(): Collection
    {
        return Settlement::query()->with('items')->latest('year')->get();
    }
}
