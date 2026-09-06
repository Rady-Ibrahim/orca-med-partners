<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Support\AdminAuthorization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedAdminRoles();
    }

    protected function seedAdminRoles(): void
    {
        $roles = AdminAuthorization::defaultRoleAssignments();

        $seededAdmins = [
            [
                'username' => 'superadmin',
                'name' => 'Super Administrator',
                'email' => 'superadmin@example.com',
                'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
                'permissions' => $roles[AdminAuthorization::ROLE_SUPER_ADMIN],
                'is_super_admin' => true,
                'status' => 'active',
            ],
            [
                'username' => 'financial-manager',
                'name' => 'Financial Manager',
                'email' => 'financial-manager@example.com',
                'role' => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
                'permissions' => $roles[AdminAuthorization::ROLE_FINANCIAL_MANAGER],
                'is_super_admin' => false,
                'status' => 'active',
            ],
            [
                'username' => 'employee',
                'name' => 'Employee',
                'email' => 'employee@example.com',
                'role' => AdminAuthorization::ROLE_EMPLOYEE,
                'permissions' => $roles[AdminAuthorization::ROLE_EMPLOYEE],
                'is_super_admin' => false,
                'status' => 'active',
            ],
        ];

        foreach ($seededAdmins as $adminData) {
            Admin::query()->updateOrCreate(
                ['username' => $adminData['username']],
                [
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => Hash::make('secret123'),
                    'status' => $adminData['status'],
                    'role' => $adminData['role'],
                    'permissions' => $adminData['permissions'],
                    'is_super_admin' => $adminData['is_super_admin'],
                ]
            );
        }
    }
}
