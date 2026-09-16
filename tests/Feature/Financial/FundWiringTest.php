<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitRevisionAction;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class FundWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_profit_wires_fund_allocations_and_depreciation_note(): void
    {
        [$admin, $snapshot, $rule, $participant] = $this->financialContext(2026, 1);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 1);

        static::assertDatabaseCount('participant_fund_allocations', 3);
        static::assertDatabaseHas('participant_fund_allocations', [
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => '2.50',
            'allocation_type' => 'growth',
        ]);
        static::assertDatabaseHas('participant_fund_allocations', [
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => '2.50',
            'allocation_type' => 'incentive',
        ]);
        static::assertDatabaseHas('participant_fund_allocations', [
            'monthly_profit_id' => $profit->id,
            'participant_id' => $participant->id,
            'amount' => '5.00',
            'allocation_type' => 'depreciation',
        ]);

        static::assertDatabaseCount('depreciation_notes', 1);
        $note = DepreciationNote::query()->firstOrFail();
        static::assertSame('5.00', (string) $note->amount);
        static::assertSame($this->fund('depreciation_fund')->id, $note->fund_id);
        static::assertNull($note->participant_id);

        static::assertDatabaseCount('fund_transactions', 0);
    }

    public function test_approving_a_profit_deposits_fund_shares_once(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 2);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 2);

        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        static::assertDatabaseCount('fund_transactions', 3);

        $depreciation = FundTransactionType::DEPOSIT->value;
        static::assertDatabaseHas('fund_transactions', [
            'fund_id' => $this->fund('depreciation_fund')->id,
            'monthly_profit_id' => $approved->id,
            'transaction_type' => $depreciation,
            'amount' => '5.00',
            'resulting_balance' => '5.00',
            'reference' => "PROFIT-{$approved->id}",
        ]);
        static::assertDatabaseHas('fund_transactions', [
            'fund_id' => $this->fund('growth_fund')->id,
            'transaction_type' => $depreciation,
            'amount' => '2.50',
            'resulting_balance' => '2.50',
        ]);
        static::assertDatabaseHas('fund_transactions', [
            'fund_id' => $this->fund('incentive_fund')->id,
            'transaction_type' => $depreciation,
            'amount' => '2.50',
            'resulting_balance' => '2.50',
        ]);

        static::assertSame('2.50', (string) $this->fund('growth_fund')->current_balance);
        static::assertSame('5.00', (string) $this->fund('depreciation_fund')->current_balance);
    }

    public function test_revision_approval_does_not_deposit_funds_twice(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 3);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 3);
        $approved = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $revision = app(CreateMonthlyProfitRevisionAction::class)->execute($admin, $approved, '110.00');
        app(ApproveMonthlyProfitAction::class)->execute($admin, $revision);

        static::assertSame(2, $revision->version);
        static::assertDatabaseCount('fund_transactions', 3);
        static::assertSame('5.00', (string) $this->fund('depreciation_fund')->current_balance);
    }

    public function test_fund_with_transactions_cannot_be_deleted(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 4);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 4);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $this->expectException(ImmutableFinancialRecordException::class);
        $this->fund('depreciation_fund')->delete();
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

    private function fund(string $code): Fund
    {
        return Fund::query()->where('code', $code)->firstOrFail();
    }
}