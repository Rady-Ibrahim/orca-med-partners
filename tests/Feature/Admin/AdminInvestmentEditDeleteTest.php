<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Investment;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminInvestmentEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'investment-crud',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Investment CRUD',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function pendingInvestment(Admin $admin): Investment
    {
        $participant = Participant::factory()->create();

        return Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '1000.00',
            'invested_at' => '2026-01-15',
            'status' => 'pending',
            'notes' => 'استثمار أول',
            'created_by_admin_id' => $admin->id,
        ]);
    }

    public function test_investments_page_renders_edit_and_delete_actions(): void
    {
        $admin = $this->admin();
        $this->pendingInvestment($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/investments')
            ->assertOk()
            ->assertSee('modal-investment-create')
            ->assertSee('modal-investment-edit')
            ->assertSee('تعديل')
            ->assertSee('حذف');
    }

    public function test_admin_can_edit_pending_investment_amount(): void
    {
        $admin = $this->admin();
        $investment = $this->pendingInvestment($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/investments/{$investment->id}", [
                'amount' => '2500.00',
                'notes' => 'تعديل المبلغ',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $investment->refresh();
        $this->assertSame('2500.00', (string) $investment->amount);
        $this->assertSame('تعديل المبلغ', $investment->notes);
        $this->assertSame('pending', $investment->status);
    }

    public function test_admin_can_delete_pending_investment(): void
    {
        $admin = $this->admin();
        $investment = $this->pendingInvestment($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/investments/{$investment->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('investments', 0);
    }

    public function test_approved_investment_can_be_edited(): void
    {
        $admin = $this->admin();
        $investment = $this->pendingInvestment($admin);
        $investment->update([
            'status' => 'approved',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/investments/{$investment->id}", [
                'amount' => '5000.00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('5000.00', (string) $investment->fresh()->amount);
    }

    public function test_approved_investment_can_be_deleted(): void
    {
        $admin = $this->admin();
        $investment = $this->pendingInvestment($admin);
        $investment->update([
            'status' => 'approved',
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/investments/{$investment->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('investments', ['id' => $investment->id]);
    }
}
