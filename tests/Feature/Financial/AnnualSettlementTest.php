<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CancelSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\CreateSettlementRevisionAction;
use App\Actions\Settlements\MarkSettlementPaidAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnualSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_aggregates_approved_effective_profit_only(): void
    {
        [$admin, $first, $second] = $this->createApprovedAnnualData(2026);
        $superseded = MonthlyProfit::query()->where('year', 2026)->firstOrFail();
        $superseded->status = 'superseded';
        $superseded->save();

        $effective = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $superseded->capital_snapshot_id,
            'distribution_rule_id' => $superseded->distribution_rule_id,
            'distribution_rule_snapshot' => $superseded->distribution_rule_snapshot,
            'parent_id' => $superseded->id,
            'year' => 2026,
            'month' => 2,
            'version' => 2,
            'status' => 'approved',
            'gross_profit' => '200.00',
            'management_amount' => '50.00',
            'depreciation_amount' => '10.00',
            'growth_amount' => '5.00',
            'incentive_amount' => '5.00',
            'distributed_amount' => '130.00',
            'rounding_delta_adjustment' => '0.00',
        ]);
        ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $effective->id,
            'participant_id' => $first->id,
            'amount' => '130.00',
            'share_ratio' => '1.0000',
            'status' => 'approved',
        ]);

        ParticipantFundAllocation::query()->create([
            'fund_id' => Fund::query()->create(['code' => 'GROWTH-2026', 'name' => 'Growth Fund'])->id,
            'monthly_profit_id' => $effective->id,
            'participant_id' => $first->id,
            'amount' => '30.00',
            'allocation_type' => 'growth',
        ]);

        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);

        self::assertSame('130.00', (string) $settlement->amount_due);
        self::assertSame('130.00', (string) $settlement->participant_profit_share);
        self::assertSame('0.00', (string) $settlement->participant_fund_share);
        self::assertSame('0.00', (string) $settlement->items->first()->fund_share);
        self::assertSame('130.00', (string) $settlement->items->first()->net_payable);
        self::assertStringContainsString('remain in their funds and are reported separately', (string) $settlement->notes);
        self::assertDatabaseHas('audit_logs', ['action' => 'settlement_created']);
        self::assertNotSame($second->id, $settlement->items->first()->participant_id);
    }

    public function test_settlement_aggregates_all_approved_months_of_the_year(): void
    {
        [$admin, $first] = $this->createApprovedAnnualData(2051);
        $january = MonthlyProfit::query()->where('year', 2051)->firstOrFail();

        $february = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $january->capital_snapshot_id,
            'distribution_rule_id' => $january->distribution_rule_id,
            'distribution_rule_snapshot' => $january->distribution_rule_snapshot,
            'parent_id' => null,
            'year' => 2051,
            'month' => 2,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => '200.00',
            'management_amount' => '50.00',
            'depreciation_amount' => '10.00',
            'growth_amount' => '5.00',
            'incentive_amount' => '5.00',
            'distributed_amount' => '130.00',
            'rounding_delta_adjustment' => '0.00',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
        ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $february->id,
            'participant_id' => $first->id,
            'amount' => '130.00',
            'share_ratio' => '1.0000',
            'status' => 'approved',
        ]);

        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2051);

        self::assertSame('195.00', (string) $settlement->participant_profit_share);
        self::assertSame('195.00', (string) $settlement->amount_due);
        self::assertSame('195.00', (string) $settlement->items->first()->net_payable);
    }

    public function test_empty_year_is_rejected_without_creating_settlement(): void
    {
        $admin = $this->settlementAdmin();

        $this->expectException(InvalidAnnualSettlementException::class);
        app(CreateAnnualSettlementAction::class)->execute($admin, 2025);
        self::assertDatabaseCount('settlements', 0);
    }

    public function test_settlement_lifecycle_approves_and_marks_full_amount_paid(): void
    {
        [$admin] = $this->createApprovedAnnualData(2027);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2027);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);
        $paid = app(MarkSettlementPaidAction::class)->execute($admin, $approved);

        self::assertSame('approved', $approved->status);
        self::assertSame('paid', $paid->status);
        self::assertSame((string) $paid->amount_due, (string) $paid->paid_amount);
        self::assertSame('paid', (string) $paid->items->first()->payment_status);
        self::assertNotNull($paid->approved_at);
        self::assertNotNull($paid->payout_at);
        self::assertDatabaseHas('audit_logs', ['action' => 'settlement_approved']);
        self::assertDatabaseHas('audit_logs', ['action' => 'settlement_paid']);
    }

    public function test_approved_settlement_and_items_can_be_edited(): void
    {
        [$admin] = $this->createApprovedAnnualData(2028);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2028);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);

        $approved->amount_due = '999.00';
        $approved->save();

        $this->assertDatabaseHas('settlements', ['id' => $approved->id, 'amount_due' => '999.00']);
    }

    public function test_approved_settlement_items_are_editable(): void
    {
        [$admin] = $this->createApprovedAnnualData(2031);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2031);
        app(ApproveSettlementAction::class)->execute($admin, $settlement);
        $item = $settlement->items()->firstOrFail();

        $item->net_payable = '999.00';
        $item->save();

        $this->assertDatabaseHas('settlement_items', ['id' => $item->id, 'net_payable' => '999.00']);
    }

    public function test_participant_settlement_screens_show_own_due_not_aggregate(): void
    {
        $year = 2040;
        [$admin, $first, $second] = $this->createApprovedAnnualData($year, '40.00');
        $profit = MonthlyProfit::query()->where('year', $year)->firstOrFail();

        ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $profit->id,
            'participant_id' => $second->id,
            'amount' => '25.00',
            'share_ratio' => '1.0000',
            'status' => 'approved',
        ]);
        ParticipantFundAllocation::query()->create([
            'fund_id' => Fund::query()->create(['code' => 'INC-2040', 'name' => 'Incentive'])->id,
            'monthly_profit_id' => $profit->id,
            'participant_id' => $first->id,
            'amount' => '5.00',
            'allocation_type' => 'incentive',
        ]);

        $settlement = app(ApproveSettlementAction::class)->execute(
            $admin,
            app(CreateAnnualSettlementAction::class)->execute($admin, $year),
        );

        self::assertSame('65.00', (string) $settlement->amount_due);

        $firstToken = $first->createToken('participant-api', ['*'])->plainTextToken;
        $secondToken = $second->createToken('participant-api', ['*'])->plainTextToken;

        $firstList = $this->withToken($firstToken)->getJson('/api/v1/me/settlements')->assertOk()->json('data.data.0');
        self::assertSame('40.00', $firstList['amount_due']);
        self::assertSame('0.00', $firstList['paid_amount']);
        self::assertSame('40.00', $firstList['remaining']);

        $secondList = $this->withToken($secondToken)->getJson('/api/v1/me/settlements')->assertOk()->json('data.data.0');
        self::assertSame('25.00', $secondList['amount_due']);
        self::assertSame('25.00', $secondList['remaining']);

        $detail = $this->withToken($firstToken)->getJson('/api/v1/me/settlements/'.$settlement->id)->assertOk()->json('data');
        self::assertSame('40.00', $detail['amount_due']);
        self::assertSame('40.00', $detail['remaining']);
        self::assertSame('65.00', $detail['settlement_total_due']);
    }

    public function test_participant_can_only_read_own_settlement(): void
    {
        [$admin, $owner, $other] = $this->createApprovedAnnualData(2029);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2029);
        $ownerToken = $owner->createToken('participant-api', ['*'])->plainTextToken;
        $otherToken = $other->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($ownerToken)->getJson('/api/v1/participant/settlements')->assertOk();
        $this->withToken($ownerToken)->getJson('/api/v1/participant/settlements/'.$settlement->id)->assertOk();
        $this->withToken($otherToken)->getJson('/api/v1/participant/settlements/'.$settlement->id)->assertForbidden();
        $this->withToken($ownerToken)->postJson('/api/v1/admin/settlements', ['year' => 2029])->assertForbidden();
    }

    public function test_admin_settlement_api_supports_create_approve_and_paid(): void
    {
        [$admin] = $this->createApprovedAnnualData(2030);
        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/v1/admin/settlements', ['year' => 2030])->assertCreated()->json('data');
        $this->withToken($token)->getJson('/api/v1/admin/settlements/'.$created['id'])->assertOk();
        $this->withToken($token)->postJson('/api/v1/admin/settlements/'.$created['id'].'/approve')->assertOk();
        $this->withToken($token)->postJson('/api/v1/admin/settlements/'.$created['id'].'/paid')->assertOk();
    }

    public function test_amount_due_does_not_invent_prior_deductions_or_include_principal(): void
    {
        [$admin] = $this->createApprovedAnnualData(2032, '10000.00');
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2032);

        self::assertSame('10000.00', (string) $settlement->amount_due);
        self::assertSame('0.00', (string) $settlement->participant_fund_share);
        self::assertStringContainsString('Previous payments are recorded separately', (string) $settlement->notes);
    }

    public function test_payment_does_not_change_funds_or_create_fund_transactions(): void
    {
        [$admin] = $this->createApprovedAnnualData(2033);
        $fund = Fund::query()->create([
            'code' => 'SETTLEMENT-FUND-2033',
            'name' => 'Settlement Reserve',
            'current_balance' => '500.00',
            'status' => 'active',
        ]);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2033);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);
        app(MarkSettlementPaidAction::class)->execute($admin, $approved);

        self::assertSame('500.00', (string) $fund->fresh()->current_balance);
        self::assertDatabaseCount('fund_transactions', 0);
    }

    public function test_partial_and_final_payments_are_reconciled_and_editable(): void
    {
        [$admin] = $this->createApprovedAnnualData(2038, '100.00');
        $settlement = app(ApproveSettlementAction::class)->execute($admin, app(CreateAnnualSettlementAction::class)->execute($admin, 2038));

        $partial = app(RecordSettlementPaymentAction::class)->execute($admin, $settlement, ['amount' => '60.00', 'reference' => 'PAY-1']);
        self::assertSame('partially_paid', $partial->status);
        self::assertSame('60.00', (string) $partial->paid_amount);
        self::assertCount(1, $partial->payments);

        $paid = app(RecordSettlementPaymentAction::class)->execute($admin, $partial, ['amount' => '40.00', 'reference' => 'PAY-2']);
        self::assertSame('paid', $paid->status);
        self::assertSame('100.00', (string) $paid->paid_amount);
        self::assertCount(2, $paid->payments);

        $payment = $paid->payments->first();
        $payment->update(['amount' => '55.00']);
        $this->assertDatabaseHas('settlement_payments', ['id' => $payment->id, 'amount' => '55.00']);
    }

    public function test_draft_can_be_cancelled_but_finalized_settlement_cannot(): void
    {
        [$admin] = $this->createApprovedAnnualData(2034);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2034);
        $cancelled = app(CancelSettlementAction::class)->execute($admin, $settlement);

        self::assertSame('cancelled', $cancelled->status);
        self::assertDatabaseHas('audit_logs', ['action' => 'settlement_cancelled']);

        [$secondAdmin] = $this->createApprovedAnnualData(2035);
        $approved = app(ApproveSettlementAction::class)->execute(
            $secondAdmin,
            app(CreateAnnualSettlementAction::class)->execute($secondAdmin, 2035),
        );
        $this->expectException(InvalidAnnualSettlementException::class);
        app(CancelSettlementAction::class)->execute($secondAdmin, $approved);
    }

    public function test_approved_revision_supersedes_original_without_mutating_values(): void
    {
        [$admin] = $this->createApprovedAnnualData(2036);
        $original = app(ApproveSettlementAction::class)->execute(
            $admin,
            app(CreateAnnualSettlementAction::class)->execute($admin, 2036),
        );
        $revision = app(CreateSettlementRevisionAction::class)->execute($admin, $original);

        self::assertSame('superseded', $original->fresh()->status);
        self::assertSame(2, $revision->version);
        self::assertSame($original->id, $revision->parent_id);
        self::assertSame('65.00', (string) $original->fresh()->amount_due);
        self::assertDatabaseHas('audit_logs', ['action' => 'settlement_revised']);
    }

    public function test_paid_revision_preserves_paid_original_and_does_not_create_fund_effect(): void
    {
        [$admin] = $this->createApprovedAnnualData(2037);
        $paid = app(MarkSettlementPaidAction::class)->execute(
            $admin,
            app(ApproveSettlementAction::class)->execute(
                $admin,
                app(CreateAnnualSettlementAction::class)->execute($admin, 2037),
            ),
        );
        $revision = app(CreateSettlementRevisionAction::class)->execute($admin, $paid);

        self::assertSame('paid', $paid->fresh()->status);
        self::assertSame('65.00', (string) $paid->fresh()->paid_amount);
        self::assertSame($paid->id, $revision->parent_id);
        self::assertSame(2, $revision->version);
        self::assertDatabaseCount('fund_transactions', 0);
    }

    public function test_revision_amount_due_is_net_of_previous_payments(): void
    {
        [$admin] = $this->createApprovedAnnualData(2038);
        $paid = app(MarkSettlementPaidAction::class)->execute(
            $admin,
            app(ApproveSettlementAction::class)->execute(
                $admin,
                app(CreateAnnualSettlementAction::class)->execute($admin, 2038),
            ),
        );

        $revision = app(CreateSettlementRevisionAction::class)->execute($admin, $paid);

        self::assertSame('65.00', (string) $paid->fresh()->amount_due);
        self::assertSame('65.00', (string) $paid->fresh()->paid_amount);
        self::assertSame('0.00', (string) $revision->amount_due);
        self::assertSame('0.00', (string) $revision->net_payable);
        self::assertSame('0.00', (string) $revision->paid_amount);
        self::assertSame($paid->id, $revision->parent_id);
        self::assertSame(2, $revision->version);
    }

    /** @return array{Admin, Participant, Participant} */
    private function createApprovedAnnualData(int $year, string $allocationAmount = '65.00'): array
    {
        $admin = $this->settlementAdmin();
        $first = Participant::factory()->create(['status' => 'active', 'password' => Hash::make('secret123')]);
        $second = Participant::factory()->create(['status' => 'active', 'password' => Hash::make('secret123')]);
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => $year.'-01-31',
            'year' => $year,
            'month' => 1,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $rule = DistributionRule::query()->create([
            'effective_from' => $year.'-01-01',
            'effective_to' => $year.'-12-31',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
        ]);
        $profit = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'distribution_rule_snapshot' => ['management_fee_rate' => '0.2500'],
            'year' => $year,
            'month' => 1,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => '100.00',
            'management_amount' => '25.00',
            'depreciation_amount' => '5.00',
            'growth_amount' => '2.50',
            'incentive_amount' => '2.50',
            'distributed_amount' => '65.00',
            'rounding_delta_adjustment' => '0.00',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);
        ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $profit->id,
            'participant_id' => $first->id,
            'amount' => $allocationAmount,
            'share_ratio' => '1.0000',
            'status' => 'approved',
        ]);

        return [$admin, $first, $second];
    }

    private function settlementAdmin(): Admin
    {
        return Admin::factory()->create([
            'role' => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_FINANCIAL_MANAGER),
            'is_super_admin' => false,
            'status' => 'active',
        ]);
    }
}
