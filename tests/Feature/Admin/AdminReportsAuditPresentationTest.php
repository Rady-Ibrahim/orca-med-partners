<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminReportsAuditPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_report_shows_arabic_status_and_rule_snapshot(): void
    {
        $admin = $this->superAdmin();

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-07-01',
            'year' => 2026,
            'month' => 7,
            'total_capital' => '100000.00',
            'status' => 'active',
        ]);

        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.1000',
            'incentive_fund_rate' => '0.0300',
            'distributed_share_rate' => '0.6500',
            'status' => 'draft',
        ]);

        MonthlyProfit::query()->create([
            'capital_snapshot_id' => $snapshot->id,
            'distribution_rule_id' => $rule->id,
            'distribution_rule_snapshot' => [
                'management_fee_rate' => '0.2500',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate' => '0.1000',
                'incentive_fund_rate' => '0.0300',
                'distributed_share_rate' => '0.6500',
            ],
            'year' => 2026,
            'month' => 7,
            'version' => 1,
            'status' => 'approved',
            'gross_profit' => '100000.00',
            'management_amount' => '25000.00',
            'depreciation_amount' => '5000.00',
            'growth_amount' => '10000.00',
            'incentive_amount' => '3000.00',
            'distributed_amount' => '65000.00',
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/reports/distribution')
            ->assertOk()
            ->assertSee('تقرير التوزيعات')
            ->assertSee('الحصة الموزعة')
            ->assertSee('65%')
            ->assertSee('صندوق النمو')
            ->assertDontSee('distributed_share_rate')
            ->assertDontSee('management_fee_rate');

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/reports/monthly-profits')
            ->assertOk()
            ->assertSee('معتمد');
    }

    public function test_audit_logs_page_shows_arabic_action_user_and_entity(): void
    {
        $admin = $this->superAdmin();

        AuditLog::query()->create([
            'auditable_type' => 'monthly_profit',
            'auditable_id' => 7,
            'action' => 'monthly_profit_approved',
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
            'old_values' => null,
            'new_values' => null,
            'metadata' => ['year' => 2026, 'month' => 7],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('اعتماد الأرباح الشهرية')
            ->assertSee('إداري')
            ->assertSee('أرباح شهرية #7')
            ->assertDontSee('monthly_profit_approved')
            ->assertDontSee(Admin::class);
    }

    public function test_audit_log_detail_shows_translated_field_values(): void
    {
        $admin = $this->superAdmin();

        $log = AuditLog::query()->create([
            'auditable_type' => 'distribution_rule',
            'auditable_id' => 3,
            'action' => 'distribution_rule_updated',
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
            'old_values' => ['status' => 'draft', 'incentive_fund_rate' => '0.0200'],
            'new_values' => ['status' => 'active', 'incentive_fund_rate' => '0.0300'],
            'metadata' => ['effective_from' => '2026-01-01'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/audit-logs/'.$log->id)
            ->assertOk()
            ->assertSee('تحديث قاعدة التوزيع')
            ->assertSee('قاعدة توزيع #3')
            ->assertSee('القيم الجديدة')
            ->assertSee('نسبة صندوق الحافز')
            ->assertSee('3%')
            ->assertSee('نشط')
            ->assertSee('السابق')
            ->assertSee('2%');
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'reports-audit-super-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Reports Audit Admin',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }
}
