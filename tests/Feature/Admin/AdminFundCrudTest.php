<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminFundCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'fund-crud',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Fund CRUD',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function fund(Admin $admin, array $overrides = []): Fund
    {
        return Fund::query()->create(array_merge([
            'code' => 'test_fund_' . uniqid(),
            'name' => 'صندوق اختبار',
            'current_balance' => '0.00',
            'status' => 'active',
            'description' => 'وصف',
            'created_by_admin_id' => $admin->id,
        ], $overrides));
    }

    private function fundWithTransaction(Admin $admin): Fund
    {
        $fund = $this->fund($admin);
        FundTransaction::query()->create([
            'fund_id' => $fund->id,
            'transaction_type' => 'deposit',
            'amount' => '500.00',
            'resulting_balance' => '500.00',
            'transaction_date' => '2026-01-15',
            'reference' => 'TEST-001',
            'description' => 'إيداع',
            'created_by_admin_id' => $admin->id,
        ]);

        return $fund;
    }

    public function test_fund_write_routes_require_web_admin_session(): void
    {
        $admin = $this->admin();
        $fund = $this->fund($admin);

        $this->patchJson("/admin/funds/{$fund->id}", [])->assertStatus(302);
        $this->deleteJson("/admin/funds/{$fund->id}")->assertStatus(302);
    }

    public function test_funds_page_renders_edit_and_delete_actions(): void
    {
        $admin = $this->admin();
        $this->fund($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/funds')
            ->assertOk()
            ->assertSee('modal-fund-create')
            ->assertSee('modal-fund-edit')
            ->assertSee('تعديل')
            ->assertSee('حذف');
    }

    public function test_admin_can_update_fund_details(): void
    {
        $admin = $this->admin();
        $fund = $this->fund($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$fund->id}", [
                'name' => 'صندوق محدث',
                'status' => 'inactive',
                'description' => 'وصف محدث',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fund->refresh();
        $this->assertSame('صندوق محدث', $fund->name);
        $this->assertSame('inactive', $fund->status);
        $this->assertSame('وصف محدث', $fund->description);
        $this->assertSame('0.00', (string) $fund->current_balance);
    }

    public function test_admin_can_delete_fund_without_transactions(): void
    {
        $admin = $this->admin();
        $fund = $this->fund($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/funds/{$fund->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('funds', ['id' => $fund->id]);
    }

    public function test_fund_with_transactions_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $fund = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/funds/{$fund->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('funds', ['id' => $fund->id]);
    }

    public function test_system_fund_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $fund = $this->fund($admin, ['code' => 'depreciation_fund', 'name' => 'صندوق الإهلاك']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/funds/{$fund->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('funds', ['id' => $fund->id]);
    }

    public function test_system_fund_can_still_be_edited(): void
    {
        $admin = $this->admin();
        $fund = $this->fund($admin, ['code' => 'depreciation_fund', 'name' => 'صندوق الإهلاك']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$fund->id}", [
                'name' => 'صندوق الإهلاك الوحيد',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('صندوق الإهلاك الوحيد', $fund->fresh()->name);
    }
}