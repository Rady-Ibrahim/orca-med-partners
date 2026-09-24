<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FinancialSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_financial_summary_returns_outer_kpi_and_monthly_details(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $participant = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $rule = $this->rule($admin);
        $this->snapshotFunds($admin);

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-09-28',
            'year' => 2026,
            'month' => 9,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => '100.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);

        $this->approveProfit($admin, $snapshot, $rule, '100.00', 2026, 9);
        $this->approveProfit($admin, $this->snapshot($admin, 2026, 10), $rule, '100.00', 2026, 10);
        $this->approveProfit($admin, $this->snapshot($admin, 2026, 11), $rule, '100.00', 2026, 11);

        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/me/financial/summary?year=2026')->assertOk()->json('data');

        static::assertSame(2026, $response['year']);
        static::assertSame('300.00', $response['kpi']['total_gross_profit']);
        static::assertSame('195.00', $response['kpi']['distributed_amount']);
        static::assertSame('7.50', $response['kpi']['growth_amount']);
        static::assertSame('7.50', $response['kpi']['incentive_amount']);
        static::assertSame('195.00', $response['participant']['total_approved_profit_share']);
        static::assertSame('7.50', $response['participant']['growth_fund_share']);
        static::assertSame('7.50', $response['participant']['incentive_fund_share']);
        static::assertSame('210.00', $response['net_profitability_kpi']);
        static::assertCount(3, $response['monthly_details']);
        static::assertSame('2026/09', $response['monthly_details'][0]['period']);
        static::assertSame('65.00', $response['monthly_details'][0]['my_profit_share']);
    }

    private function approveProfit(Admin $admin, CapitalSnapshot $snapshot, DistributionRule $rule, string $gross, int $year, int $month): void
    {
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, $gross, $year, $month);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
    }

    private function snapshot(Admin $admin, int $year, int $month): CapitalSnapshot
    {
        return $this->snapshotFor($admin, $year, $month);
    }

    private function snapshotFor(Admin $admin, int $year, int $month): CapitalSnapshot
    {
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => sprintf('%d-%02d-28', $year, $month),
            'year' => $year,
            'month' => $month,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $this->participantId(),
            'participant_capital_snapshot' => '100.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);

        return $snapshot;
    }

    private function participantId(): int
    {
        return Participant::query()->where('status', 'active')->value('id');
    }

    private function rule(Admin $admin): DistributionRule
    {
        return DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);
    }

    private function snapshotFunds(Admin $admin): void
    {
        foreach (['management_fund', 'growth_fund', 'incentive_fund', 'depreciation_fund'] as $code) {
            Fund::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]
            );
        }
    }
}