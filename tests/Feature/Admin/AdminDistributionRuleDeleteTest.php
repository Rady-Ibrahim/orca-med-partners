<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminDistributionRuleDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'rule-delete',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Rule Delete',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function rule(Admin $admin, array $overrides = []): DistributionRule
    {
        return DistributionRule::query()->create(array_merge([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.1000',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.1000',
            'incentive_fund_rate' => '0.0500',
            'distributed_share_rate' => '0.7000',
            'status' => 'draft',
            'is_default' => false,
            'notes' => 'قاعدة اختبار',
            'created_by_admin_id' => $admin->id,
        ], $overrides));
    }

    public function test_distribution_rule_delete_requires_web_admin_session(): void
    {
        $admin = $this->admin();
        $rule = $this->rule($admin);

        $this->deleteJson("/admin/distribution-rules/{$rule->id}")->assertStatus(302);
    }

    public function test_distribution_rules_page_renders_delete_action(): void
    {
        $admin = $this->admin();
        $this->rule($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/distribution-rules')
            ->assertOk()
            ->assertSee('تعديل')
            ->assertSee('حذف');
    }

    public function test_admin_can_delete_unreferenced_distribution_rule(): void
    {
        $admin = $this->admin();
        $rule = $this->rule($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/distribution-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('distribution_rules', 0);
    }

    public function test_distribution_rule_referenced_by_monthly_profit_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $rule = $this->rule($admin);
        $participant = \App\Models\Participant::factory()->create();
        $snapshot = \App\Models\CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-01-31',
            'year' => 2026,
            'month' => 1,
            'total_capital' => '10000.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        \App\Models\CapitalSnapshotItem::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => '10000.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);

        MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => 2026,
            'month' => 1,
            'version' => 1,
            'status' => 'draft',
            'gross_profit' => '5000.00',
            'management_amount' => '0.00',
            'depreciation_amount' => '0.00',
            'growth_amount' => '0.00',
            'incentive_amount' => '0.00',
            'distributed_amount' => '0.00',
            'rounding_delta_adjustment' => '0.00',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/distribution-rules/{$rule->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('distribution_rules', ['id' => $rule->id]);
    }
}