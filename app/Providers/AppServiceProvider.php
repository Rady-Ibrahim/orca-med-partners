<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\Settlement;
use App\Policies\AdminPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CapitalSnapshotPolicy;
use App\Policies\DepreciationNotePolicy;
use App\Policies\DistributionRulePolicy;
use App\Policies\FundPolicy;
use App\Policies\InvestmentPolicy;
use App\Policies\MonthlyProfitPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ParticipantPolicy;
use App\Policies\SettlementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Admin::class => AdminPolicy::class,
        Participant::class => ParticipantPolicy::class,
        Investment::class => InvestmentPolicy::class,
        CapitalSnapshot::class => CapitalSnapshotPolicy::class,
        MonthlyProfit::class => MonthlyProfitPolicy::class,
        Fund::class => FundPolicy::class,
        DepreciationNote::class => DepreciationNotePolicy::class,
        Settlement::class => SettlementPolicy::class,
        Notification::class => NotificationPolicy::class,
        DistributionRule::class => DistributionRulePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability): ?bool {
            if (! $user instanceof Admin) {
                return null;
            }

            if ($user->is_super_admin) {
                return true;
            }

            if ($user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        $this->defineAdminPermissionGates();

        Gate::define('view-own-participant-profile', function ($user, $participant = null) {
            return $user instanceof Participant && ($participant === null || $user->id === $participant->id);
        });
    }

    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    protected function defineAdminPermissionGates(): void
    {
        $permissions = [
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

        foreach ($permissions as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return $user instanceof Admin && ($user->is_super_admin || $user->hasPermission($permission));
            });
        }
    }
}
