<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SidebarPageDataAction;
use App\Actions\Audit\QueryAuditLogsAction;
use App\Http\Requests\Admin\AuditLogFilterRequest;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SidebarPageController
{
    public function participants(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.participants', [
            'items'   => $action->participants($request->only(['search', 'status'])),
            'title'   => 'المشاركون',
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function investments(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.investments', [
            'items'       => $action->investments($request->only(['participant', 'status', 'date_from', 'date_to'])),
            'participants' => \App\Models\Participant::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn(\App\Models\Participant $p): array => [
                    'id'   => $p->getKey(),
                    'name' => trim($p->first_name . ' ' . $p->last_name) ?: $p->username,
                ]),
            'title'   => 'الاستثمارات',
            'filters' => $request->only(['participant', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function capital(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.capital', [
            'items'        => $action->capitalSnapshots($request->only(['year', 'month', 'status'])),
            'participants' => \App\Models\Participant::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn(\App\Models\Participant $p): array => [
                    'id'   => $p->getKey(),
                    'name' => trim($p->first_name . ' ' . $p->last_name) ?: $p->username,
                ]),
            'title'   => 'رأس المال',
            'filters' => $request->only(['year', 'month', 'status']),
        ]);
    }

    public function monthlyProfits(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.monthly-profits', [
            'items'             => $action->monthlyProfits($request->only(['year', 'month', 'status'])),
            'capitalSnapshots'  => \App\Models\CapitalSnapshot::query()
                ->with('items')
                ->latest('snapshot_date')
                ->get()
                ->map(fn(\App\Models\CapitalSnapshot $s): array => [
                    'id'     => $s->getKey(),
                    'period' => sprintf('%04d / %02d', $s->year, $s->month),
                    'status' => $s->status,
                ]),
            'distributionRules' => \App\Models\DistributionRule::query()
                ->latest('effective_from')
                ->get()
                ->map(fn(\App\Models\DistributionRule $r): array => [
                    'id'    => $r->getKey(),
                    'label' => $r->notes ?: sprintf('قاعدة توزيع — %s', $r->effective_from?->format('Y-m-d') ?? 'غير محددة'),
                    'status'=> $r->status,
                ]),
            'title'   => 'الأرباح الشهرية',
            'filters' => $request->only(['year', 'month', 'status']),
        ]);
    }

    public function settlements(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.settlements', [
            'items'   => $action->settlements($request->only(['year', 'status'])),
            'title'   => 'التسويات السنوية',
            'filters' => $request->only(['year', 'status']),
        ]);
    }

    public function funds(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.funds', [
            'items'   => $action->funds($request->only(['search', 'status'])),
            'title'   => 'الصناديق',
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function depreciation(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.depreciation', [
            'items'   => $action->depreciationNotes($request->only(['year', 'month', 'fund'])),
            'participants' => \App\Models\Participant::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn(\App\Models\Participant $p): array => [
                    'id'   => $p->getKey(),
                    'name' => trim($p->first_name . ' ' . $p->last_name) ?: $p->username,
                ]),
            'funds' => \App\Models\Fund::query()
                ->orderBy('name')
                ->get()
                ->map(fn(\App\Models\Fund $f): array => [
                    'id'   => $f->getKey(),
                    'name' => $f->name,
                ]),
            'title'   => 'الإهلاك',
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
            'items'   => $action->notifications($request->only(['type', 'is_read', 'participant'])),
            'title'   => 'الإشعارات',
            'filters' => $request->only(['type', 'is_read', 'participant']),
        ]);
    }

    public function distributionRules(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.distribution-rules', [
            'items'   => $action->distributionRules($request->only(['status', 'year'])),
            'title'   => 'قواعد التوزيع',
            'filters' => $request->only(['status', 'year']),
        ]);
    }

    public function settings(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.settings', [
            'items' => $action->settings(),
            'title' => 'الإعدادات',
        ]);
    }

    public function auditLogs(Request $request, AuditLogFilterRequest $filterRequest, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-logs', [
            'items'   => $action->execute($filterRequest->filters()),
            'title'   => 'سجل التدقيق',
            'filters' => $filterRequest->filters(),
        ]);
    }

    public function auditLogDetails(Request $request, AuditLog $auditLog, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-log-detail', [
            'item'  => $action->find($auditLog->id),
            'title' => 'تفاصيل سجل التدقيق',
        ]);
    }
}
