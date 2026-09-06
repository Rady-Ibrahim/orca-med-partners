<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Domain\Financial\Services\DistributionRuleSnapshotService;
use App\Domain\Financial\Services\FinancialCalculationServiceContract;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use App\Models\ParticipantProfitAllocation;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateMonthlyProfitAction
{
    public function __construct(
        private FinancialCalculationServiceContract $calculationService,
        private DistributionRuleSnapshotService $ruleSnapshotService,
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

            $this->audit->log('monthly_profit_created', $admin, 'monthly_profit', $profit->id, [
                'year' => $year,
                'month' => $month,
                'version' => $version,
            ]);

            return $profit->load('allocations');
        });
    }
}
