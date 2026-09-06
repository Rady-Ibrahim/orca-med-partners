<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Fund;
use Illuminate\Database\Eloquent\Collection;

final class ListFundsAction
{
    /** @return Collection<int, Fund> */
    public function execute(): Collection
    {
        return Fund::query()->withCount('transactions')->orderBy('code')->get();
    }
}
