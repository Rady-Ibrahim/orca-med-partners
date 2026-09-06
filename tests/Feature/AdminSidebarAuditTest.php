<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminSidebarAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_pages_require_admin_session(): void
    {
        foreach (
            [
                '/admin/participants',
                '/admin/investments',
                '/admin/capital',
                '/admin/monthly-profits',
                '/admin/settlements',
                '/admin/funds',
                '/admin/depreciation',
                '/admin/reports',
                '/admin/notifications',
                '/admin/distribution-rules',
                '/admin/settings',
                '/admin/audit-logs',
            ] as $path
        ) {
            $this->get($path)->assertRedirect('/admin/login');
        }
    }

    public function test_admin_can_access_every_sidebar_module_page(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'sidebar-auditor',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Sidebar Auditor',
            'role' => 'super-admin',
            'permissions' => ['participants.view', 'investments.view', 'capital.view', 'profits.view', 'settlements.view', 'funds.view', 'depreciation.view', 'reports.view', 'notifications.view', 'distribution_rules.view', 'settings.view', 'audit_logs.view'],
            'is_super_admin' => true,
        ]);

        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/participants')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/investments')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/capital')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/monthly-profits')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/settlements')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/funds')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/depreciation')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/reports')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/notifications')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/distribution-rules')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/settings')->assertOk();
        $this->withSession(['web_admin_id' => $admin->id])->get('/admin/audit-logs')->assertOk();
    }
}
