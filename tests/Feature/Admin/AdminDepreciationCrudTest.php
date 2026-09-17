<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\DepreciationNote;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminDepreciationCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'depreciation-crud',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Depreciation CRUD',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function depreciationFund(?int $createdByAdminId = null): Fund
    {
        return Fund::query()->firstOrCreate(['code' => 'depreciation_fund'], [
            'name' => 'صندوق الإهلاك',
            'current_balance' => '0.00',
            'status' => 'active',
            'created_by_admin_id' => $createdByAdminId,
        ]);
    }

    private function note(Admin $admin): DepreciationNote
    {
        return DepreciationNote::query()->create([
            'amount' => '500.00',
            'rate' => '0.1000',
            'transaction_date' => '2026-01-31',
            'year' => 2026,
            'month' => 1,
            'description' => 'إهلاك شهر يناير',
            'created_by_admin_id' => $admin->id,
        ]);
    }

    public function test_write_routes_require_web_admin_session(): void
    {
        $admin = $this->admin();
        $this->depreciationFund($admin->id);
        $participant = Participant::factory()->create();
        $note = $this->note($admin);
        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '1000.00',
            'invested_at' => '2026-01-15',
            'status' => 'pending',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->postJson('/admin/depreciation', [])->assertStatus(302);
        $this->patchJson("/admin/depreciation/{$note->id}", [])->assertStatus(302);
        $this->deleteJson("/admin/depreciation/{$note->id}")->assertStatus(302);
        $this->patchJson("/admin/investments/{$investment->id}", [])->assertStatus(302);
        $this->deleteJson("/admin/investments/{$investment->id}")->assertStatus(302);
    }

    public function test_depreciation_page_renders_with_write_actions(): void
    {
        $admin = $this->admin();
        $this->depreciationFund($admin->id);
        $participant = Participant::factory()->create();

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/depreciation')
            ->assertOk()
            ->assertSee('modal-depreciation-create')
            ->assertSee('modal-depreciation-edit')
            ->assertSee($participant->first_name);
    }

    public function test_admin_can_create_depreciation_note(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create();
        $fund = $this->depreciationFund($admin->id);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/depreciation', [
                'amount' => '500.00',
                'rate' => '0.1000',
                'transaction_date' => '2026-01-31',
                'year' => 2026,
                'month' => 1,
                'description' => 'إهلاك شهر يناير',
                'participant_id' => $participant->id,
                'fund_id' => $fund->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('depreciation_notes', [
            'amount' => '500.00',
            'rate' => '0.1000',
            'year' => 2026,
            'month' => 1,
            'description' => 'إهلاك شهر يناير',
            'participant_id' => $participant->id,
            'fund_id' => $fund->id,
            'created_by_admin_id' => $admin->id,
        ]);
    }

    public function test_depreciation_create_defaults_to_depreciation_fund_when_omitted(): void
    {
        $admin = $this->admin();
        $fund = $this->depreciationFund($admin->id);

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/depreciation', [
                'amount' => '250.00',
                'rate' => '0.0500',
                'transaction_date' => '2026-02-15',
                'year' => 2026,
                'month' => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('depreciation_notes', [
            'fund_id' => $fund->id,
            'amount' => '250.00',
        ]);
    }

    public function test_depreciation_create_validates_required_fields(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/depreciation', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'rate', 'transaction_date', 'year', 'month']);

        $this->assertDatabaseCount('depreciation_notes', 0);
    }

    public function test_admin_can_update_depreciation_note(): void
    {
        $admin = $this->admin();
        $note = $this->note($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/depreciation/{$note->id}", [
                'amount' => '750.25',
                'rate' => '0.1200',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $note->refresh();
        $this->assertSame('750.25', (string) $note->amount);
        $this->assertSame('0.1200', (string) $note->rate);
        $this->assertSame('إهلاك شهر يناير', $note->description);
    }

    public function test_admin_can_delete_depreciation_note(): void
    {
        $admin = $this->admin();
        $note = $this->note($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/depreciation/{$note->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('depreciation_notes', 0);
    }
}