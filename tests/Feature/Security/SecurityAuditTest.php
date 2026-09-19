<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Participant;
use App\Services\SecurityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_participant_logins_and_logouts_are_audited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'audit-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $adminLogin = $this->postJson('/api/v1/auth/admin/login', [
            'username' => 'audit-admin',
            'password' => 'secret123',
        ]);

        $adminLogin->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_login_success']);

        $adminToken = $adminLogin->json('data.access_token');
        $this->withToken($adminToken)->postJson('/api/v1/auth/admin/logout')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_logout']);

        $participant = Participant::factory()->create([
            'username' => 'audit-participant',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $participantLogin = $this->postJson('/api/v1/auth/participant/login', [
            'username' => 'audit-participant',
            'password' => 'secret123',
        ]);

        $participantLogin->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'participant_login_success']);

        $participantToken = $participantLogin->json('data.access_token');
        $this->withToken($participantToken)->postJson('/api/v1/auth/participant/logout')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'participant_logout']);
    }

    public function test_login_failure_audit_is_persisted(): void
    {
        $this->postJson('/api/v1/auth/admin/login', [
            'username' => 'missing-user',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_login_failure']);
    }

    public function test_refresh_and_reuse_are_audited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'refresh-audit-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/auth/admin/login', [
            'username' => 'refresh-audit-admin',
            'password' => 'secret123',
        ]);

        $login->assertOk();
        $refreshToken = $login->json('data.refresh_token');

        $this->postJson('/api/v1/auth/admin/refresh', ['refresh_token' => $refreshToken])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_refresh_success']);

        $this->postJson('/api/v1/auth/admin/refresh', ['refresh_token' => $refreshToken])->assertStatus(401);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_refresh_failure']);
    }

    public function test_password_change_and_reset_are_audited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'audit-password-admin',
            'email' => 'audit-password-admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/admin/password/change', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_password_change']);

        $this->postJson('/api/v1/auth/admin/password/reset/request', ['email' => 'audit-password-admin@example.com'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_password_reset_request']);
    }

    public function test_inactive_login_attempt_is_audited(): void
    {
        Admin::factory()->create([
            'username' => 'inactive-audit-admin',
            'password' => Hash::make('secret123'),
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/auth/admin/login', [
            'username' => 'inactive-audit-admin',
            'password' => 'secret123',
        ])->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['action' => 'inactive_account_attempt']);
    }

    public function test_role_and_permission_change_events_are_audited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'audit-role-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => 'employee',
            'permissions' => ['investments.view'],
        ]);

        app(SecurityAuditService::class)->log('role_assigned', $admin, 'admin', $admin->id, [
            'role' => 'financial-manager',
        ]);

        app(SecurityAuditService::class)->log('permission_granted', $admin, 'admin', $admin->id, [
            'permission' => 'reports.view',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'role_assigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'permission_granted']);
    }

    public function test_authorization_denial_events_are_audited_and_sensitive_values_are_redacted(): void
    {
        $owner = Participant::factory()->create(['username' => 'owner-audit', 'status' => 'active', 'password' => Hash::make('secret123')]);
        $attacker = Participant::factory()->create(['username' => 'attacker-audit', 'status' => 'active', 'password' => Hash::make('secret123')]);

        $investment = \App\Models\Investment::query()->create([
            'participant_id' => $owner->id,
            'amount' => 1200,
            'invested_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/v1/participant/investments/' . $investment->id);
        $response->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['action' => 'authorization_denied']);

        app(SecurityAuditService::class)->log('security_redaction_check', $attacker, 'participant', $attacker->id, [
            'password' => 'super-secret-password',
            'access_token' => 'secret-access-token',
            'refresh_token' => 'secret-refresh-token',
            'reset_token' => 'secret-reset-token',
            'safe_value' => 'visible',
        ]);

        $audit = AuditLog::query()->where('action', 'security_redaction_check')->latest('id')->first();
        $this->assertNotNull($audit);

        $metadataJson = json_encode($audit->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('[redacted]', $metadataJson);
        $this->assertStringNotContainsString('super-secret-password', $metadataJson);
        $this->assertStringNotContainsString('secret-access-token', $metadataJson);
        $this->assertStringNotContainsString('secret-refresh-token', $metadataJson);
        $this->assertStringNotContainsString('secret-reset-token', $metadataJson);
        $this->assertStringContainsString('visible', $metadataJson);
    }
}
