<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Fund;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListFundsAction
{
    /** @return LengthAwarePaginator<int, Fund> */
    public function execute(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Fund::query()
            ->withCount('transactions')
            ->when($search, fn($query) => $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }
}
