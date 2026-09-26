<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Domain\Financial\ValueObjects\MonthlyProfitCalculationResult;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DistributionRule;

final class FinancialCalculationService implements FinancialCalculationServiceContract
{
    public function __construct(
        private FinancialRoundingService $rounding,
        private CapitalCalculatorService $capital,
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
        $capitals = [];
        foreach ($items as $item) {
            $capital = (string) $item->participant_capital_snapshot;
            if (bccomp($capital, '0', 2) < 0) {
                throw new InvalidCapitalSnapshotException('Capital snapshot values cannot be negative.');
            }
            $capitals[(int) $item->participant_id] = $capital;
            $totalCapital = bcadd($totalCapital, $capital, 2);
        }

        if (bccomp($totalCapital, '0.00', 2) === 0) {
            throw new InvalidCapitalSnapshotException('Cannot allocate profit when total participant capital is zero.');
        }

        $amount = fn(string $rate): string => $this->rounding->money(bcmul($grossProfit, $rate, 8));
        $distributedPool = $amount($ruleSnapshot['distributed_share_rate']);
        $allocations = $this->allocateExactly($distributedPool, $items, $capitals);

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
            roundedAllocationsTotal: $distributedPool,
            roundingDelta: '0.00',
        );
    }

    /**
     * Allocates the pool across participants so that the stored ratios sum to
     * exactly 1.0000 and the individual rounded amounts sum to exactly the pool.
     *
     * Ratios come from {@see CapitalCalculatorService::recalculateRatios()} so a
     * participant's ownership ratio on the capital page is byte-identical to the
     * share ratio used when their profit is distributed.
     *
     * @param  iterable<CapitalSnapshotItem>  $items
     * @param  array<int, string>  $capitals
     * @return array<int, array{participant_id:int, amount:string, share_ratio:string}>
     */
    private function allocateExactly(string $distributedPool, iterable $items, array $capitals): array
    {
        $ratios = $this->capital->recalculateRatios($capitals);

        $allocations = [];
        foreach ($items as $item) {
            $ratio = $ratios[(int) $item->participant_id];
            $allocations[] = [
                'participant_id' => (int) $item->participant_id,
                'amount' => $this->rounding->money(bcmul($distributedPool, $ratio, 8)),
                'share_ratio' => $ratio,
            ];
        }

        return $this->applyAmountDelta($distributedPool, $allocations);
    }

    /** @param array<int, array{participant_id:int, amount:string, share_ratio:string}> $allocations */
    private function applyAmountDelta(string $pool, array $allocations): array
    {
        if ($allocations === []) {
            return $allocations;
        }

        $total = '0.00';
        foreach ($allocations as $allocation) {
            $total = bcadd($total, $allocation['amount'], 2);
        }

        $delta = bcsub($pool, $total, 2);
        if (bccomp($delta, '0', 2) === 0) {
            return $allocations;
        }

        $byAmount = $allocations;
        uasort($byAmount, static fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));
        $indexes = array_keys($byAmount);

        $remaining = $delta;
        $cursor = 0;
        while (bccomp($remaining, '0', 2) !== 0 && $cursor < count($indexes)) {
            $index = $indexes[$cursor];
            $candidate = $this->rounding->money(bcadd($allocations[$index]['amount'], $remaining, 2));

            if (bccomp($candidate, '0', 2) < 0) {
                $allocations[$index]['amount'] = '0.00';
                $remaining = $candidate;
                $cursor++;
                continue;
            }

            $allocations[$index]['amount'] = $candidate;
            $remaining = '0.00';
        }

        return $allocations;
    }
}
