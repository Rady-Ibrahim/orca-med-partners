<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Models\Settlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAnnualSettlementsAction
{
    /** @return LengthAwarePaginator<int, Settlement> */
    public function execute(?int $year = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Settlement::query()
            ->with('items')
            ->when($year, fn($query) => $query->where('year', $year))
            ->when($status, fn($query) => $query->where('status', $status))
            ->latest('year')
            ->paginate($perPage)
            ->withQueryString();
    }
}