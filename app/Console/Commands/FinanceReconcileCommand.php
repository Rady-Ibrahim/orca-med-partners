<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Financial\Services\CapitalCalculatorService;
use App\Domain\Financial\Services\FinancialCalculationServiceContract;
use App\Domain\Financial\Services\FundBalanceService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Services\SecurityAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class FinanceReconcileCommand extends Command
{
    protected $signature = 'finance:reconcile
        {--year= : Restrict repairs to a specific year}
        {--force : Also overwrite stored monthly profit header amounts that disagree with the recomputed split, instead of only reporting the mismatch}';

    protected $description = 'Repairs capital snapshot ownership ratios, recomputes profit/fund allocations to exact sums, backfills missing fund deposits and depreciation notes for approved profits, and regenerates stale draft annual settlements.';

    public function __construct(
        private FinancialCalculationServiceContract $calculationService,
        private FinancialRoundingService $rounding,
        private FundBalanceService $funds,
        private SecurityAuditService $audit,
        private CapitalCalculatorService $capital,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $actor = Admin::query()->where('is_super_admin', true)->orderBy('id')->first()
            ?? Admin::query()->orderBy('id')->first();

        if ($actor === null) {
            $this->error('No admin actor available for audit logging.');

            return self::FAILURE;
        }

        $profits = MonthlyProfit::query()
            ->where('status', 'approved')
            ->whereNotNull('capital_snapshot_id')
            ->whereNotNull('distribution_rule_id')
            ->when($year !== null, fn ($query) => $query->where('year', $year))
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('version')
            ->get();

        $summary = [
            'profits' => count($profits),
            'snapshots_repaired' => 0,
            'allocations_recomputed' => 0,
            'fund_allocations_rebuilt' => 0,
            'deposits_created' => 0,
            'depreciation_notes_created' => 0,
            'settlements_regenerated' => [],
            'mismatches' => [],
        ];

        DB::transaction(function () use ($profits, $actor, &$summary): void {
            $summary['snapshots_repaired'] = $this->repairSnapshotRatios($profits, $actor);

            foreach ($profits as $profit) {
                if ($profit->parent_id !== null || $profit->version > 1) {
                    continue;
                }

                try {
                    $snapshot = $profit->capitalSnapshot()->lockForUpdate()->firstOrFail();
                    $rule = $profit->distributionRule()->lockForUpdate()->firstOrFail();

                    $result = $this->calculationService->calculate(
                        (string) $profit->gross_profit,
                        $rule,
                        $snapshot,
                        $profit->distribution_rule_snapshot,
                    );

                    $amountMismatch = [];
                    $storedKeyToResult = [
                        'management_amount' => 'managementAmount',
                        'depreciation_amount' => 'depreciationAmount',
                        'growth_amount' => 'growthAmount',
                        'incentive_amount' => 'incentiveAmount',
                        'distributed_amount' => 'distributedPool',
                    ];
                    foreach ($storedKeyToResult as $storedKey => $resultKey) {
                        if (bccomp((string) $profit->{$storedKey}, (string) $result->{$resultKey}, 2) !== 0) {
                            $amountMismatch[] = $storedKey;
                        }
                    }
                    if ($amountMismatch !== []) {
                        $summary['mismatches'][] = sprintf(
                            'PROFIT-%d (%04d/%02d): stored amounts differ from recomputed amounts for %s',
                            $profit->id,
                            $profit->year,
                            $profit->month,
                            implode(', ', $amountMismatch),
                        );
                    }

                    $repaired = [
                        'rounding_delta_adjustment' => $result->roundingDelta,
                    ];

                    if ($this->option('force')) {
                        foreach ($storedKeyToResult as $storedKey => $resultKey) {
                            $repaired[$storedKey] = $result->{$resultKey};
                        }
                    }

                    $profit->forceFill($repaired)->saveQuietly();

                    $this->rebuildProfitAllocations($profit, $result->participantAllocations);
                    $summary['allocations_recomputed']++;

                    $this->rebuildFundAllocations($profit, $result->growthAmount, $result->incentiveAmount, $result->ruleSnapshot);
                    $summary['fund_allocations_rebuilt']++;

                    $summary['deposits_created'] += $this->ensureFundDeposits($profit, $actor);
                    $summary['depreciation_notes_created'] += $this->ensureDepreciationNote($profit, $result->depreciationAmount, $actor);
                } catch (Throwable $exception) {
                    $summary['mismatches'][] = sprintf('PROFIT-%d: %s', $profit->id, $exception->getMessage());
                }
            }

            foreach (Fund::query()->get() as $fund) {
                $this->funds->recalculateRunningBalances($fund, $actor);
            }

            $years = array_values(
                array_unique(
                    array_map(
                        static fn (MonthlyProfit $profit): int => $profit->year,
                        $profits->all(),
                    ),
                ),
            );

            foreach ($years as $yearToRegenerate) {
                $regenerated = $this->regenerateDraftSettlement($yearToRegenerate, $actor);
                if ($regenerated !== null) {
                    $summary['settlements_regenerated'][] = $regenerated;
                }
            }
        });

        foreach ($summary['mismatches'] as $mismatch) {
            $this->warn($mismatch);
        }

        $this->info(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * Repairs ownership ratios on every snapshot the target profits depend on.
     *
     * Without this the command would faithfully re-derive downstream numbers
     * from ratios that were themselves wrong, which is exactly how a stale
     * snapshot kept reproducing a zero share for a funded partner.
     *
     * @param  Collection<int, MonthlyProfit>  $profits
     */
    private function repairSnapshotRatios(iterable $profits, Admin $actor): int
    {
        $snapshotIds = [];
        foreach ($profits as $profit) {
            if ($profit->parent_id === null && $profit->version === 1 && $profit->capital_snapshot_id !== null) {
                $snapshotIds[(int) $profit->capital_snapshot_id] = true;
            }
        }

        $repaired = 0;
        foreach (array_keys($snapshotIds) as $snapshotId) {
            $snapshot = CapitalSnapshot::query()->lockForUpdate()->find($snapshotId);
            if ($snapshot === null) {
                continue;
            }

            $result = $this->capital->recalculateSnapshot($snapshot);
            if (! $result['changed']) {
                continue;
            }

            $repaired++;
            $this->audit->log('finance_reconcile_capital_ratios_repaired', $actor, 'capital_snapshot', $snapshot->id, [
                'total_capital' => $result['total_capital'],
                'ratios_updated' => $result['updated'],
            ]);
        }

        return $repaired;
    }

    /** @param array<int, array{participant_id:int, amount:string, share_ratio:string}> $allocations */
    private function rebuildProfitAllocations(MonthlyProfit $profit, array $allocations): void
    {
        ParticipantProfitAllocation::query()
            ->where('monthly_profit_id', $profit->id)
            ->delete();

        foreach ($allocations as $allocation) {
            ParticipantProfitAllocation::query()->create([
                'monthly_profit_id' => $profit->id,
                'participant_id' => $allocation['participant_id'],
                'amount' => $allocation['amount'],
                'share_ratio' => $allocation['share_ratio'],
                'status' => 'approved',
            ]);
        }
    }

    /** @param array<string, string> $ruleSnapshot */
    private function rebuildFundAllocations(MonthlyProfit $profit, string $growthAmount, string $incentiveAmount, array $ruleSnapshot): void
    {
        ParticipantFundAllocation::query()
            ->where('monthly_profit_id', $profit->id)
            ->whereIn('allocation_type', ['growth', 'incentive'])
            ->delete();

        $shares = ParticipantProfitAllocation::query()
            ->where('monthly_profit_id', $profit->id)
            ->orderBy('participant_id')
            ->get(['participant_id', 'share_ratio']);

        $types = [
            'growth' => [$this->resolveFund('growth_fund'), $growthAmount],
            'incentive' => [$this->resolveFund('incentive_fund'), $incentiveAmount],
        ];

        foreach ($types as $type => [$fund, $amount]) {
            if ($fund === null || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $entries = [];
            foreach ($shares as $share) {
                $entries[] = [
                    'participant_id' => (int) $share->participant_id,
                    'amount' => bcmul($amount, (string) $share->share_ratio, 8),
                ];
            }

            foreach ($this->exactAmounts($amount, $entries) as $entry) {
                ParticipantFundAllocation::query()->create([
                    'fund_id' => $fund->id,
                    'monthly_profit_id' => $profit->id,
                    'participant_id' => $entry['participant_id'],
                    'amount' => $entry['amount'],
                    'allocation_type' => $type,
                ]);
            }
        }

        unset($shares, $ruleSnapshot);
    }

    private function ensureFundDeposits(MonthlyProfit $profit, Admin $actor): int
    {
        $createdCount = 0;
        $entries = [
            'management_fund' => (string) $profit->management_amount,
            'growth_fund' => (string) $profit->growth_amount,
            'incentive_fund' => (string) $profit->incentive_amount,
            'depreciation_fund' => (string) $profit->depreciation_amount,
        ];

        foreach ($entries as $canonicalCode => $amount) {
            $fund = $this->resolveFund($canonicalCode);

            if ($fund === null || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $alreadyDeposited = DB::table('fund_transactions')
                ->where('fund_id', $fund->id)
                ->where('monthly_profit_id', $profit->id)
                ->where('transaction_type', FundTransactionType::DEPOSIT->value)
                ->exists();

            if ($alreadyDeposited) {
                continue;
            }

            $this->funds->applyTransaction(
                fund: $fund,
                amount: $amount,
                transactionType: FundTransactionType::DEPOSIT,
                monthlyProfitId: $profit->id,
                reference: "PROFIT-{$profit->id}",
                createdByAdminId: $actor->id,
                transactionDate: $profit->approved_at ?? $profit->created_at,
                description: "تحويل من أرباح شهر {$profit->month}/{$profit->year} (إعادة تسوية)",
                actor: $actor,
            );
            $createdCount++;
        }

        return $createdCount;
    }

    private function ensureDepreciationNote(MonthlyProfit $profit, string $amount, Admin $actor): int
    {
        if (bccomp($amount, '0', 2) <= 0) {
            return 0;
        }

        $fund = $this->resolveFund('depreciation_fund');
        if ($fund === null) {
            return 0;
        }

        $exists = DepreciationNote::query()
            ->where('monthly_profit_id', $profit->id)
            ->where('amount', $amount)
            ->exists();

        if ($exists) {
            return 0;
        }

        DepreciationNote::query()->create([
            'participant_id' => null,
            'fund_id' => $fund->id,
            'monthly_profit_id' => $profit->id,
            'amount' => $amount,
            'rate' => '0.0500',
            'transaction_date' => $profit->approved_at?->toDateString() ?? now()->toDateString(),
            'year' => $profit->year,
            'month' => $profit->month,
            'description' => "مخصص إهلاك شهر {$profit->month}/{$profit->year}",
            'admin_note' => 'تم إنشاء المخصص تلقائياً أثناء إعادة التسوية المالية.',
            'created_by_admin_id' => $actor->id,
        ]);

        return 1;
    }

    private function regenerateDraftSettlement(int $year, Admin $actor): ?string
    {
        $settlement = Settlement::query()
            ->where('year', $year)
            ->where('status', 'draft')
            ->where('parent_id', null)
            ->where('paid_amount', '0.00')
            ->first();

        if ($settlement === null) {
            return null;
        }

        $allocations = ParticipantProfitAllocation::query()
            ->join('monthly_profits', 'monthly_profits.id', '=', 'participant_profit_allocations.monthly_profit_id')
            ->where('monthly_profits.year', $year)
            ->where('monthly_profits.status', 'approved')
            ->get([
                'participant_profit_allocations.*',
            ])
            ->groupBy('participant_id')
            ->map(fn ($rows): string => $rows->reduce(
                fn (string $carry, $row): string => bcadd($carry, (string) $row->amount, 2),
                '0.00',
            ));

        $profitTotal = $allocations->values()->reduce(fn (string $carry, string $amount): string => bcadd($carry, $amount, 2), '0.00');

        SettlementItem::query()->where('settlement_id', $settlement->id)->delete();

        foreach ($allocations as $participantId => $amount) {
            SettlementItem::query()->create([
                'settlement_id' => $settlement->id,
                'participant_id' => (int) $participantId,
                'profit_share' => $amount,
                'fund_share' => '0.00',
                'net_payable' => $amount,
                'payment_status' => 'pending',
                'paid_amount' => '0.00',
            ]);
        }

        $settlement->forceFill([
            'total_distributed_amount' => $profitTotal,
            'participant_profit_share' => $profitTotal,
            'net_payable' => $profitTotal,
            'amount_due' => $profitTotal,
        ])->saveQuietly();

        $this->audit->log('finance_reconcile_settlement_regenerated', $actor, 'settlement', $settlement->id, [
            'year' => $year,
            'participant_profit_share' => $profitTotal,
        ]);

        return sprintf('SETTLEMENT-%d (%d): %s', $settlement->id, $year, $profitTotal);
    }

    /** @param array<int, array{participant_id:int, amount:string}> $entries */
    private function exactAmounts(string $total, array $entries): array
    {
        $normalized = [];
        $sum = '0.00';
        foreach ($entries as $entry) {
            $amount = $this->rounding->money($entry['amount']);
            $sum = bcadd($sum, $amount, 2);
            $normalized[] = ['participant_id' => $entry['participant_id'], 'amount' => $amount];
        }

        $delta = bcsub($total, $sum, 2);
        if (bccomp($delta, '0', 2) === 0 || $normalized === []) {
            return $normalized;
        }

        $indexes = array_keys($normalized);
        usort($indexes, static fn (int $a, int $b): int => bccomp($normalized[$b]['amount'], $normalized[$a]['amount'], 2));

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

    private function resolveFund(string $canonicalCode): ?Fund
    {
        return Fund::resolveSystemFund($canonicalCode);
    }
}
