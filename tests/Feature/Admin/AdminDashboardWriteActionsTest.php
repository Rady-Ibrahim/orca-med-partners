<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminDashboardWriteActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'dashboard-writer',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Dashboard Writer',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_write_routes_require_web_admin_session(): void
    {
        $this->postJson('/admin/monthly-profits', [])->assertStatus(302);
        $this->postJson('/admin/funds', [])->assertStatus(302);
        $this->postJson('/admin/settlements', [])->assertStatus(302);
        $this->postJson('/admin/capital', [])->assertStatus(302);
        $this->postJson('/admin/distribution-rules', [])->assertStatus(302);
    }

    public function test_wired_admin_pages_render_with_write_actions(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create();
        [$snapshot, $rule] = $this->financialContext($admin);
        $fund = Fund::query()->firstOrCreate(['code' => 'reserve_fund'], [
            'name' => 'Reserve', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id,
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/monthly-profits')
            ->assertOk()
            ->assertSee('modal-profit-create')
            ->assertSee('modal-profit-revise');

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/funds')
            ->assertOk()
            ->assertSee('modal-fund-create')
            ->assertSee('modal-fund-transaction');

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/settlements')
            ->assertOk()
            ->assertSee('modal-settlement-create')
            ->assertSee('modal-settlement-payment');

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/capital')
            ->assertOk()
            ->assertSee('modal-capital-create')
            ->assertSee($participant->first_name);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/distribution-rules')
            ->assertOk()
            ->assertSee('modal-rule-create')
            ->assertSee('modal-rule-edit');

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/participants')
            ->assertOk()
            ->assertSee('modal-participant-create')
            ->assertSee('modal-participant-edit')
            ->assertSee('revoke-tokens');
    }

    public function test_admin_can_create_approve_and_revise_monthly_profit_from_web(): void
    {
        $admin = $this->admin();
        [$snapshot, $rule] = $this->financialContext($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/monthly-profits', [
                'capital_snapshot_id' => $snapshot->id,
                'distribution_rule_id' => $rule->id,
                'gross_profit' => '100.00',
                'year' => 2026,
                'month' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $profit = MonthlyProfit::query()->where('year', 2026)->where('month', 1)->firstOrFail();
        $this->assertSame('draft', $profit->status);
        $this->assertSame('1', (string) $profit->version);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/monthly-profits/{$profit->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        $profit->refresh();
        $this->assertSame('approved', $profit->status);
        $this->assertSame($admin->id, $profit->approved_by_admin_id);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/monthly-profits/{$profit->id}/revision", [
                'gross_profit' => '110.00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $revision = MonthlyProfit::query()
            ->where('year', 2026)
            ->where('month', 1)
            ->where('status', 'draft')
            ->firstOrFail();
        $this->assertSame('2', (string) $revision->version);
        $profit->refresh();
        $this->assertSame('superseded', $profit->status);
    }

    public function test_admin_can_create_fund_and_record_transaction_from_web(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/funds', [
                'code' => 'reserve_fund',
                'name' => 'صندوق الاحتياط',
                'status' => 'active',
                'description' => 'احتياط تشغيلي',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $fund = Fund::query()->where('code', 'reserve_fund')->firstOrFail();
        $this->assertSame('0.00', (string) $fund->current_balance);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/funds/{$fund->id}/transactions", [
                'transaction_type' => 'deposit',
                'amount' => '250.50',
                'transaction_date' => '2026-01-15',
                'reference' => 'WEB-DEP-001',
                'description' => 'إيداع يدوي',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame('250.50', (string) $fund->fresh()->current_balance);
        $this->assertDatabaseHas('fund_transactions', [
            'fund_id' => $fund->id,
            'transaction_type' => 'deposit',
            'amount' => '250.50',
            'reference' => 'WEB-DEP-001',
        ]);
    }

    public function test_admin_can_run_settlement_lifecycle_from_web(): void
    {
        $admin = $this->admin();
        [$snapshot, $rule] = $this->financialContext($admin);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 2);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/settlements', ['year' => 2026])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $settlement = Settlement::query()->where('year', 2026)->firstOrFail();
        $this->assertSame('draft', $settlement->status);
        $this->assertSame('75.00', (string) $settlement->amount_due);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/settlements/{$settlement->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        $settlement->refresh();
        $this->assertSame('approved', $settlement->status);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/settlements/{$settlement->id}/payments", [
                'amount' => '75.00',
                'paid_at' => '2026-02-28',
                'payment_source' => 'bank',
                'payment_method' => 'تحويل بنكي',
                'reference' => 'TRF-2026-0001',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $settlement->refresh();
        $this->assertSame('paid', $settlement->status);
        $this->assertSame('75.00', (string) $settlement->paid_amount);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/settlements/{$settlement->id}/revision")
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $revision = Settlement::query()
            ->where('year', 2026)
            ->where('id', '<>', $settlement->id)
            ->firstOrFail();
        $this->assertSame('2', (string) $revision->version);
    }

    public function test_admin_can_create_capital_snapshot_with_participant_rows_from_web(): void
    {
        $admin = $this->admin();
        $first = Participant::factory()->create();
        $second = Participant::factory()->create();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/capital', [
                'snapshot_date' => '2026-03-01',
                'year' => 2026,
                'month' => 3,
                'items' => [
                    ['participant_id' => $first->id, 'capital' => '750.25'],
                    ['participant_id' => $second->id, 'capital' => '249.75'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $snapshot = CapitalSnapshot::query()->where('year', 2026)->where('month', 3)->firstOrFail();
        $this->assertSame('1000.00', (string) $snapshot->total_capital);
        $this->assertSame(2, $snapshot->items()->count());
        $this->assertSame('0.7502', (string) $snapshot->items()->where('participant_id', $first->id)->firstOrFail()->participant_ratio_snapshot);
    }

    public function test_admin_can_create_and_update_distribution_rule_from_web(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/distribution-rules', [
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'management_fee_rate' => '0.2500',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate' => '0.0250',
                'incentive_fund_rate' => '0.0250',
                'distributed_share_rate' => '0.6500',
                'status' => 'draft',
                'notes' => 'قاعدة 2026',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $rule = DistributionRule::query()->where('notes', 'قاعدة 2026')->firstOrFail();

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/distribution-rules/{$rule->id}", [
                'management_fee_rate' => '0.2000',
                'distributed_share_rate' => '0.7000',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $rule->refresh();
        $this->assertSame('0.2000', (string) $rule->management_fee_rate);
        $this->assertSame('0.7000', (string) $rule->distributed_share_rate);
        $this->assertSame('active', $rule->status);
    }

    public function test_admin_can_revoke_participant_tokens_from_web(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create();
        $participant->createToken('mobile');
        $this->assertSame(1, $participant->tokens()->count());

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson("/admin/participants/{$participant->id}/revoke-tokens")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $participant->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'action' => 'participant_tokens_revoked',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_distribution_rule_with_non_zero_sum_is_rejected(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/distribution-rules', [
                'effective_from' => '2026-01-01',
                'management_fee_rate' => '0.9000',
                'depreciation_fund_rate' => '0.9000',
                'growth_fund_rate' => '0.9000',
                'incentive_fund_rate' => '0.9000',
                'distributed_share_rate' => '0.9000',
                'status' => 'draft',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('distribution_rules', 0);
    }

    /** @return array{CapitalSnapshot, DistributionRule} */
    private function financialContext(Admin $admin): array
    {
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-01-28',
            'year' => 2026,
            'month' => 1,
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
            'effective_from' => '2026-01-01',
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

        return [$snapshot, $rule];
    }
}