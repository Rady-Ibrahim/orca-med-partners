<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_web_admin_session(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    public function test_admin_can_login_and_view_database_backed_dashboard(): void
    {
        Admin::factory()->create([
            'username' => 'dashboard-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'لوحة الإدارة',
        ]);

        $this->post('/admin/login', [
            'username' => 'dashboard-admin',
            'password' => 'secret123',
        ])->assertRedirect('/admin/dashboard');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('لوحة التحكم')
            ->assertSee('إجمالي رأس المال')
            ->assertSee('لا توجد بيانات صناديق لهذه الفترة')
            ->assertSee('لوحة الإدارة');
    }

    public function test_inactive_admin_cannot_start_web_session(): void
    {
        Admin::factory()->create([
            'username' => 'inactive-dashboard-admin',
            'password' => Hash::make('secret123'),
            'status' => 'inactive',
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'username' => 'inactive-dashboard-admin',
            'password' => 'secret123',
        ])->assertRedirect('/admin/login')->assertSessionHasErrors('username');
    }
}
