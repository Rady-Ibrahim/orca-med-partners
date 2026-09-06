<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Domain\Financial\Services\FundBalanceService;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\Fund;
use App\Models\FundTransaction;
use Carbon\Carbon;

final class CreateFundTransactionAction
{
    public function __construct(private FundBalanceService $balanceService)
    {
    }

    /** @param array{transaction_type:string,amount:string,transaction_date?:string,reference?:string|null,description?:string|null,notes?:string|null,monthly_profit_id?:int|null} $attributes */
    public function execute(Admin $admin, Fund $fund, array $attributes): FundTransaction
    {
        return $this->balanceService->applyTransaction(
            $fund,
            $attributes['amount'],
            FundTransactionType::from($attributes['transaction_type']),
            $attributes['monthly_profit_id'] ?? null,
            $attributes['reference'] ?? null,
            $attributes['notes'] ?? null,
            $admin->id,
            Carbon::parse($attributes['transaction_date'] ?? now()->toDateString()),
            $attributes['description'] ?? null,
            $admin,
        );
    }
}