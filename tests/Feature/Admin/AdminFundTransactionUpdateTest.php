<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminFundTransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'fund-txn-edit',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Fund Txn Edit',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function fundWithTransaction(Admin $admin): array
    {
        $fund = Fund::query()->create([
            'code' => 'txn_fund_' . uniqid(),
            'name' => 'صندوق الحركات',
            'current_balance' => '200.00',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $fundTransaction = FundTransaction::query()->create([
            'fund_id' => $fund->id,
            'transaction_type' => 'withdrawal',
            'amount' => '200.00',
            'resulting_balance' => '200.00',
            'transaction_date' => '2026-09-17',
            'reference' => 'WDR-001',
            'description' => 'سحب',
            'created_by_admin_id' => $admin->id,
        ]);

        return [$fund, $fundTransaction];
    }

    public function test_transaction_update_requires_web_admin_session(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->patchJson("/admin/funds/{$fund->id}/transactions/{$fundTransaction->id}", [])
            ->assertStatus(302);
    }

    public function test_fund_transaction_metadata_can_be_edited(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$fund->id}/transactions/{$fundTransaction->id}", [
                'reference' => 'WDR-001-EDIT',
                'description' => 'سحب محدث',
                'notes' => 'ملاحظة جديدة',
                'transaction_date' => '2026-09-18',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fundTransaction->refresh();
        $this->assertSame('WDR-001-EDIT', $fundTransaction->reference);
        $this->assertSame('سحب محدث', $fundTransaction->description);
        $this->assertSame('ملاحظة جديدة', $fundTransaction->notes);
        $this->assertSame('2026-09-18', $fundTransaction->transaction_date?->toDateString());

        $this->assertSame('200.00', (string) $fundTransaction->amount);
        $this->assertSame('200.00', (string) $fundTransaction->resulting_balance);
        $this->assertSame('withdrawal', $fundTransaction->transaction_type);
        $this->assertSame('200.00', (string) $fund->fresh()->current_balance);
    }

    public function test_fund_transaction_amount_is_locked(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$fund->id}/transactions/{$fundTransaction->id}", [
                'amount' => '500.00',
            ])
            ->assertOk();

        $this->assertSame('200.00', (string) $fundTransaction->fresh()->amount);
        $this->assertSame('200.00', (string) $fund->fresh()->current_balance);
    }

    public function test_fund_transaction_type_and_balance_are_locked(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$fund->id}/transactions/{$fundTransaction->id}", [
                'transaction_type' => 'deposit',
                'resulting_balance' => '999.99',
            ])
            ->assertOk();

        $fresh = $fundTransaction->fresh();
        $this->assertSame('withdrawal', $fresh->transaction_type);
        $this->assertSame('200.00', (string) $fresh->resulting_balance);
    }

    public function test_model_level_guard_blocks_reordering_financial_fields(): void
    {
        $admin = $this->admin();
        [, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->expectException(\App\Domain\Financial\Exceptions\ImmutableFinancialRecordException::class);
        $fundTransaction->amount = '999.99';
        $fundTransaction->save();
    }

    public function test_transaction_update_rejects_transaction_not_in_fund(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);
        $otherFund = Fund::query()->create([
            'code' => 'other_fund_' . uniqid(),
            'name' => 'صندوق آخر',
            'current_balance' => '0.00',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/funds/{$otherFund->id}/transactions/{$fundTransaction->id}", [
                'description' => 'خربشة',
            ])
            ->assertStatus(404);
    }

    public function test_fund_transaction_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->deleteJson("/admin/funds/{$fund->id}/transactions/{$fundTransaction->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('fund_transactions', ['id' => $fundTransaction->id]);
    }

    public function test_funds_page_renders_transactions_list_and_edit_action(): void
    {
        $admin = $this->admin();
        [$fund, $fundTransaction] = $this->fundWithTransaction($admin);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/funds')
            ->assertOk()
            ->assertSee('modal-fund-txn-list-' . $fund->id)
            ->assertSee('modal-transaction-edit')
            ->assertSee('حركات الصندوق');
    }
}