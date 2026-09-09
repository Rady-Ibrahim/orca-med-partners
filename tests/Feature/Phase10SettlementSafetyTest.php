<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\CreateSettlementAdjustmentAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\Admin;
use App\Models\Participant;
use App\Models\SettlementPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class Phase10SettlementSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_source_is_explicit_and_adjustment_preserves_paid_history(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active', 'is_super_admin' => true]);
        $participant = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $this->createAnnualData($admin, $participant, 2040);
        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2040);
        $approved = app(ApproveSettlementAction::class)->execute($admin, $settlement);
        $paid = app(RecordSettlementPaymentAction::class)->execute($admin, $approved, ['amount' => (string) $approved->amount_due, 'payment_source' => 'bank']);

        self::assertSame('bank', $paid->payments->first()->payment_source);
        $adjustment = app(CreateSettlementAdjustmentAction::class)->execute($admin, $paid, [
            'settlement_payment_id' => $paid->payments->first()->id,
            'type' => 'reversal',
            'direction' => 'decrease',
            'amount' => '10.00',
            'reason' => 'Correction reference',
        ]);

        self::assertSame('paid', $paid->fresh()->status);
        self::assertSame((string) $approved->amount_due, (string) $paid->fresh()->amount_due);
        $this->expectException(ImmutableFinancialRecordException::class);
        $adjustment->amount = '20.00';
        $adjustment->save();
    }

    private function createAnnualData(Admin $admin, Participant $participant, int $year): void
    {
        $snapshot = \App\Models\CapitalSnapshot::query()->create(['snapshot_date' => $year . '-01-01', 'year' => $year, 'month' => 1, 'total_capital' => '1000.00', 'status' => 'final']);
        $snapshot->items()->create(['participant_id' => $participant->id, 'participant_capital_snapshot' => '1000.00', 'participant_ratio_snapshot' => '1.0000']);
        $rule = \App\Models\DistributionRule::query()->create(['effective_from' => $year . '-01-01', 'management_fee_rate' => '0.2500', 'depreciation_fund_rate' => '0.0500', 'growth_fund_rate' => '0.0250', 'incentive_fund_rate' => '0.0250', 'distributed_share_rate' => '0.6500', 'status' => 'active']);
        $profit = \App\Models\MonthlyProfit::query()->create(['capital_snapshot_id' => $snapshot->id, 'distribution_rule_id' => $rule->id, 'year' => $year, 'month' => 1, 'version' => 1, 'status' => 'approved', 'gross_profit' => '100.00', 'management_amount' => '25.00', 'depreciation_amount' => '5.00', 'growth_amount' => '2.50', 'incentive_amount' => '2.50', 'distributed_amount' => '65.00', 'rounding_delta_adjustment' => '0.00']);
        \App\Models\ParticipantProfitAllocation::query()->create(['monthly_profit_id' => $profit->id, 'participant_id' => $participant->id, 'amount' => '65.00', 'share_ratio' => '1.0000', 'status' => 'approved']);
    }
}
