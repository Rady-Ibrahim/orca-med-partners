<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SidebarPageDataAction;
use App\Actions\Audit\QueryAuditLogsAction;
use App\Http\Requests\Admin\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use App\Support\AppSettingBag;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SidebarPageController
{
    public function participants(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.participants', [
            'items' => $action->participants($request->only(['search', 'status'])),
            'title' => 'المشاركون',
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function investments(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.investments', [
            'items' => $action->investments($request->only(['participant', 'status', 'date_from', 'date_to'])),
            'participants' => $this->participantOptions(),
            'title' => 'الاستثمارات',
            'filters' => $request->only(['participant', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function capital(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.capital', [
            'items' => $action->capitalSnapshots($request->only(['status'])),
            'participants' => $this->participantOptions(),
            'title' => 'رأس المال',
            'filters' => $request->only(['status']),
        ]);
    }

    public function monthlyProfits(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.monthly-profits', [
            'items' => $action->monthlyProfits($request->only(['year', 'month', 'status'])),
            'capitalSnapshots' => CapitalSnapshot::query()
                ->with('items')
                ->latest('snapshot_date')
                ->get()
                ->map(fn (CapitalSnapshot $s): array => [
                    'id' => $s->getKey(),
                    'period' => sprintf('%04d / %02d', $s->year, $s->month),
                    'status' => $s->status,
                ]),
            'distributionRules' => DistributionRule::query()
                ->latest('effective_from')
                ->get()
                ->map(fn (DistributionRule $r): array => [
                    'id' => $r->getKey(),
                    'label' => $r->notes ?: sprintf('قاعدة توزيع — %s', $r->effective_from?->format('Y-m-d') ?? 'غير محددة'),
                    'status' => $r->status,
                ]),
            'title' => 'الأرباح الشهرية',
            'filters' => $request->only(['year', 'month', 'status']),
        ]);
    }

    public function settlements(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.settlements', [
            'items' => $action->settlements($request->only(['year', 'status'])),
            'title' => 'التسويات السنوية',
            'filters' => $request->only(['year', 'status']),
        ]);
    }

    public function funds(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.funds', [
            'items' => $action->funds($request->only(['search', 'status'])),
            'title' => 'الصناديق',
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function depreciation(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.depreciation', [
            'items' => $action->depreciationNotes($request->only(['year', 'month', 'fund'])),
            'participants' => $this->participantOptions(),
            'funds' => Fund::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Fund $f): array => [
                    'id' => $f->getKey(),
                    'name' => $f->name,
                ]),
            'title' => 'الإهلاك',
            'filters' => $request->only(['year', 'month', 'fund']),
        ]);
    }

    public function reports(Request $request, SidebarPageDataAction $action): View
    {
        Gate::forUser($request->user())->authorize('reports.view');

        return view('admin.pages.reports', [
            'items' => $action->reports(),
            'title' => 'التقارير',
        ]);
    }

    public function notifications(Request $request, SidebarPageDataAction $action): View
    {
        Gate::forUser($request->user())->authorize('notifications.view');

        return view('admin.pages.notifications', [
            'items' => $action->notifications($request->only(['type', 'is_read', 'participant'])),
            'title' => 'الإشعارات',
            'filters' => $request->only(['type', 'is_read', 'participant']),
        ]);
    }

    public function distributionRules(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.distribution-rules', [
            'items' => $action->distributionRules($request->only(['status', 'year'])),
            'title' => 'قواعد التوزيع',
            'filters' => $request->only(['status', 'year']),
        ]);
    }

    public function settings(Request $request): View
    {
        Gate::forUser($request->user())->authorize('settings.view');

        $bonuses = (array) AppSettingBag::get('roi_growth_bonuses', ['0.005', '0.010', '0.0075', '0.005']);

        return view('admin.pages.settings', [
            'settings' => [
                'company_name' => (string) AppSettingBag::get('company_name', 'ORCA MED Partners'),
                'currency_code' => (string) AppSettingBag::get('currency_code', 'SAR'),
                'currency_symbol' => (string) AppSettingBag::get('currency_symbol', 'ر.س'),
                'date_format' => (string) AppSettingBag::get('date_format', 'Y-m-d'),
                'session_lifetime_minutes' => (string) AppSettingBag::get('session_lifetime_minutes', '120'),
                'login_throttle_attempts' => (string) AppSettingBag::get('login_throttle_attempts', '10'),
                'roi_base_annual_rate_percent' => $this->percentFixed(AppSettingBag::get('roi_base_annual_rate', '0.216')),
                'roi_growth_bonus_year1_percent' => $this->percentFixed($bonuses[0] ?? '0.005'),
                'roi_growth_bonus_year2_percent' => $this->percentFixed($bonuses[1] ?? '0.010'),
                'roi_growth_bonus_year3_percent' => $this->percentFixed($bonuses[2] ?? '0.0075'),
                'roi_growth_bonus_year4_percent' => $this->percentFixed($bonuses[3] ?? '0.005'),
            ],
            'activeRule' => DistributionRule::query()->where('status', 'active')->latest('effective_from')->first(),
            'title' => 'الإعدادات',
        ]);
    }

    private function percentFixed(string $ratio): string
    {
        return rtrim(rtrim(bcmul($ratio, '100', 4), '0'), '.');
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function participantOptions(): Collection
    {
        return Participant::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Participant $p): array => [
                'id' => $p->getKey(),
                'name' => ($p->code ? "[{$p->code}] " : '').(trim($p->first_name.' '.$p->last_name) ?: $p->username),
            ]);
    }

    public function auditLogs(Request $request, AuditLogFilterRequest $filterRequest, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-logs', [
            'items' => $action->execute($filterRequest->filters()),
            'title' => 'سجل التدقيق',
            'filters' => $filterRequest->filters(),
        ]);
    }

    public function auditLogDetails(Request $request, AuditLog $auditLog, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-log-detail', [
            'item' => $action->find($auditLog->id),
            'title' => 'تفاصيل سجل التدقيق',
        ]);
    }
}
