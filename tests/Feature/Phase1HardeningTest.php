<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Financial\Services\DistributionRuleService;
use App\Domain\Financial\Services\FundBalanceService;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase1HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_capital_snapshot_is_editable_after_approved_monthly_profit_uses_it(): void
    {
        $admin = Admin::factory()->create();
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2025-01-31',
            'year' => 2025,
            'month' => 1,
            'total_capital' => 10000.00,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);

        DistributionRule::query()->create([
            'effective_from' => '2025-01-01',
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => DistributionRule::query()->first()->id,
            'year' => 2025,
            'month' => 1,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => 1000.00,
            'management_amount' => 250.00,
            'depreciation_amount' => 50.00,
            'growth_amount' => 25.00,
            'incentive_amount' => 25.00,
            'distributed_amount' => 650.00,
            'rounding_delta_adjustment' => 0.00,
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $snapshot->update(['total_capital' => 15000.00]);

        $this->assertDatabaseHas('capital_snapshots', ['id' => $snapshot->id, 'total_capital' => 15000.00]);
    }

    public function test_approved_monthly_profit_can_be_mutated(): void
    {
        $admin = Admin::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2025-01-31',
            'year' => 2025,
            'month' => 1,
            'total_capital' => 10000.00,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);

        $rule = DistributionRule::query()->create([
            'effective_from' => '2025-01-01',
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $profit = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => 2025,
            'month' => 1,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => 1000.00,
            'management_amount' => 250.00,
            'depreciation_amount' => 50.00,
            'growth_amount' => 25.00,
            'incentive_amount' => 25.00,
            'distributed_amount' => 650.00,
            'rounding_delta_adjustment' => 0.00,
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $profit->update(['gross_profit' => 1200.00]);

        $this->assertDatabaseHas('monthly_profits', ['id' => $profit->id, 'gross_profit' => 1200.00]);
    }

    public function test_approved_settlement_can_be_mutated_or_deleted(): void
    {
        $admin = Admin::factory()->create();

        $settlement = Settlement::query()->create([
            'year' => 2025,
            'version' => 1,
            'status' => 'approved',
            'total_distributed_amount' => 1000.00,
            'participant_profit_share' => 600.00,
            'participant_fund_share' => 200.00,
            'net_payable' => 800.00,
            'amount_due' => 800.00,
            'paid_amount' => 0.00,
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $settlement->update(['amount_due' => 900.00]);
        $this->assertDatabaseHas('settlements', ['id' => $settlement->id, 'amount_due' => 900.00]);

        $settlement->refresh()->delete();
        $this->assertDatabaseMissing('settlements', ['id' => $settlement->id]);
    }

    public function test_distribution_rule_service_rejects_overlapping_effective_periods(): void
    {
        $admin = Admin::factory()->create();

        DistributionRule::query()->create([
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-03-31',
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(DistributionRuleService::class)->validateEffectiveRange(
            '2025-02-01',
            '2025-04-30',
            null,
            false
        );
    }

    public function test_fund_balance_service_tracks_deposit_withdrawal_and_adjustment(): void
    {
        $admin = Admin::factory()->create();
        $fund = Fund::query()->create([
            'code' => 'hardening_fund',
            'name' => 'Hardening Fund',
            'current_balance' => 0.00,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $deposit = app(FundBalanceService::class)->applyTransaction($fund, 100, FundTransactionType::DEPOSIT->value, null, 'seed', 'deposit');
        $this->assertSame('100.00', (string) $deposit->resulting_balance);

        $withdrawal = app(FundBalanceService::class)->applyTransaction($fund, 30, FundTransactionType::WITHDRAWAL->value, null, 'withdrawal', 'withdrawal');
        $this->assertSame('70.00', (string) $withdrawal->resulting_balance);

        $adjustment = app(FundBalanceService::class)->applyTransaction($fund, 10, FundTransactionType::ADJUSTMENT->value, null, 'adjustment', 'adjustment');
        $this->assertSame('80.00', (string) $adjustment->resulting_balance);

        $fund->refresh();
        $this->assertSame('80.00', (string) $fund->current_balance);
    }

    public function test_participant_fund_allocation_constraint_requires_uniqueness(): void
    {
        $admin = Admin::factory()->create();
        $participant = Participant::factory()->create();
        $fund = Fund::query()->create([
            'code' => 'unique_fund',
            'name' => 'Unique Fund',
            'current_balance' => 0.00,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2025-02-28',
            'year' => 2025,
            'month' => 2,
            'total_capital' => 10000.00,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $rule = DistributionRule::query()->create([
            'effective_from' => '2025-01-01',
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
        $profit = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => 2025,
            'month' => 2,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => 1000.00,
            'management_amount' => 250.00,
            'depreciation_amount' => 50.00,
            'growth_amount' => 25.00,
            'incentive_amount' => 25.00,
            'distributed_amount' => 650.00,
            'rounding_delta_adjustment' => 0.00,
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        DB::table('participant_fund_allocations')->insert([
            'fund_id' => $fund->id,
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => 100.00,
            'allocation_type' => 'growth',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('participant_fund_allocations')->insert([
            'fund_id' => $fund->id,
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => 200.00,
            'allocation_type' => 'growth',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_monthly_profit_version_and_parent_revision_chain_are_preserved(): void
    {
        $admin = Admin::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2025-03-31',
            'year' => 2025,
            'month' => 3,
            'total_capital' => 10000.00,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $rule = DistributionRule::query()->create([
            'effective_from' => '2025-01-01',
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $original = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => 2025,
            'month' => 3,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => 1000.00,
            'management_amount' => 250.00,
            'depreciation_amount' => 50.00,
            'growth_amount' => 25.00,
            'incentive_amount' => 25.00,
            'distributed_amount' => 650.00,
            'rounding_delta_adjustment' => 0.00,
            'created_by_admin_id' => $admin->id,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $revision = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'parent_id' => $original->id,
            'year' => 2025,
            'month' => 3,
            'version' => 2,
            'status' => 'draft',
            'gross_profit' => 1100.00,
            'management_amount' => 275.00,
            'depreciation_amount' => 55.00,
            'growth_amount' => 27.50,
            'incentive_amount' => 27.50,
            'distributed_amount' => 715.00,
            'rounding_delta_adjustment' => 0.00,
            'created_by_admin_id' => $admin->id,
        ]);

        $this->assertSame($original->id, $revision->parent_id);
        $this->assertSame(2, $revision->version);
        $this->assertSame(1, $original->version);
    }
}
