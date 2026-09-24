<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Admin\ReportDataAction;
use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ExactAllocationAndTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocations_and_ratios_sum_exactly_to_the_pool(): void
    {
        [$admin, $rule] = $this->financialContext();
        $snapshot = $this->productionStyleSnapshot($admin, 2026, 9);

        $result = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '3000000.00', 2026, 9);

        $allocations = ParticipantProfitAllocation::query()->where('monthly_profit_id', $result->id)->get();

        $sum = $allocations->reduce(fn (string $carry, $a): string => bcadd($carry, (string) $a->amount, 2), '0.00');
        $ratioSum = $allocations->reduce(fn (string $carry, $a): string => bcadd($carry, (string) $a->share_ratio, 4), '0.0000');

        static::assertSame('1950000.00', $sum);
        static::assertSame('1.0000', $ratioSum);
        static::assertSame('0.00', (string) $result->rounding_delta_adjustment);

        $tenPercent = $allocations->first(fn ($a): bool => $a->participant_id === 8);
        static::assertSame('195000.00', (string) $tenPercent->amount);
        static::assertSame('0.1000', (string) $tenPercent->share_ratio);
    }

    public function test_fund_allocations_sum_exactly_to_the_fund_amounts(): void
    {
        [$admin, $rule] = $this->financialContext();
        $snapshot = $this->productionStyleSnapshot($admin, 2026, 9);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '3000000.00', 2026, 9);

        $growth = ParticipantFundAllocation::query()->where('monthly_profit_id', $profit->id)->where('allocation_type', 'growth')->get();
        $incentive = ParticipantFundAllocation::query()->where('monthly_profit_id', $profit->id)->where('allocation_type', 'incentive')->get();

        $growthSum = $growth->reduce(fn (string $c, $a): string => bcadd($c, (string) $a->amount, 2), '0.00');
        $incentiveSum = $incentive->reduce(fn (string $c, $a): string => bcadd($c, (string) $a->amount, 2), '0.00');

        static::assertSame('75000.00', $growthSum);
        static::assertSame('75000.00', $incentiveSum);
        static::assertSame('75000.00', (string) $profit->growth_amount);
        static::assertSame('75000.00', (string) $profit->incentive_amount);
    }

    public function test_annual_profits_report_totals_match_the_fund_model(): void
    {
        [$admin, $rule] = $this->financialContext();

        $this->approveProfit($admin, $rule, 2026, 9);
        $this->approveProfit($admin, $rule, 2026, 10);
        $this->approveProfit($admin, $rule, 2026, 11);

        $reports = app(ReportDataAction::class);
        $totals = $reports->totals('annual-profits', ['year' => 2026]);

        static::assertNotNull($totals);
        static::assertSame('9000000.00', $totals['gross_share']);
        static::assertSame('2250000.00', $totals['management_share']);
        static::assertSame('450000.00', $totals['depreciation_share']);
        static::assertSame('225000.00', $totals['growth_share']);
        static::assertSame('225000.00', $totals['incentive_share']);
        static::assertSame('5850000.00', $totals['amount']);
        static::assertSame('الإجمالي الكلي', $totals['year']);
        static::assertSame('100.00%', $totals['ratio']);
    }

    public function test_settlement_aggregates_all_approved_months_exactly(): void
    {
        [$admin, $rule] = $this->financialContext();

        $this->approveProfit($admin, $rule, 2026, 9);
        $this->approveProfit($admin, $rule, 2026, 10);
        $this->approveProfit($admin, $rule, 2026, 11);

        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);

        static::assertSame('5850000.00', (string) $settlement->participant_profit_share);
        static::assertSame('5850000.00', (string) $settlement->amount_due);

        $item = $settlement->items()->where('participant_id', 8)->firstOrFail();
        static::assertSame('585000.00', (string) $item->profit_share);
        static::assertSame('585000.00', (string) $item->net_payable);
    }

    private function approveProfit(Admin $admin, DistributionRule $rule, int $year, int $month): void
    {
        $snapshot = $this->productionStyleSnapshot($admin, $year, $month);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '3000000.00', $year, $month);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
    }

    private function productionStyleSnapshot(Admin $admin, int $year, int $month): CapitalSnapshot
    {
        $capitals = [
            1 => '1.00', 2 => '1.00', 3 => '1.00', 4 => '51000000.00', 5 => '2000000.00',
            6 => '6000001.00', 7 => '0.00', 8 => '7000000.00', 9 => '1000000.00',
            10 => '2000000.00', 11 => '1000000.00',
        ];

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => sprintf('%d-%02d-28', $year, $month),
            'year' => $year,
            'month' => $month,
            'total_capital' => '70000004.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);

        foreach ($capitals as $participantId => $capital) {
            $snapshot->items()->create([
                'participant_id' => $participantId,
                'participant_capital_snapshot' => $capital,
                'participant_ratio_snapshot' => '0.0000',
            ]);
        }

        return $snapshot->fresh();
    }

    /** @return array{Admin, DistributionRule} */
    private function financialContext(): array
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] as $participantId) {
            Participant::query()->create([
                'id' => $participantId,
                'first_name' => 'Participant',
                'last_name' => (string) $participantId,
                'username' => sprintf('participant-%d', $participantId),
                'email' => sprintf('participant%d@example.test', $participantId),
                'password' => Hash::make('secret123'),
                'status' => 'active',
            ]);
        }

        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        foreach (['management_fund', 'growth_fund', 'incentive_fund', 'depreciation_fund'] as $code) {
            Fund::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]
            );
        }

        return [$admin, $rule];
    }
}