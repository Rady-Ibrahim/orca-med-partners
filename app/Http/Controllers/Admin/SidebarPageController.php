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
            'items'   => $action->investments($request->only(['participant', 'status', 'date_from', 'date_to'])),
            'title'   => 'الاستثمارات',
            'filters' => $request->only(['participant', 'status', 'date_from', 'date_to']),
        ]);
    }

    public function capital(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.capital', [
            'items'   => $action->capitalSnapshots($request->only(['year', 'month', 'status'])),
            'title'   => 'رأس المال',
            'filters' => $request->only(['year', 'month', 'status']),
        ]);
    }

    public function monthlyProfits(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.monthly-profits', [
            'items'   => $action->monthlyProfits($request->only(['year', 'month', 'status'])),
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
