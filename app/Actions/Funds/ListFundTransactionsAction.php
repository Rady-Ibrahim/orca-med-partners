<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListFundTransactionsAction
{
    /** @return LengthAwarePaginator<int, FundTransaction> */
    public function execute(Fund $fund, ?string $type = null, int $perPage = 15): LengthAwarePaginator
    {
        return FundTransaction::query()
            ->where('fund_id', $fund->id)
            ->when($type, fn($query) => $query->where('transaction_type', $type))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}