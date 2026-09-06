<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_full_system_access(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'super-admin-matrix',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_SUPER_ADMIN),
            'is_super_admin' => true,
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/investments')->assertOk();
        $this->withToken($token)->getJson('/api/admin/participants')->assertOk();
        $this->withToken($token)->getJson('/api/admin/audit-logs')->assertOk();
    }

    public function test_financial_manager_has_financial_access_but_is_denied_admin_management_routes(): void
    {
        $manager = Admin::factory()->create([
            'username' => 'financial-manager-matrix',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_FINANCIAL_MANAGER),
            'is_super_admin' => false,
        ]);

        $token = $manager->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/investments')->assertOk();
        $this->withToken($token)->getJson('/api/admin/audit-logs')->assertStatus(403);
    }

    public function test_employee_is_blocked_from_unassigned_routes(): void
    {
        $employee = Admin::factory()->create([
            'username' => 'employee-matrix',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_EMPLOYEE),
            'is_super_admin' => false,
        ]);

        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/investments')->assertStatus(403);
        $this->withToken($token)->getJson('/api/admin/audit-logs')->assertStatus(403);
    }

    public function test_employee_can_access_route_when_explicit_permission_is_granted(): void
    {
        $employee = Admin::factory()->create([
            'username' => 'employee-granted-permission',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => ['investments.view'],
            'is_super_admin' => false,
        ]);

        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/investments')->assertOk();
    }
}
