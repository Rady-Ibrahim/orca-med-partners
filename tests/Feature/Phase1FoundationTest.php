<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_rule_validation_accepts_approved_rates(): void
    {
        DistributionRuleValidator::validate([
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6500,
        ]);

        $this->assertTrue(true);
    }

    public function test_distribution_rule_validation_rejects_total_not_equal_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DistributionRuleValidator::validate([
            'management_fee_rate' => 0.2500,
            'depreciation_fund_rate' => 0.0500,
            'growth_fund_rate' => 0.0250,
            'incentive_fund_rate' => 0.0250,
            'distributed_share_rate' => 0.6000,
        ]);
    }

    public function test_active_distribution_rule_returns_latest_effective_rule(): void
    {
        $admin = Admin::factory()->create();

        DistributionRule::query()->create([
            'effective_from' => '2024-01-01',
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

        $rule = app(\App\Domain\Financial\Services\DistributionRuleService::class)->activeRule(now());

        $this->assertNotNull($rule);
        $this->assertSame('0.2500', (string) $rule->management_fee_rate);
    }

    public function test_snapshot_can_attach_participant_items(): void
    {
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2025-01-31',
            'year' => 2025,
            'month' => 1,
            'total_capital' => 10000.00,
            'status' => 'final',
        ]);

        $snapshot->items()->create([
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => 4000.00,
            'participant_ratio_snapshot' => 0.4000,
        ]);

        $this->assertSame(1, $snapshot->items()->count());
        $this->assertSame(4000.00, (float) $snapshot->fresh()->items()->first()->participant_capital_snapshot);
    }

    public function test_approved_financial_records_are_not_soft_deleted(): void
    {
        $admin = Admin::factory()->create();

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

        $this->assertDatabaseHas('distribution_rules', ['status' => 'active']);
        $this->assertTrue(DB::table('distribution_rules')->where('status', 'active')->exists());
    }
}
