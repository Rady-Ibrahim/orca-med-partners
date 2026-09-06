<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\Participant;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2AuthorizationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_the_required_permissions_and_can_access_admin_financial_route(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'super-admin-role',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_SUPER_ADMIN),
            'is_super_admin' => true,
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin/investments');

        $response->assertOk();
        $this->assertTrue($admin->hasPermission('investments.view'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_financial_manager_can_access_financial_route_but_not_roles_management(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'financial-manager-role',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_FINANCIAL_MANAGER),
            'is_super_admin' => false,
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $financialResponse = $this->withToken($token)->getJson('/api/admin/investments');
        $financialResponse->assertOk();

        $rolesResponse = $this->withToken($token)->getJson('/api/admin/audit-logs');
        $rolesResponse->assertStatus(403);
    }

    public function test_employee_cannot_approve_profit_without_permission(): void
    {
        $employee = Admin::factory()->create([
            'username' => 'employee-role',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_EMPLOYEE),
            'is_super_admin' => false,
        ]);

        $participant = Participant::factory()->create(['status' => 'active']);
        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => 1000,
            'invested_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/admin/investments/' . $investment->id . '/approve');

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_another_participants_investment(): void
    {
        $owner = Participant::factory()->create(['username' => 'participant-owner', 'status' => 'active']);
        $attacker = Participant::factory()->create(['username' => 'participant-attacker', 'status' => 'active']);

        $investment = Investment::query()->create([
            'participant_id' => $owner->id,
            'amount' => 2000,
            'invested_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $attacker->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/participant/investments/' . $investment->id);

        $response->assertStatus(403);
    }

    public function test_security_audit_redacts_secrets(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'audit-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_SUPER_ADMIN),
            'is_super_admin' => true,
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/admin/login', [
            'username' => 'audit-admin',
            'password' => 'secret123',
        ]);

        $audit = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('secret123', json_encode($audit->toArray()));
        $this->assertStringNotContainsString($token, json_encode($audit->toArray()));
    }

    public function test_admin_login_is_throttled_after_excessive_requests(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'rate-limit-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/auth/admin/login', [
                'username' => 'rate-limit-admin',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/auth/admin/login', [
            'username' => 'rate-limit-admin',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertNotNull($admin);
    }
}
