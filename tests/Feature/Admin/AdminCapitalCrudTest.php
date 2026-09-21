<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminCapitalCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'capital-crud',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Capital CRUD',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function rule(Admin $admin): DistributionRule
    {
        return DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.1000',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.1000',
            'incentive_fund_rate' => '0.0500',
            'distributed_share_rate' => '0.7000',
            'status' => 'active',
            'is_default' => true,
            'created_by_admin_id' => $admin->id,
        ]);
    }

    private function snapshot(Admin $admin): CapitalSnapshot
    {
        $participant = Participant::factory()->create();
        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-01-31',
            'year' => 2026,
            'month' => 1,
            'total_capital' => '10000.00',
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);
        CapitalSnapshotItem::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'participant_id' => $participant->id,
            'participant_capital_snapshot' => '10000.00',
            'participant_ratio_snapshot' => '1.0000',
        ]);

        return $snapshot;
    }

    public function test_capital_write_routes_require_web_admin_session(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);

        $this->patchJson("/admin/capital/{$snapshot->id}", [])->assertStatus(302);
        $this->deleteJson("/admin/capital/{$snapshot->id}")->assertStatus(302);
    }

    public function test_capital_page_renders_edit_and_delete_actions(): void
    {
        $admin = $this->admin();
        $this->snapshot($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/capital')
            ->assertOk()
            ->assertSee('modal-capital-create')
            ->assertSee('modal-capital-edit')
            ->assertSee('تعديل')
            ->assertSee('حذف');
    }

    public function test_admin_can_update_capital_snapshot_header(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'snapshot_date' => '2026-02-15',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $snapshot->refresh();
        $this->assertSame(2, (int) $snapshot->month);
        $this->assertSame(2026, (int) $snapshot->year);
        $this->assertSame('10000.00', (string) $snapshot->total_capital);
    }

    public function test_admin_can_update_capital_snapshot_items(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);
        $participantB = Participant::factory()->create();
        $existingItem = $snapshot->items()->first();

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $existingItem->participant_id, 'capital' => '3000.00'],
                    ['participant_id' => $participantB->id, 'capital' => '7000.00'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $snapshot->refresh();
        $this->assertSame('10000.00', (string) $snapshot->total_capital);
        $this->assertSame(0.3, round((float) $snapshot->items()->firstWhere('participant_id', $existingItem->participant_id)->participant_ratio_snapshot, 4));
    }

    public function test_admin_can_delete_capital_snapshot(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/capital/{$snapshot->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('capital_snapshots', 0);
    }

    public function test_capital_snapshot_used_by_approved_profit_can_be_updated(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);
        $this->createApprovedProfitFor($snapshot);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'snapshot_date' => '2026-03-20',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $snapshot->refresh();
        $this->assertSame(3, (int) $snapshot->month);
    }

    public function test_capital_snapshot_used_by_approved_profit_can_be_deleted(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin);
        $this->createApprovedProfitFor($snapshot);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/capital/{$snapshot->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('capital_snapshots', ['id' => $snapshot->id]);
    }

    private function createApprovedProfitFor(CapitalSnapshot $snapshot): MonthlyProfit
    {
        $admin = Admin::query()->find($snapshot->created_by_admin_id);
        $rule = $this->rule($admin);

        return MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'year' => $snapshot->year,
            'month' => $snapshot->month,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => '5000.00',
            'management_amount' => '0.00',
            'depreciation_amount' => '0.00',
            'growth_amount' => '0.00',
            'incentive_amount' => '0.00',
            'distributed_amount' => '0.00',
            'rounding_delta_adjustment' => '0.00',
            'created_by_admin_id' => $snapshot->created_by_admin_id,
            'approved_by_admin_id' => $snapshot->created_by_admin_id,
            'approved_at' => now(),
        ]);
    }
}
