<?php

declare(strict_types=1);

namespace App\Actions\Investment;

use App\Models\Investment;
use Illuminate\Database\Eloquent\Collection;

final class ListInvestmentsAction
{
    /** @return Collection<int, Investment> */
    public function execute(): Collection
    {
        return Investment::query()->with('participant')->get();
    }
}
