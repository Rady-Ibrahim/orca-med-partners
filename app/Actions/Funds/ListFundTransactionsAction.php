<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Database\Eloquent\Collection;

final class ListFundTransactionsAction
{
    /** @return Collection<int, FundTransaction> */
    public function execute(Fund $fund): Collection
    {
        return FundTransaction::query()->where('fund_id', $fund->id)->latest('id')->get();
    }
}
