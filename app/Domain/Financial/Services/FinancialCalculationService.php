<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Domain\Financial\ValueObjects\MonthlyProfitCalculationResult;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;

final class FinancialCalculationService implements FinancialCalculationServiceContract
{
    public function __construct(
        private FinancialRoundingService $rounding,
    ) {}

    /** @param array<string, string>|null $ruleSnapshot */
    public function calculate(string|int $grossProfit, DistributionRule $rule, CapitalSnapshot $snapshot, ?array $ruleSnapshot = null): MonthlyProfitCalculationResult
    {
        $grossProfit = (string) $grossProfit;
        if (! preg_match('/^\d+(?:\.\d+)?$/', $grossProfit) || bccomp($grossProfit, '0', 8) < 0) {
            throw new InvalidGrossProfitException('[NEEDS BUSINESS DECISION] Negative profit policy is not defined; calculation requires non-negative gross profit.');
        }

        $ruleSnapshot ??= [
            'management_fee_rate' => $this->rounding->rate((string) $rule->management_fee_rate),
            'depreciation_fund_rate' => $this->rounding->rate((string) $rule->depreciation_fund_rate),
            'growth_fund_rate' => $this->rounding->rate((string) $rule->growth_fund_rate),
            'incentive_fund_rate' => $this->rounding->rate((string) $rule->incentive_fund_rate),
            'distributed_share_rate' => $this->rounding->rate((string) $rule->distributed_share_rate),
        ];

        $items = $snapshot->items()->lockForUpdate()->get();
        $totalCapital = '0';
        foreach ($items as $item) {
            $capital = (string) $item->participant_capital_snapshot;
            if (bccomp($capital, '0', 2) < 0) {
                throw new InvalidCapitalSnapshotException('Capital snapshot values cannot be negative.');
            }
            $totalCapital = bcadd($totalCapital, $capital, 2);
        }

        if (bccomp($totalCapital, '0.00', 2) === 0) {
            throw new InvalidCapitalSnapshotException('Cannot allocate profit when total participant capital is zero.');
        }

        $amount = fn(string $rate): string => $this->rounding->money(bcmul($grossProfit, $rate, 8));
        $distributedPool = $amount($ruleSnapshot['distributed_share_rate']);
        $allocations = [];
        $roundedTotal = '0.00';

        foreach ($items as $item) {
            $ratio = $this->rounding->rate(bcdiv((string) $item->participant_capital_snapshot, $totalCapital, 8));
            $allocation = $this->rounding->money(bcmul($distributedPool, $ratio, 8));
            $roundedTotal = bcadd($roundedTotal, $allocation, 2);
            $allocations[] = [
                'participant_id' => (int) $item->participant_id,
                'amount' => $allocation,
                'share_ratio' => $ratio,
            ];
        }

        return new MonthlyProfitCalculationResult(
            grossProfit: $this->rounding->money($grossProfit),
            ruleSnapshot: $ruleSnapshot,
            managementAmount: $amount($ruleSnapshot['management_fee_rate']),
            depreciationAmount: $amount($ruleSnapshot['depreciation_fund_rate']),
            growthAmount: $amount($ruleSnapshot['growth_fund_rate']),
            incentiveAmount: $amount($ruleSnapshot['incentive_fund_rate']),
            distributedPool: $distributedPool,
            totalParticipantCapital: $this->rounding->money($totalCapital),
            participantAllocations: $allocations,
            roundedAllocationsTotal: $roundedTotal,
            roundingDelta: bcsub($distributedPool, $roundedTotal, 2),
        );
    }
}
