<?php

declare(strict_types=1);

namespace App\Support;

class AdminAuthorization
{
    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_FINANCIAL_MANAGER = 'financial-manager';

    public const ROLE_EMPLOYEE = 'employee';

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_FINANCIAL_MANAGER,
            self::ROLE_EMPLOYEE,
        ];
    }

    public static function permissionCatalog(): array
    {
        return [
            'participants.view',
            'participants.create',
            'participants.update',
            'participants.activate',
            'participants.deactivate',
            'participants.reset_password',
            'investments.view',
            'investments.create',
            'investments.update',
            'capital.view',
            'capital.manage',
            'profits.view',
            'profits.create',
            'profits.update',
            'profits.approve',
            'funds.view',
            'funds.manage',
            'depreciation.view',
            'depreciation.create',
            'depreciation.update',
            'settlements.view',
            'settlements.create',
            'settlements.update',
            'settlements.approve',
            'settlements.pay',
            'reports.view',
            'projections.view',
            'reports.export',
            'notifications.view',
            'distribution_rules.view',
            'distribution_rules.manage',
            'settings.view',
            'settings.manage',
            'audit_logs.view',
            'roles.manage',
            'permissions.manage',
        ];
    }

    public static function permissionsForRole(string $role): array
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            self::ROLE_SUPER_ADMIN => self::permissionCatalog(),
            self::ROLE_FINANCIAL_MANAGER => [
                'participants.view',
                'participants.create',
                'participants.update',
                'investments.view',
                'investments.create',
                'investments.update',
                'capital.view',
                'capital.manage',
                'profits.view',
                'profits.create',
                'profits.update',
                'profits.approve',
                'funds.view',
                'funds.manage',
                'depreciation.view',
                'depreciation.create',
                'depreciation.update',
                'settlements.view',
                'settlements.create',
                'settlements.update',
                'settlements.approve',
                'settlements.pay',
                'reports.view',
                'projections.view',
                'reports.export',
                'notifications.view',
                'distribution_rules.view',
                'distribution_rules.manage',
                'settings.view',
            ],
            self::ROLE_EMPLOYEE => [
                'participants.view',
                'notifications.view',
                'reports.view',
                'settings.view',
                'funds.view',
                'depreciation.view',
                'settlements.view',
                'distribution_rules.view',
            ],
            default => [],
        };
    }

    public static function defaultRoleAssignments(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => self::permissionsForRole(self::ROLE_SUPER_ADMIN),
            self::ROLE_FINANCIAL_MANAGER => self::permissionsForRole(self::ROLE_FINANCIAL_MANAGER),
            self::ROLE_EMPLOYEE => self::permissionsForRole(self::ROLE_EMPLOYEE),
        ];
    }

    public static function normalizeRole(?string $role): string
    {
        return strtolower(trim((string) ($role ?? '')));
    }
}
