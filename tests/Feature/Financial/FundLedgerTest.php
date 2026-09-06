<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Domain\Financial\Exceptions\FundBalanceDriftException;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Services\FundBalanceService;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FundLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_read_fund_and_audit_operations(): void
    {
        $admin = $this->adminWithFundPermission();
        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/admin/funds', [
            'code' => 'DEP-001',
            'name' => 'Depreciation Fund',
            'description' => 'Operational fund',
        ])->assertCreated()->json('data');

        $this->withToken($token)->getJson('/api/admin/funds/' . $created['id'])->assertOk();
        $this->withToken($token)->patchJson('/api/admin/funds/' . $created['id'], [
            'name' => 'Updated Depreciation Fund',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_updated']);
    }

    public function test_deposit_withdrawal_and_adjustment_are_atomic_and_reconciled(): void
    {
        $admin = $this->adminWithFundPermission();
        $fund = Fund::query()->create(['code' => 'LEDGER-001', 'name' => 'Ledger Fund', 'status' => 'active']);
        $service = app(FundBalanceService::class);

        $deposit = $service->applyTransaction($fund, '100.10', FundTransactionType::DEPOSIT, createdByAdminId: $admin->id, actor: $admin);
        $withdrawal = $service->applyTransaction($fund, '25.10', FundTransactionType::WITHDRAWAL, createdByAdminId: $admin->id, actor: $admin);
        $adjustment = $service->applyTransaction($fund, '5.00', FundTransactionType::ADJUSTMENT, createdByAdminId: $admin->id, actor: $admin);

        self::assertSame('100.10', (string) $deposit->resulting_balance);
        self::assertSame('75.00', (string) $withdrawal->resulting_balance);
        self::assertSame('80.00', (string) $adjustment->resulting_balance);
        self::assertSame('80.00', (string) $fund->fresh()->current_balance);
        self::assertTrue($service->reconcile($fund->fresh())['is_consistent']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_deposit']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_withdrawal']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_adjustment']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fund_balance_changed']);
    }

    public function test_invalid_amounts_are_rejected_without_persisting_transactions(): void
    {
        $fund = Fund::query()->create(['code' => 'VALIDATE-001', 'name' => 'Validation Fund']);
        $service = app(FundBalanceService::class);

        foreach (['0', '0.00', '-1.00', '1.001', 'not-money'] as $amount) {
            try {
                $service->applyTransaction($fund, $amount, FundTransactionType::DEPOSIT);
                self::fail('Invalid amount was accepted: ' . $amount);
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }

        self::assertDatabaseCount('fund_transactions', 0);
    }

    public function test_failed_transaction_creation_rolls_back_balance_and_transaction(): void
    {
        $fund = Fund::query()->create(['code' => 'ROLLBACK-001', 'name' => 'Rollback Fund']);
        $service = app(FundBalanceService::class);

        $this->expectException(\Illuminate\Database\QueryException::class);
        try {
            $service->applyTransaction($fund, '50.00', FundTransactionType::DEPOSIT, monthlyProfitId: 999999);
        } finally {
            self::assertSame('0.00', (string) $fund->fresh()->current_balance);
            self::assertDatabaseCount('fund_transactions', 0);
        }
    }

    public function test_fund_transactions_are_immutable_and_drift_is_detected(): void
    {
        $fund = Fund::query()->create(['code' => 'DRIFT-001', 'name' => 'Drift Fund']);
        $transaction = app(FundBalanceService::class)->applyTransaction($fund, '10.00', FundTransactionType::DEPOSIT);

        $this->expectException(ImmutableFinancialRecordException::class);
        $transaction->amount = '20.00';
        $transaction->save();
    }

    public function test_reconciliation_detects_intentional_balance_drift(): void
    {
        $fund = Fund::query()->create(['code' => 'DRIFT-002', 'name' => 'Drift Fund']);
        $service = app(FundBalanceService::class);
        $service->applyTransaction($fund, '10.00', FundTransactionType::DEPOSIT);
        DB::table('funds')->where('id', $fund->id)->update(['current_balance' => '9.00']);

        $result = $service->reconcile($fund->fresh());
        self::assertFalse($result['is_consistent']);
        $this->expectException(FundBalanceDriftException::class);
        $service->assertReconciled($fund->fresh());
    }

    public function test_participant_cannot_create_or_modify_fund_transactions(): void
    {
        $participant = \App\Models\Participant::factory()->create(['status' => 'active']);
        $fund = Fund::query()->create(['code' => 'READ-ONLY-001', 'name' => 'Read Only Fund']);
        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/funds/' . $fund->id . '/transactions', [
            'transaction_type' => 'deposit',
            'amount' => '10.00',
        ])->assertForbidden();

        $this->withToken($token)->patchJson('/api/admin/funds/' . $fund->id, [
            'name' => 'Tampered',
        ])->assertForbidden();
    }

    public function test_employee_can_read_funds_but_cannot_write_fund_state(): void
    {
        $employee = Admin::factory()->create([
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_EMPLOYEE),
            'is_super_admin' => false,
            'status' => 'active',
        ]);
        $fund = Fund::query()->create(['code' => 'EMPLOYEE-001', 'name' => 'Employee Visible Fund']);
        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/funds')->assertOk();
        $this->withToken($token)->postJson('/api/admin/funds/' . $fund->id . '/transactions', [
            'transaction_type' => 'deposit',
            'amount' => '1.00',
        ])->assertForbidden();
    }

    public function test_admin_transaction_api_creates_and_lists_ledger_entries(): void
    {
        $admin = $this->adminWithFundPermission();
        $fund = Fund::query()->create(['code' => 'API-001', 'name' => 'API Fund']);
        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/funds/' . $fund->id . '/transactions', [
            'transaction_type' => 'deposit',
            'amount' => '0.10',
            'transaction_date' => '2026-09-06',
            'reference' => 'API-DEP-001',
            'description' => 'Initial deposit',
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/admin/funds/' . $fund->id . '/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.amount', '0.10');
    }

    private function adminWithFundPermission(): Admin
    {
        return Admin::factory()->create([
            'role' => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_FINANCIAL_MANAGER),
            'is_super_admin' => false,
            'status' => 'active',
        ]);
    }
}
