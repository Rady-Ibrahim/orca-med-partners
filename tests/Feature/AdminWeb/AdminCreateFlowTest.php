<?php

declare(strict_types=1);

namespace Tests\Feature\AdminWeb;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'create-flow-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Create Flow Admin',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    /** @return array{Admin, CapitalSnapshot, DistributionRule, Participant} */
    private function financialContext(int $year, int $month): array
    {
        $admin = $this->admin();
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

    public function test_all_admin_pages_render_for_logged_in_admin(): void
    {
        [$admin] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/participants')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/investments')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/capital')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/monthly-profits')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/distribution-rules')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/funds')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/settlements')->assertOk();
    }

    public function test_capital_create_via_web_ajax_returns_201(): void
    {
        [$admin,, , $participant] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/capital', [
                'snapshot_date' => '2026-01-31',
                'year' => 2026,
                'month' => 1,
                'items' => [
                    ['participant_id' => $participant->id, 'capital' => '500.00'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('capital_snapshots', ['total_capital' => '500.00']);
    }

    public function test_distribution_rule_create_via_web_ajax_returns_201(): void
    {
        [$admin, , $existing] = $this->financialContext(2026, 1);
        $existing->update(['effective_to' => '2026-02-28']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/distribution-rules', [
                'effective_from' => '2026-03-01',
                'management_fee_rate' => '0.25',
                'depreciation_fund_rate' => '0.05',
                'growth_fund_rate' => '0.025',
                'incentive_fund_rate' => '0.025',
                'distributed_share_rate' => '0.65',
                'status' => 'active',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('distribution_rules', ['status' => 'active', 'effective_to' => null]);
    }

    public function test_new_open_ended_active_rule_overlapping_existing_is_rejected_with_friendly_422(): void
    {
        [$admin] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/distribution-rules', [
                'effective_from' => '2026-03-01',
                'management_fee_rate' => '0.25',
                'depreciation_fund_rate' => '0.05',
                'growth_fund_rate' => '0.025',
                'incentive_fund_rate' => '0.025',
                'distributed_share_rate' => '0.65',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Overlapping active distribution rule periods are not allowed.',
            ]);
    }

    public function test_fund_create_via_web_ajax_returns_201(): void
    {
        [$admin] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/funds', [
                'code' => 'reserve_fund',
                'name' => 'Reserve Fund',
                'status' => 'active',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('funds', ['code' => 'reserve_fund']);
    }

    public function test_monthly_profit_create_via_web_ajax_returns_201(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2026, 4);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/monthly-profits', [
                'capital_snapshot_id' => $snapshot->id,
                'distribution_rule_id' => $rule->id,
                'gross_profit' => '1000.00',
                'year' => 2026,
                'month' => 4,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', fn ($id) => is_int($id));

        $this->assertDatabaseHas('monthly_profits', ['status' => 'draft']);
    }

    public function test_settlement_create_via_web_ajax_returns_201(): void
    {
        [$admin, $snapshot, $rule] = $this->financialContext(2025, 12);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '1000.00', 2025, 12);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/settlements', ['year' => 2025])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('settlements', ['year' => 2025, 'status' => 'draft']);
    }

    public function test_create_validation_errors_return_422_json_like_the_ajax_handler_expects(): void
    {
        [$admin] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/capital', [
                'snapshot_date' => '2026-01-31',
                'year' => 2026,
                'month' => 1,
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_investment_create_via_web_ajax_returns_201(): void
    {
        [$admin, , , $participant] = $this->financialContext(2026, 1);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/investments', [
                'participant_id' => $participant->id,
                'amount' => '2500.00',
                'invested_at' => '2026-01-15',
                'notes' => 'استثمار جديد',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('investments', [
            'participant_id' => $participant->id,
            'amount' => '2500.00',
            'status' => 'pending',
            'created_by_admin_id' => $admin->id,
        ]);
    }

    public function test_investment_approve_via_web_ajax_marks_investment_approved(): void
    {
        [$admin, , , $participant] = $this->financialContext(2026, 1);
        $investment = \App\Models\Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '5000.00',
            'invested_at' => '2026-01-20',
            'status' => 'pending',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/investments/{$investment->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('investments', ['id' => $investment->id, 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'investment',
            'auditable_id' => $investment->id,
            'action' => 'investment_approved',
            'actor_id' => $admin->id,
        ]);
    }
}