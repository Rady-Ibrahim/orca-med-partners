<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\MonthlyProfit;
use App\Models\Settlement;

final class FinancialImmutabilityGuard
{
    public function ensureMonthlyProfitMutable(MonthlyProfit $monthlyProfit): void
    {
        if ($monthlyProfit->status === 'approved') {
            throw new ImmutableFinancialRecordException('Approved monthly profit records are immutable. Create a new revision instead.');
        }
    }

    public function ensureSettlementMutable(Settlement $settlement): void
    {
        if (in_array($settlement->status, ['approved', 'paid', 'cancelled'], true)) {
            throw new ImmutableFinancialRecordException('Approved or paid settlement records are immutable. Create a new revision instead.');
        }
    }

    public function ensureSnapshotMutableForApprovedPeriod(?string $status): void
    {
        if ($status === 'final') {
            throw new ImmutableFinancialRecordException('Capital snapshots used by approved financial periods are immutable.');
        }
    }
}
