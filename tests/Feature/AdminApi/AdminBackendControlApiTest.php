<?php

declare(strict_types=1);

namespace Tests\Feature\AdminApi;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminBackendControlApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = Admin::factory()->create([
            'username' => 'api-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        return $admin->createToken('admin-api', ['*'])->plainTextToken;
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/v1/admin/capital')->assertStatus(401);
        $this->getJson('/api/v1/admin/depreciation')->assertStatus(401);
        $this->getJson('/api/v1/admin/distribution-rules')->assertStatus(401);
        $this->getJson('/api/v1/admin/settings')->assertStatus(401);
        $this->getJson('/api/v1/admin/monthly-profits')->assertStatus(401);
    }

    public function test_participant_listing_supports_search_and_pagination(): void
    {
        $token = $this->adminToken();
        Participant::factory()->create(['username' => 'unique.search.target']);
        Participant::factory()->create();

        $this->withToken($token)
            ->getJson('/api/v1/admin/participants?search=unique.search.target')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.username', 'unique.search.target');

        $this->withToken($token)
            ->getJson('/api/v1/admin/participants?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.per_page', 1);
    }

    public function test_capital_snapshot_crud(): void
    {
        $token = $this->adminToken();
        $first = Participant::factory()->create();
        $second = Participant::factory()->create();

        $create = $this->withToken($token)->postJson('/api/v1/admin/capital', [
            'snapshot_date' => '2026-05-31',
            'year' => 2026,
            'month' => 5,
            'items' => [
                ['participant_id' => $first->id, 'capital' => '60000.00'],
                ['participant_id' => $second->id, 'capital' => '40000.00'],
            ],
        ]);

        $create->assertStatus(201)->assertJsonPath('success', true);
        $snapshotId = $create->json('data.id');

        $this->assertDatabaseHas('capital_snapshots', [
            'id' => $snapshotId,
            'total_capital' => '100000.00',
            'year' => 2026,
            'month' => 5,
            'status' => 'final',
        ]);

        $this->assertDatabaseHas('capital_snapshot_items', [
            'capital_snapshot_id' => $snapshotId,
            'participant_id' => $first->id,
            'participant_capital_snapshot' => '60000.00',
            'participant_ratio_snapshot' => '0.6000',
        ]);
        $this->assertDatabaseHas('capital_snapshot_items', [
            'capital_snapshot_id' => $snapshotId,
            'participant_id' => $second->id,
            'participant_capital_snapshot' => '40000.00',
            'participant_ratio_snapshot' => '0.4000',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/capital?year=2026&month=5')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/capital/{$snapshotId}", [
                'items' => [
                    ['participant_id' => $first->id, 'capital' => '75000.00'],
                    ['participant_id' => $second->id, 'capital' => '25000.00'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.total_capital', '100000.00');

        $this->assertDatabaseHas('capital_snapshot_items', [
            'capital_snapshot_id' => $snapshotId,
            'participant_id' => $first->id,
            'participant_ratio_snapshot' => '0.7500',
        ]);
    }

    public function test_capital_can_be_updated_when_used_by_approved_profit(): void
    {
        $token = $this->adminToken();
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-06-28',
            'year' => 2026,
            'month' => 6,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => Admin::query()->first()->id,
        ]);
        $snapshot->items()->create([
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => '100.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/capital/{$snapshot->id}", ['total_capital' => '200.00'])
            ->assertOk();

        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-06-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
        ]);
        $profit = app(CreateMonthlyProfitAction::class)->execute(Admin::query()->first(), $snapshot, $rule, '100.00', 2026, 6);
        app(ApproveMonthlyProfitAction::class)->execute(Admin::query()->first(), $profit);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/capital/{$snapshot->id}", ['total_capital' => '300.00'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_capital', '300.00');
    }

    public function test_depreciation_notes_crud_and_approved_link_guard(): void
    {
        $token = $this->adminToken();
        Fund::query()->create(['code' => 'depreciation_fund', 'name' => 'Depreciation Fund', 'current_balance' => '0.00', 'status' => 'active']);

        $store = $this->withToken($token)->postJson('/api/v1/admin/depreciation', [
            'amount' => '500.00',
            'rate' => '0.0500',
            'transaction_date' => '2026-07-31',
            'year' => 2026,
            'month' => 7,
            'description' => 'Monthly depreciation',
        ]);

        $store->assertStatus(201)->assertJsonPath('success', true);
        $noteId = $store->json('data.id');
        $this->assertDatabaseHas('depreciation_notes', ['id' => $noteId, 'amount' => '500.00']);
        $this->assertSame('depreciation_fund', Fund::query()->find($store->json('data.fund_id'))->code);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/depreciation/{$noteId}", ['description' => 'Updated', 'amount' => '600.00'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated')
            ->assertJsonPath('data.amount', '600.00');

        $this->withToken($token)
            ->getJson('/api/v1/admin/depreciation?year=2026')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/depreciation/{$noteId}")
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('depreciation_notes', ['id' => $noteId]);
    }

    public function test_depreciation_linked_to_approved_profit_can_be_deleted_via_api(): void
    {
        $token = $this->adminToken();
        $admin = Admin::query()->firstOrFail();
        Fund::query()->create(['code' => 'growth_fund', 'name' => 'Growth Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);
        Fund::query()->create(['code' => 'incentive_fund', 'name' => 'Incentive Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);
        Fund::query()->create(['code' => 'depreciation_fund', 'name' => 'Depreciation Fund', 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id]);
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-08-28',
            'year' => 2026,
            'month' => 8,
            'total_capital' => '100.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        $snapshot->items()->create(['participant_id' => $participant->id, 'participant_capital_snapshot' => '100.00', 'participant_ratio_snapshot' => '1.0000']);
        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-08-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
        ]);
        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100.00', 2026, 8);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $note = DepreciationNote::query()->where('monthly_profit_id', $profit->id)->firstOrFail();

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/depreciation/{$note->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('depreciation_notes', ['id' => $note->id]);
    }

    public function test_distribution_rules_index_store_show_update(): void
    {
        $token = $this->adminToken();

        $store = $this->withToken($token)->postJson('/api/v1/admin/distribution-rules', [
            'effective_from' => '2026-09-01',
            'management_fee_rate' => '0.2000',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.7000',
            'status' => 'draft',
            'notes' => 'New ruleset',
        ]);

        $store->assertStatus(201)->assertJsonPath('success', true);
        $ruleId = $store->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/admin/distribution-rules')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)
            ->getJson("/api/v1/admin/distribution-rules/{$ruleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $ruleId);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/distribution-rules/{$ruleId}", [
                'status' => 'active',
                'distributed_share_rate' => '0.6800',
                'management_fee_rate' => '0.2200',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.distributed_share_rate', '0.6800');
    }

    public function test_distribution_rule_rejects_rates_that_do_not_sum_to_one(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson('/api/v1/admin/distribution-rules', [
                'effective_from' => '2026-10-01',
                'management_fee_rate' => '0.9000',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate' => '0.0250',
                'incentive_fund_rate' => '0.0250',
                'distributed_share_rate' => '0.6500',
                'status' => 'draft',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_settings_get_and_update(): void
    {
        $token = $this->adminToken();
        AppSetting::query()->create(['key' => 'company_name', 'value' => 'Orca Med Partners', 'description' => 'Compañía']);

        $this->withToken($token)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Orca Med Partners');

        $this->withToken($token)
            ->putJson('/api/v1/admin/settings', [
                'settings' => [
                    'company_name' => 'Orca Med',
                    'support_email' => 'support@orcam.com',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Orca Med')
            ->assertJsonPath('data.support_email', 'support@orcam.com');

        $this->assertSame('"Orca Med"', DB::table('app_settings')->where('key', 'company_name')->value('value'));
    }

    public function test_admin_api_unified_error_envelope(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson('/api/v1/admin/capital', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);

        $this->withToken($token)
            ->patchJson('/api/v1/admin/capital/999999', [])
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message']);
    }

    public function test_employee_admin_without_permission_is_forbidden(): void
    {
        $employee = Admin::factory()->create([
            'username' => 'limited-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => false,
            'permissions' => ['participants.view'],
        ]);
        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/participants')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/admin/capital', ['year' => 2026, 'month' => 1, 'snapshot_date' => '2026-01-31'])->assertForbidden();
    }
}
