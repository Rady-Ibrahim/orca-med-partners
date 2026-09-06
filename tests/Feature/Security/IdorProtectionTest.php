<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\Settlement;
use App\Models\SettlementItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_cannot_access_another_participants_investment(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-investment', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-investment', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $investment = Investment::query()->create([
            'participant_id' => $owner->id,
            'amount' => 2500,
            'invested_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/investments/' . $investment->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_capital_record(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-capital', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-capital', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => now()->toDateString(),
            'year' => now()->year,
            'month' => now()->month,
            'total_capital' => 15000,
            'status' => 'final',
        ]);

        $capital = CapitalSnapshotItem::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'participant_id' => $owner->id,
            'participant_capital_snapshot' => 15000,
            'participant_ratio_snapshot' => 1.0000,
            'calculation_metadata' => ['source' => 'test'],
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/capital/' . $capital->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_profit_allocation(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-profit', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-profit', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $rule = DistributionRule::query()->create([
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'management_fee_rate' => 0.10,
            'depreciation_fund_rate' => 0.10,
            'growth_fund_rate' => 0.20,
            'incentive_fund_rate' => 0.20,
            'distributed_share_rate' => 0.40,
            'status' => 'active',
            'is_default' => true,
            'notes' => 'test rule',
        ]);

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => now()->toDateString(),
            'year' => now()->year,
            'month' => now()->month,
            'total_capital' => 12000,
            'status' => 'final',
        ]);

        $monthlyProfit = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => now()->year,
            'month' => now()->month,
            'version' => 1,
            'status' => 'draft',
            'gross_profit' => 1200,
            'management_amount' => 120,
            'depreciation_amount' => 120,
            'growth_amount' => 240,
            'incentive_amount' => 240,
            'distributed_amount' => 480,
            'rounding_delta_adjustment' => 0,
            'notes' => 'test profit',
        ]);

        $profit = ParticipantProfitAllocation::query()->create([
            'monthly_profit_id' => $monthlyProfit->id,
            'participant_id' => $owner->id,
            'amount' => 480,
            'share_ratio' => 0.4000,
            'status' => 'approved',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/profits/' . $profit->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_fund_allocation(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-fund', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-fund', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $fund = Fund::query()->create([
            'code' => 'FUND-' . time(),
            'name' => 'Growth Fund',
            'current_balance' => 5000,
            'status' => 'active',
            'description' => 'test fund',
        ]);

        $rule = DistributionRule::query()->create([
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'management_fee_rate' => 0.10,
            'depreciation_fund_rate' => 0.10,
            'growth_fund_rate' => 0.20,
            'incentive_fund_rate' => 0.20,
            'distributed_share_rate' => 0.40,
            'status' => 'active',
            'is_default' => true,
            'notes' => 'test rule',
        ]);

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => now()->toDateString(),
            'year' => now()->year,
            'month' => now()->month,
            'total_capital' => 8000,
            'status' => 'final',
        ]);

        $monthlyProfit = MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => now()->year,
            'month' => now()->month,
            'version' => 1,
            'status' => 'draft',
            'gross_profit' => 600,
            'management_amount' => 60,
            'depreciation_amount' => 60,
            'growth_amount' => 120,
            'incentive_amount' => 120,
            'distributed_amount' => 240,
            'rounding_delta_adjustment' => 0,
            'notes' => 'test',
        ]);

        $allocation = ParticipantFundAllocation::query()->create([
            'fund_id' => $fund->id,
            'monthly_profit_id' => $monthlyProfit->id,
            'participant_id' => $owner->id,
            'amount' => 240,
            'allocation_type' => 'growth',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/funds/' . $allocation->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_depreciation_record(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-depreciation', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-depreciation', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $fund = Fund::query()->create([
            'code' => 'DEPR-' . time(),
            'name' => 'Depreciation Fund',
            'current_balance' => 2000,
            'status' => 'active',
            'description' => 'depreciation fund',
        ]);

        $depreciation = DepreciationNote::query()->create([
            'participant_id' => $owner->id,
            'fund_id' => $fund->id,
            'monthly_profit_id' => null,
            'amount' => 100,
            'rate' => 0.0500,
            'transaction_date' => now()->toDateString(),
            'year' => now()->year,
            'month' => now()->month,
            'description' => 'test depreciation',
            'admin_note' => 'managed',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/depreciation/' . $depreciation->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_settlement_item(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-settlement', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-settlement', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $settlement = Settlement::query()->create([
            'year' => now()->year,
            'version' => 1,
            'status' => 'draft',
            'total_distributed_amount' => 0,
            'participant_profit_share' => 0,
            'participant_fund_share' => 0,
            'net_payable' => 0,
            'amount_due' => 0,
            'paid_amount' => 0,
        ]);

        $item = SettlementItem::query()->create([
            'settlement_id' => $settlement->id,
            'participant_id' => $owner->id,
            'profit_share' => 0,
            'fund_share' => 0,
            'net_payable' => 0,
            'payment_status' => 'pending',
            'paid_amount' => 0,
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/settlements/' . $item->id);

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_notification(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-notify', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-notify', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $notification = Notification::query()->create([
            'participant_id' => $owner->id,
            'type' => 'info',
            'title' => 'Private notice',
            'body' => 'This is not your notification.',
            'is_read' => false,
            'metadata' => ['source' => 'test'],
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/notifications/' . $notification->id);

        $response->assertStatus(403);
    }
}
