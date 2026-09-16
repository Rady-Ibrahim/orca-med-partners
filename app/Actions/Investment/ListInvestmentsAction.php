<?php

declare(strict_types=1);

namespace App\Actions\Investment;

use App\Models\Investment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListInvestmentsAction
{
    /** @return LengthAwarePaginator<int, Investment> */
    public function execute(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return Investment::query()
            ->with('participant')
            ->when($search, fn($query) => $query->whereHas('participant', fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->when($status, fn($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}