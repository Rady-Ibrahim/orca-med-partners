<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class LoginThrottleAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_admin_login_throttles_after_10_attempts(): void
    {
        Admin::factory()->create([
            'username' => 'bruteforce',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);
        RateLimiter::clear('10,1');

        for ($i = 0; $i < 10; $i++) {
            $this->post('/admin/login', ['username' => 'bruteforce', 'password' => 'wrong-pass'])
                ->assertStatus(302);
        }

        $this->post('/admin/login', ['username' => 'bruteforce', 'password' => 'secret123'])
            ->assertTooManyRequests();
    }

    public function test_failed_web_login_writes_audit_log(): void
    {
        Admin::factory()->create([
            'username' => 'web-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        $this->post('/admin/login', ['username' => 'web-admin', 'password' => 'wrong-pass']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_login_failure',
            'actor_type' => 'system',
            'actor_id' => 0,
        ]);

        $log = AuditLog::query()->where('action', 'admin_login_failure')->firstOrFail();
        $this->assertStringContainsString('web_login', json_encode($log->metadata ?? []));
    }

    public function test_successful_web_login_writes_audit_log_and_session(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'web-admin-ok',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        $this->post('/admin/login', ['username' => 'web-admin-ok', 'password' => 'secret123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_login_success',
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
        ]);

        $log = AuditLog::query()->where('action', 'admin_login_success')->firstOrFail();
        $this->assertStringContainsString('web_login', json_encode($log->metadata ?? []));
    }
}