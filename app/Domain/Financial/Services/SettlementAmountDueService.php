<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;

final class SettlementAmountDueService
{
    public function __construct(private FinancialRoundingService $rounding) {}

    /** @return array{profit:string,fund_share:string,previous_payments:string,amount_due:string} */
    public function calculate(string|int $profit, string|int $fundShare, string|int $previousPayments): array
    {
        $profit = $this->rounding->money((string) $profit);
        $fundShare = $this->rounding->money((string) $fundShare);
        $previousPayments = $this->rounding->money((string) $previousPayments);
        $grossDue = bcadd($profit, $fundShare, 2);
        $amountDue = bcsub($grossDue, $previousPayments, 2);

        if (bccomp($amountDue, '0.00', 2) < 0) {
            $amountDue = '0.00';
        }

        return compact('profit', 'fundShare', 'previousPayments') + ['amount_due' => $amountDue];
    }
}
