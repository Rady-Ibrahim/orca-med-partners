<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DeleteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_participant_is_soft_deleted_and_records_intact(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $participant = Participant::factory()->create();

        Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '10000.00',
            'invested_at' => '2026-01-01',
            'status' => 'approved',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $participant->delete();

        $this->assertSoftDeleted('participants', ['id' => $participant->id]);
        $this->assertSame(1, Investment::query()->where('participant_id', $participant->id)->count());
        self::assertDatabaseHas('investments', ['participant_id' => $participant->id]);
    }

    public function test_admin_is_soft_deleted(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $admin->delete();

        $this->assertSoftDeleted('admins', ['id' => $admin->id]);
    }

    public function test_approved_investment_can_be_deleted(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $participant = Participant::factory()->create();
        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '10000.00',
            'invested_at' => '2026-01-01',
            'status' => 'approved',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $investment->delete();

        $this->assertDatabaseMissing('investments', ['id' => $investment->id]);
    }

    public function test_distribution_rule_referenced_by_profit_can_be_deleted_via_controller(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 5);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 5);

        $this->ruleDeleteControllerSimulation($rule);

        $this->assertDatabaseMissing('distribution_rules', ['id' => $rule->id]);
        $this->assertDatabaseHas('monthly_profits', [
            'id' => $profit->id,
            'distribution_rule_id' => null,
        ]);
    }

    public function test_capital_snapshot_used_by_approved_profit_can_be_updated(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 6);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 6);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $snapshot->update(['total_capital' => '200.00']);

        $this->assertDatabaseHas('capital_snapshots', ['id' => $snapshot->id, 'total_capital' => '200.00']);
    }

    public function test_depreciation_note_linked_to_approved_profit_can_be_modified_and_deleted(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 7);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 7);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $note = DepreciationNote::query()->where('monthly_profit_id', $profit->id)->firstOrFail();

        $note->update(['amount' => '9.99']);
        $this->assertDatabaseHas('depreciation_notes', ['id' => $note->id, 'amount' => '9.99']);

        $note->delete();
        $this->assertDatabaseMissing('depreciation_notes', ['id' => $note->id]);
    }

    public function test_depreciation_note_standalone_can_be_deleted_when_not_approved_linked(): void
    {
        $admin = Admin::factory()->create();
        $fund = Fund::query()->create(['code' => 'depreciation_fund', 'name' => 'Depreciation Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);

        $note = DepreciationNote::query()->create([
            'fund_id' => $fund->id,
            'amount' => '10.00',
            'rate' => '0.0500',
            'transaction_date' => '2026-01-31',
            'year' => 2026,
            'month' => 1,
            'description' => 'test',
            'created_by_admin_id' => $admin->id,
        ]);

        $note->delete();
        $this->assertDatabaseMissing('depreciation_notes', ['id' => $note->id]);
    }

    public function test_fund_allocation_linked_to_approved_profit_can_be_deleted(): void
    {
        [$admin, $snapshot, $rule, $participant] = $this->financialContext(2026, 8);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 8);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $allocation = ParticipantFundAllocation::query()->where('monthly_profit_id', $profit->id)->firstOrFail();
        self::assertSame($participant->id, $allocation->participant_id);

        $allocation->delete();
        $this->assertDatabaseMissing('participant_fund_allocations', ['id' => $allocation->id]);
    }

    public function test_monthly_profit_approved_can_be_updated_and_deleted(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 9);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 9);
        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $approved->update(['gross_profit' => '200.00']);
        $this->assertDatabaseHas('monthly_profits', ['id' => $approved->id, 'gross_profit' => '200.00']);

        $approved->refresh()->delete();
        $this->assertDatabaseMissing('monthly_profits', ['id' => $approved->id]);
    }

    /**
     * Emulates AdminActionsController::destroyDistributionRule: null references, then delete.
     */
    private function ruleDeleteControllerSimulation(DistributionRule $rule): void
    {
        MonthlyProfit::query()->where('distribution_rule_id', $rule->id)->update(['distribution_rule_id' => null]);
        $rule->delete();
    }

    /** @return array{Admin, CapitalSnapshot, DistributionRule, Participant} */
    private function financialContext(int $year, int $month): array
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => sprintf('%d-%02d-28', $year, $month),
            'year' => $year,
            'month' => $month,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => '100.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);
        $rule = DistributionRule::query()->create([
            'effective_from' => sprintf('%d-%02d-01', $year, $month),
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        Fund::query()->create(['code' => 'growth_fund', 'name' => 'Growth Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);
        Fund::query()->create(['code' => 'incentive_fund', 'name' => 'Incentive Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);
        Fund::query()->create(['code' => 'depreciation_fund', 'name' => 'Depreciation Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);

        return [$admin, $snapshot, $rule, $participant];
    }
}
