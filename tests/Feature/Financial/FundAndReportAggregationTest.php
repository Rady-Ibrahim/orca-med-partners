<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Admin\GetDashboardDataAction;
use App\Actions\Admin\ReportDataAction;
use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FundAndReportAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregates_all_months_of_the_year(): void
    {
        [$admin, $rule, $participant] = $this->financialContext();
        [$snapshotA] = $this->snapshot($admin, $participant, 2026, 2, '100.00', '1.0000');
        [$snapshotB] = $this->snapshot($admin, $participant, 2026, 3, '100.00', '1.0000');

        $this->approveProfit($admin, $snapshotA, $rule, '100.00', 2026, 2);
        $this->approveProfit($admin, $snapshotB, $rule, '100.00', 2026, 3);

        $dashboard = app(GetDashboardDataAction::class)->execute(2026);

        static::assertSame('200.00', $dashboard['kpis']['approved_profits']);
        static::assertSame('130.00', $dashboard['kpis']['net_profit']);
        static::assertSame('0.00', $dashboard['monthly_series'][0]['gross']);
        static::assertSame('100.00', $dashboard['monthly_series'][1]['gross']);
        static::assertSame('100.00', $dashboard['monthly_series'][2]['gross']);
        static::assertSame('65.00', $dashboard['monthly_series'][1]['distributed']);
        static::assertSame('65.00', $dashboard['monthly_series'][2]['distributed']);

        $reports = app(ReportDataAction::class);
        $annual = $reports->normalize($reports->execute('annual-profits', ['year' => 2026], false), 'annual-profits');
        $row = $annual->first();
        static::assertSame('130.00', $row['amount']);
        static::assertSame('100.00%', $row['ratio']);
        static::assertSame('200.00', $row['gross_share']);
        static::assertSame('50.00', $row['management_share']);
        static::assertSame('10.00', $row['depreciation_share']);
        static::assertSame('5.00', $row['growth_share']);
        static::assertSame('5.00', $row['incentive_share']);
    }

    public function test_annual_profits_report_aggregates_multiple_participants_with_ratios(): void
    {
        [$admin, $rule, $firstParticipant] = $this->financialContext();
        $secondParticipant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-05-28',
            'year' => 2026,
            'month' => 5,
            'total_capital' => '400.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $firstParticipant->id,
            'participant_capital_snapshot' => '300.00',
            'participant_ratio_snapshot' => '0.7500',
        ]);
        $snapshot->items()->create([
            'participant_id' => $secondParticipant->id,
            'participant_capital_snapshot' => '100.00',
            'participant_ratio_snapshot' => '0.2500',
        ]);

        $this->approveProfit($admin, $snapshot, $rule, '100.00', 2026, 5);

        $reports = app(ReportDataAction::class);
        $annual = $reports->normalize($reports->execute('annual-profits', ['year' => 2026], false), 'annual-profits');

        $byName = $annual->first(
            fn (array $row): bool => str_contains($row['participant'], $firstParticipant->first_name)
        );
        static::assertNotNull($byName, 'First participant row should exist in annual report.');
        static::assertSame('75.00%', $byName['ratio']);
        static::assertSame('48.75', $byName['amount']);
        static::assertSame('75.00', $byName['gross_share']);
        static::assertSame('18.75', $byName['management_share']);
        static::assertSame('3.75', $byName['depreciation_share']);
        static::assertSame('1.88', $byName['growth_share']);
        static::assertSame('1.88', $byName['incentive_share']);
    }

    public function test_dashboard_and_participants_report_expose_capital_ratio(): void
    {
        [$admin, $rule, $participant] = $this->financialContext();
        [$snapshot] = $this->snapshot($admin, $participant, 2026, 6, '400.00', '1.0000');

        $dashboard = app(GetDashboardDataAction::class)->execute(2026);
        $dashboardParticipant = $dashboard['participants'][0];
        static::assertSame('400.00', $dashboardParticipant['capital']);
        static::assertSame('100.00%', $dashboardParticipant['ratio']);

        $reports = app(ReportDataAction::class);
        $participants = $reports->normalize($reports->execute('participants', [], false), 'participants');
        $row = $participants->first();
        static::assertSame('400.00', $row['capital']);
        static::assertSame('100.00%', $row['ratio']);
    }

    public function test_fund_shares_report_aggregates_growth_and_incentive_across_months(): void
    {
        [$admin, $rule, $participant] = $this->financialContext();
        [$snapshotA] = $this->snapshot($admin, $participant, 2026, 2, '100.00', '1.0000');
        [$snapshotB] = $this->snapshot($admin, $participant, 2026, 3, '100.00', '1.0000');

        $this->approveProfit($admin, $snapshotA, $rule, '100.00', 2026, 2);
        $this->approveProfit($admin, $snapshotB, $rule, '100.00', 2026, 3);

        static::assertDatabaseCount('participant_fund_allocations', 4);
        static::assertDatabaseMissing('participant_fund_allocations', ['allocation_type' => 'depreciation']);

        $reports = app(ReportDataAction::class);
        $report = $reports->normalize($reports->execute('fund-shares', ['year' => 2026], false), 'fund-shares');
        $row = $report->first();

        static::assertSame('5.00', $row['growth_amount']);
        static::assertSame('5.00', $row['incentive_amount']);
        static::assertSame('10.00', $row['total_amount']);
    }

    public function test_approval_deposits_management_share_and_system_funds_are_protected(): void
    {
        [$admin, $rule, $participant] = $this->financialContext();
        [$snapshot] = $this->snapshot($admin, $participant, 2026, 4, '100.00', '1.0000');

        $this->approveProfit($admin, $snapshot, $rule, '100.00', 2026, 4);

        static::assertSame('25.00', (string) $this->fund('management_fund')->current_balance);
        static::assertSame('2.50', (string) $this->fund('growth_fund')->current_balance);
        static::assertSame('2.50', (string) $this->fund('incentive_fund')->current_balance);
        static::assertSame('5.00', (string) $this->fund('depreciation_fund')->current_balance);

        $this->expectException(ImmutableFinancialRecordException::class);
        $this->fund('management_fund')->delete();
    }

    private function approveProfit(Admin $admin, CapitalSnapshot $snapshot, DistributionRule $rule, string $gross, int $year, int $month): void
    {
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, $gross, $year, $month);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
    }

    /** @return array{CapitalSnapshot} */
    private function snapshot(Admin $admin, Participant $participant, int $year, int $month, string $capital, string $ratio): array
    {
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => sprintf('%d-%02d-28', $year, $month),
            'year' => $year,
            'month' => $month,
            'total_capital' => $capital,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => $capital,
            'participant_ratio_snapshot' => $ratio,
        ]);

        return [$snapshot];
    }

    /** @return array{Admin, DistributionRule, Participant} */
    private function financialContext(): array
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $participant = Participant::factory()->create();
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

        return [$admin, $rule, $participant];
    }

    private function fund(string $code): Fund
    {
        return Fund::query()->where('code', $code)->firstOrFail();
    }
}