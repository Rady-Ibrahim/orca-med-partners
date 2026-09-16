<?php

declare(strict_types=1);

namespace Tests\Feature\Settlements;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Enums\SettlementStatus;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PartiallyPaidFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_marks_settlement_as_partially_paid_then_paid(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-03-28',
            'year' => 2026,
            'month' => 3,
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
            'effective_from' => '2026-03-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 3);
        $approvedProfit = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);

        $amountDue = (string) $approved->amount_due;
        static::assertSame('65.00', $amountDue);

        $partial = app(RecordSettlementPaymentAction::class)->execute($admin, $approved, [
            'amount' => '40.00',
            'payment_method' => 'bank',
            'payment_source' => 'bank_transfer',
        ]);

        static::assertSame(SettlementStatus::PARTIALLY_PAID->value, $partial->status);
        static::assertSame('40.00', (string) $partial->paid_amount);

        $full = app(RecordSettlementPaymentAction::class)->execute($admin, $partial, [
            'amount' => '25.00',
            'payment_method' => 'bank',
            'payment_source' => 'bank_transfer',
        ]);

        static::assertSame(SettlementStatus::PAID->value, $full->status);
        static::assertSame('65.00', (string) $full->paid_amount);
        static::assertSame('paid', $full->items()->first()->payment_status);
    }

    public function test_new_settlement_is_blocked_while_partially_paid_exists(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'is_super_admin' => true]);
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-04-28',
            'year' => 2026,
            'month' => 4,
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
            'effective_from' => '2026-04-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 4);
        $approvedProfit = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);

        app(RecordSettlementPaymentAction::class)->execute($admin, $approved, ['amount' => '10.00']);

        static::assertSame(SettlementStatus::PARTIALLY_PAID->value, $approved->fresh()->status);

        $this->expectException(InvalidAnnualSettlementException::class);
        app(CreateAnnualSettlementAction::class)->execute($admin, 2026);
    }
}