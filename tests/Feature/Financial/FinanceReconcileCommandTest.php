<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FinanceReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_backfills_funds_and_regenerates_stale_draft_settlement(): void
    {
        [$admin, $first, $second] = $this->legacyFinancialContext();

        $sept = $this->legacyApprovedProfit($admin, 2026, 9, '3000000.00');
        $this->legacyOverAllocated($sept, $first, $second);

        $draft = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);

        $october = $this->legacyApprovedProfit($admin, 2026, 10, '3000000.00');
        $this->legacyOverAllocated($october, $first, $second);

        static::assertDatabaseCount('fund_transactions', 0);
        static::assertDatabaseCount('participant_fund_allocations', 0);
        static::assertSame('1950100.00', (string) $draft->fresh()->participant_profit_share);

        $this->artisan('finance:reconcile')->assertExitCode(0);

        $septemberAllocations = ParticipantProfitAllocation::query()->where('monthly_profit_id', $sept->id)->get();
        $octoberAllocations = ParticipantProfitAllocation::query()->where('monthly_profit_id', $october->id)->get();

        foreach ([$septemberAllocations, $octoberAllocations] as $allocations) {
            $sum = $allocations->reduce(fn (string $c, $a): string => bcadd($c, (string) $a->amount, 2), '0.00');
            static::assertSame('1950000.00', $sum);
            $ratioSum = $allocations->reduce(fn (string $c, $a): string => bcadd($c, (string) $a->share_ratio, 4), '0.0000');
            static::assertSame('1.0000', $ratioSum);
        }

        $growthSeed = ParticipantFundAllocation::query()
            ->where('monthly_profit_id', $sept->id)
            ->where('allocation_type', 'growth')
            ->get()
            ->reduce(fn (string $c, $a): string => bcadd($c, (string) $a->amount, 2), '0.00');
        static::assertSame('75000.00', $growthSeed);

        static::assertDatabaseCount('fund_transactions', 8);
        static::assertSame('150000.00', (string) Fund::query()->where('code', 'growth_fund')->firstOrFail()->current_balance);
        static::assertSame('300000.00', (string) Fund::query()->where('code', 'depreciation_fund')->firstOrFail()->current_balance);
        static::assertSame('1500000.00', (string) Fund::query()->where('code', 'management_fund')->firstOrFail()->current_balance);

        static::assertDatabaseCount('depreciation_notes', 2);

        $regenerated = $draft->fresh();
        static::assertSame('3900000.00', (string) $regenerated->participant_profit_share);
        static::assertSame('3900000.00', (string) $regenerated->amount_due);
        static::assertDatabaseCount('settlement_items', 2);

        $before = $regenerated->fresh()->paid_amount;
        $this->artisan('finance:reconcile')->assertExitCode(0);
        static::assertSame('3900000.00', (string) $regenerated->fresh()->amount_due);
        static::assertSame($before, (string) $regenerated->fresh()->paid_amount);
        static::assertSame('150000.00', (string) Fund::query()->where('code', 'growth_fund')->firstOrFail()->current_balance);
    }

    private function legacyApprovedProfit(Admin $admin, int $year, int $month, string $gross): MonthlyProfit
    {
        $snapshot = $this->capitalSnapshot($admin, $year, $month);

        return MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => DistributionRule::query()->firstOrFail()->id,
            'distribution_rule_snapshot' => [
                'management_fee_rate' => '0.2500',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate' => '0.0250',
                'incentive_fund_rate' => '0.0250',
                'distributed_share_rate' => '0.6500',
            ],
            'year' => $year,
            'month' => $month,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => $gross,
            'management_amount' => '750000.00',
            'depreciation_amount' => '150000.00',
            'growth_amount' => '75000.00',
            'incentive_amount' => '75000.00',
            'distributed_amount' => '1950000.00',
            'rounding_delta_adjustment' => '0.00',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    private function legacyOverAllocated(MonthlyProfit $profit, Participant $first, Participant $second): void
    {
        $this->allocation($profit, $first, '195100.00', '0.1000');
        $this->allocation($profit, $second, '1755000.00', '0.9000');
    }

    private function allocation(MonthlyProfit $profit, Participant $participant, string $amount, string $ratio): void
    {
        ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => $amount,
            'share_ratio' => $ratio,
            'status' => 'approved',
        ]);
    }

    private function capitalSnapshot(Admin $admin, int $year, int $month): CapitalSnapshot
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
            'participant_id' => Participant::query()->where('status', 'active')->orderBy('id')->firstOrFail()->id,
            'participant_capital_snapshot' => '50.00',
            'participant_ratio_snapshot' => '0.1000',
        ]);
        $snapshot->items()->create([
            'participant_id' => Participant::query()->where('status', 'active')->orderBy('id')->get()[1]->id,
            'participant_capital_snapshot' => '50.00',
            'participant_ratio_snapshot' => '0.9000',
        ]);

        return $snapshot;
    }

    /** @return array{Admin, Participant, Participant} */
    private function legacyFinancialContext(): array
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $first = Participant::factory()->create(['status' => 'active', 'password' => Hash::make('secret123')]);
        $second = Participant::factory()->create(['status' => 'active', 'password' => Hash::make('secret123')]);

        DistributionRule::query()->create([
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

        return [$admin, $first, $second];
    }
}