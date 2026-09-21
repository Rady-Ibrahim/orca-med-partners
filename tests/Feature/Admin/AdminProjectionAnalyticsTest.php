<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\ProfitProjectionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminProjectionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_requires_web_admin_session(): void
    {
        $this->get('/admin/projection-analytics')->assertRedirect('/admin/login');
    }

    public function test_super_admin_views_analytics_with_kpis_and_logs(): void
    {
        $admin = $this->superAdmin();
        $participant = Participant::factory()->create([
            'first_name' => 'أحمد',
            'last_name' => 'محمود',
            'status' => 'active',
        ]);

        $this->log(['amount' => '100000.00', 'period_type' => 'annual', 'period_value' => 1, 'is_compounded' => false, 'participant_id' => $participant->id]);
        $this->log(['amount' => '200000.00', 'period_type' => 'years', 'period_value' => 2, 'is_compounded' => true, 'participant_id' => null]);
        $this->log(['amount' => '50000.00', 'period_type' => 'month', 'period_value' => 1, 'is_compounded' => false, 'participant_id' => null]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/projection-analytics')
            ->assertOk()
            ->assertSee('تحليلات توقعات الاستثمار')
            ->assertSee('إجمالي عمليات التوقع')
            ->assertSee('متوسط المبلغ المبحوث عنه')
            ->assertSee('إجمالي رؤوس الأموال المستهدفة')
            ->assertSee('أكثر فترة مطلوبة')
            ->assertSee('350,000.00')
            ->assertSee('116,666.66')
            ->assertSee('سنوي')
            ->assertSee('أحمد محمود')
            ->assertSee('توقعات الاستثمار');
    }

    public function test_analytics_filters_by_period_type(): void
    {
        $admin = $this->superAdmin();

        $this->log(['amount' => '100000.00', 'period_type' => 'annual', 'period_value' => 1, 'is_compounded' => false]);
        $this->log(['amount' => '200000.00', 'period_type' => 'annual', 'period_value' => 2, 'is_compounded' => true]);
        $this->log(['amount' => '40000.00', 'period_type' => 'month', 'period_value' => 1, 'is_compounded' => false]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/projection-analytics?period_type=annual')
            ->assertOk()
            ->assertSee('300,000.00')
            ->assertSee('150,000.00')
            ->assertSee('مركّب')
            ->assertDontSee('40,000.00');
    }

    public function test_analytics_search_filters_by_participant_name(): void
    {
        $admin = $this->superAdmin();
        $participant = Participant::factory()->create([
            'first_name' => 'سارة',
            'last_name' => 'العلي',
            'username' => 'sara',
            'status' => 'active',
        ]);

        $this->log(['amount' => '80000.00', 'period_type' => 'annual', 'period_value' => 1, 'is_compounded' => false, 'participant_id' => $participant->id]);
        $this->log(['amount' => '30000.00', 'period_type' => 'month', 'period_value' => 1, 'is_compounded' => false, 'participant_id' => null]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/projection-analytics?search=سارة')
            ->assertOk()
            ->assertSee('سارة العلي')
            ->assertSee('80,000.00')
            ->assertDontSee('30,000.00');
    }

    public function test_analytics_requires_projections_view_permission(): void
    {
        $blocked = Admin::factory()->create([
            'username' => 'analytics-blocked',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => 'employee',
            'permissions' => [],
            'is_super_admin' => false,
        ]);

        $this->withSession(['web_admin_id' => $blocked->id])
            ->get('/admin/projection-analytics')
            ->assertForbidden();
    }

    public function test_admin_with_projections_view_permission_can_access(): void
    {
        $allowed = Admin::factory()->create([
            'username' => 'analytics-allowed',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => 'employee',
            'permissions' => ['projections.view'],
            'is_super_admin' => false,
        ]);

        $this->withSession(['web_admin_id' => $allowed->id])
            ->get('/admin/projection-analytics')
            ->assertOk();
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'analytics-super-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Analytics Admin',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function log(array $attributes): ProfitProjectionLog
    {
        return ProfitProjectionLog::query()->create([
            'participant_id' => $attributes['participant_id'] ?? null,
            'amount' => $attributes['amount'],
            'period_type' => $attributes['period_type'],
            'period_value' => $attributes['period_value'],
            'is_compounded' => $attributes['is_compounded'],
            'expected_net_profit' => bcdiv(bcmul($attributes['amount'], '0.216', 6), '1', 2),
            'expected_total_balance' => bcadd($attributes['amount'], bcdiv(bcmul($attributes['amount'], '0.216', 6), '1', 2), 2),
            'ip_address' => '127.0.0.1',
        ]);
    }
}
