<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitRevisionAction;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Services\FinancialCalculationServiceContract;
use App\Domain\Financial\ValueObjects\MonthlyProfitCalculationResult;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\ParticipantProfitAllocation;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Mockery;
use Tests\TestCase;

class MonthlyProfitEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_calculates_rates_from_snapshot_capital_with_rounding_delta(): void
    {
        $admin = Admin::factory()->create();
        $first = Participant::factory()->create();
        $second = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-01-31',
            'year' => 2026,
            'month' => 1,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->createMany([
            ['participant_id' => $first->id, 'participant_capital_snapshot' => '33.33', 'participant_ratio_snapshot' => '0.3333'],
            ['participant_id' => $second->id, 'participant_capital_snapshot' => '66.67', 'participant_ratio_snapshot' => '0.6667'],
        ]);
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

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 1);

        self::assertSame('25.00', (string) $profit->management_amount);
        self::assertSame('5.00', (string) $profit->depreciation_amount);
        self::assertSame('2.50', (string) $profit->growth_amount);
        self::assertSame('2.50', (string) $profit->incentive_amount);
        self::assertSame('65.00', (string) $profit->distributed_amount);
        self::assertSame('0.00', (string) $profit->rounding_delta_adjustment);
        self::assertCount(2, $profit->allocations);
        self::assertSame($rule->management_fee_rate, $profit->distribution_rule_snapshot['management_fee_rate']);
        self::assertSame('65.00', bcadd((string) $profit->allocations->sum('amount'), (string) $profit->rounding_delta_adjustment, 2));
    }

    public function test_zero_capital_is_rejected_without_creating_a_profit(): void
    {
        $admin = Admin::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-02-28',
            'year' => 2026,
            'month' => 2,
            'total_capital' => '0.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-02-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
        ]);

        $this->expectException(InvalidCapitalSnapshotException::class);
        app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 2);
        self::assertDatabaseCount('monthly_profits', 0);
    }

    public function test_approval_immutability_and_revision_preserve_the_original(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 3);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 3);
        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $this->expectException(ImmutableFinancialRecordException::class);
        $approved->gross_profit = '200.00';
        $approved->save();
    }

    public function test_revision_creates_a_new_version_and_supersedes_the_old_record(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 4);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 4);
        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
        $revision = app(CreateMonthlyProfitRevisionAction::class)->execute($admin, $approved, '110.00');

        self::assertSame('superseded', $approved->fresh()->status);
        self::assertSame(2, $revision->version);
        self::assertSame($approved->id, $revision->parent_id);
        self::assertSame('100.00', (string) $approved->fresh()->gross_profit);
        self::assertDatabaseHas('audit_logs', ['action' => 'monthly_profit_revision_created']);
    }

    public function test_revision_uses_the_historical_rule_snapshot_after_rule_changes(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 5);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 5);
        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $rule->update([
            'management_fee_rate' => '0.2000',
            'depreciation_fund_rate' => '0.1000',
        ]);

        $revision = app(CreateMonthlyProfitRevisionAction::class)->execute($admin, $approved, '100.00');

        self::assertSame('0.2500', $revision->distribution_rule_snapshot['management_fee_rate']);
        self::assertSame('25.00', (string) $revision->management_amount);
    }

    public function test_employee_without_profit_creation_permission_cannot_create_monthly_profit(): void
    {
        $employee = Admin::factory()->create([
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_EMPLOYEE),
            'is_super_admin' => false,
        ]);
        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/monthly-profits', [
            'capital_snapshot_id' => 1,
            'distribution_rule_id' => 1,
            'gross_profit' => '100.00',
            'year' => 2026,
            'month' => 6,
        ])->assertForbidden();
    }

    public function test_failed_allocation_rolls_back_the_monthly_profit(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 6);
        $result = new MonthlyProfitCalculationResult(
            grossProfit: '100.00',
            ruleSnapshot: [
                'management_fee_rate' => '0.2500',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate' => '0.0250',
                'incentive_fund_rate' => '0.0250',
                'distributed_share_rate' => '0.6500',
            ],
            managementAmount: '25.00',
            depreciationAmount: '5.00',
            growthAmount: '2.50',
            incentiveAmount: '2.50',
            distributedPool: '65.00',
            totalParticipantCapital: '100.00',
            participantAllocations: [['participant_id' => 999999, 'amount' => '65.00', 'share_ratio' => '1.0000']],
            roundedAllocationsTotal: '65.00',
            roundingDelta: '0.00',
        );
        $calculation = Mockery::mock(FinancialCalculationServiceContract::class);
        $calculation->shouldReceive('calculate')->once()->andReturn($result);
        $this->app->instance(FinancialCalculationServiceContract::class, $calculation);

        $this->expectException(QueryException::class);
        try {
            app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 6);
        } finally {
            self::assertDatabaseCount('monthly_profits', 0);
            self::assertDatabaseCount('participant_profit_allocations', 0);
        }
    }

    /** @return array{Admin, CapitalSnapshot, DistributionRule} */
    private function financialContext(int $year, int $month): array
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123')]);
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
        ]);

        return [$admin, $snapshot, $rule];
    }
}
