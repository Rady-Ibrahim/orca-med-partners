<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Domain\Financial\Services\DistributionRuleSnapshotService;
use App\Domain\Financial\Services\FinancialCalculationServiceContract;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Domain\Financial\ValueObjects\MonthlyProfitCalculationResult;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateMonthlyProfitAction
{
    public function __construct(
        private FinancialCalculationServiceContract $calculationService,
        private DistributionRuleSnapshotService $ruleSnapshotService,
        private FinancialRoundingService $rounding,
        private SecurityAuditService $audit,
    ) {}

    /** @param array<string, string>|null $ruleSnapshot */
    public function execute(Admin $admin, CapitalSnapshot $snapshot, DistributionRule $rule, string|int $grossProfit, int $year, int $month, ?int $parentId = null, ?array $ruleSnapshot = null): MonthlyProfit
    {
        return DB::transaction(function () use ($admin, $snapshot, $rule, $grossProfit, $year, $month, $parentId, $ruleSnapshot) {
            $snapshot = CapitalSnapshot::query()->lockForUpdate()->findOrFail($snapshot->id);
            $rule = DistributionRule::query()->lockForUpdate()->findOrFail($rule->id);
            $result = $this->calculationService->calculate(
                $grossProfit,
                $rule,
                $snapshot,
                $ruleSnapshot ?? $this->ruleSnapshotService->snapshot($rule),
            );

            $version = (int) MonthlyProfit::query()
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->max('version') + 1;

            $profit = MonthlyProfit::query()->create([
                'capital_snapshot_id' => $snapshot->id,
                'distribution_rule_id' => $rule->id,
                'distribution_rule_snapshot' => $result->ruleSnapshot,
                'parent_id' => $parentId,
                'year' => $year,
                'month' => $month,
                'version' => $version,
                'status' => 'draft',
                'gross_profit' => $result->grossProfit,
                'management_amount' => $result->managementAmount,
                'depreciation_amount' => $result->depreciationAmount,
                'growth_amount' => $result->growthAmount,
                'incentive_amount' => $result->incentiveAmount,
                'distributed_amount' => $result->distributedPool,
                'rounding_delta_adjustment' => $result->roundingDelta,
                'created_by_admin_id' => $admin->id,
            ]);

            foreach ($result->participantAllocations as $allocation) {
                ParticipantProfitAllocation::query()->create([
                    'monthly_profit_id' => $profit->id,
                    'participant_id' => $allocation['participant_id'],
                    'amount' => $allocation['amount'],
                    'share_ratio' => $allocation['share_ratio'],
                    'status' => 'approved',
                ]);
            }

            $this->wireFundComponents($profit, $result, $snapshot);

            $this->audit->log('monthly_profit_created', $admin, 'monthly_profit', $profit->id, [
                'year' => $year,
                'month' => $month,
                'version' => $version,
            ]);

            return $profit->load('allocations');
        });
    }

private function wireFundComponents(MonthlyProfit $profit, MonthlyProfitCalculationResult $result, CapitalSnapshot $snapshot): void
    {
        $funds = [
            'growth' => ['fund' => $this->fundByCode('growth_fund'), 'amount' => $result->growthAmount],
            'incentive' => ['fund' => $this->fundByCode('incentive_fund'), 'amount' => $result->incentiveAmount],
        ];

        foreach ($funds as $type => $config) {
            $fund = $config['fund'];
            $amount = $config['amount'];

            if (! $fund || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $shares = array_map(
                static fn (array $allocation): array => [
                    'participant_id' => $allocation['participant_id'],
                    'amount' => bcmul($amount, $allocation['share_ratio'], 8),
                ],
                $result->participantAllocations,
            );

            foreach ($this->exactAmounts($amount, $shares) as $share) {
                ParticipantFundAllocation::query()->create([
                    'fund_id' => $fund->id,
                    'monthly_profit_id' => $profit->id,
                    'participant_id' => $share['participant_id'],
                    'amount' => $share['amount'],
                    'allocation_type' => $type,
                ]);
            }
        }

        $depreciationFund = $this->fundByCode('depreciation_fund');
        if ($depreciationFund && bccomp($result->depreciationAmount, '0', 2) > 0) {
            DepreciationNote::query()->create([
                'participant_id' => null,
                'fund_id' => $depreciationFund->id,
                'monthly_profit_id' => $profit->id,
                'amount' => $result->depreciationAmount,
                'rate' => $result->ruleSnapshot['depreciation_fund_rate'] ?? '0.0500',
                'transaction_date' => $snapshot->snapshot_date ?? now()->toDateString(),
                'year' => $profit->year,
                'month' => $profit->month,
                'description' => "مخصص إهلاك شهر {$profit->month}/{$profit->year}",
                'admin_note' => 'تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',
                'created_by_admin_id' => $profit->created_by_admin_id,
            ]);
        }
    }

    private function fundByCode(string $code): ?Fund
    {
        return Fund::resolveSystemFund($code);
    }

    /**
     * Rounds raw per-participant amounts and redistributes the residual so the
     * stored shares sum to exactly the assigned fund amount.
     *
     * @param array<int, array{participant_id:int, amount:string}> $shares
     * @return array<int, array{participant_id:int, amount:string}>
     */
    private function exactAmounts(string $total, array $shares): array
    {
        $normalized = [];
        $sum = '0.00';
        foreach ($shares as $share) {
            $amount = $this->rounding->money($share['amount']);
            $sum = bcadd($sum, $amount, 2);
            $normalized[] = ['participant_id' => $share['participant_id'], 'amount' => $amount];
        }

        $delta = bcsub($total, $sum, 2);
        if (bccomp($delta, '0', 2) === 0 || $normalized === []) {
            return $normalized;
        }

        $indexes = array_keys($normalized);
        usort(
            $indexes,
            static fn (int $a, int $b): int => bccomp($normalized[$b]['amount'], $normalized[$a]['amount'], 2),
        );

        $remaining = $delta;
        $cursor = 0;
        while (bccomp($remaining, '0', 2) !== 0 && $cursor < count($indexes)) {
            $index = $indexes[$cursor];
            $candidate = $this->rounding->money(bcadd($normalized[$index]['amount'], $remaining, 2));

            if (bccomp($candidate, '0', 2) < 0) {
                $normalized[$index]['amount'] = '0.00';
                $remaining = $candidate;
                $cursor++;
                continue;
            }

            $normalized[$index]['amount'] = $candidate;
            $remaining = '0.00';
        }

        return $normalized;
    }
}
