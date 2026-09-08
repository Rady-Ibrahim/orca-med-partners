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
            'items' => $action->participants(),
            'title' => 'المشاركون',
            'user' => $request->user(),
        ]);
    }

    public function investments(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.investments', [
            'items' => $action->investments(),
            'title' => 'الاستثمارات',
            'user' => $request->user(),
        ]);
    }

    public function capital(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.capital', [
            'items' => $action->capitalSnapshots(),
            'title' => 'رأس المال',
            'user' => $request->user(),
        ]);
    }

    public function monthlyProfits(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.monthly-profits', [
            'items' => $action->monthlyProfits(),
            'title' => 'الأرباح الشهرية',
            'user' => $request->user(),
        ]);
    }

    public function settlements(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.settlements', [
            'items' => $action->settlements(),
            'title' => 'التسويات السنوية',
            'user' => $request->user(),
        ]);
    }

    public function funds(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.funds', [
            'items' => $action->funds(),
            'title' => 'الصناديق',
            'user' => $request->user(),
        ]);
    }

    public function depreciation(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.depreciation', [
            'items' => $action->depreciationNotes(),
            'title' => 'الإهلاك',
            'user' => $request->user(),
        ]);
    }

    public function reports(Request $request, SidebarPageDataAction $action): View
    {
        Gate::forUser($request->user())->authorize('reports.view');

        return view('admin.pages.reports', [
            'items' => $action->reports(),
            'title' => 'التقارير',
            'user' => $request->user(),
        ]);
    }

    public function notifications(Request $request, SidebarPageDataAction $action): View
    {
        Gate::forUser($request->user())->authorize('notifications.view');

        return view('admin.pages.notifications', [
            'items' => $action->notifications(),
            'title' => 'الإشعارات',
            'user' => $request->user(),
        ]);
    }

    public function distributionRules(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.distribution-rules', [
            'items' => $action->distributionRules(),
            'title' => 'قواعد التوزيع',
            'user' => $request->user(),
        ]);
    }

    public function settings(Request $request, SidebarPageDataAction $action): View
    {
        return view('admin.pages.settings', [
            'items' => $action->settings(),
            'title' => 'الإعدادات',
            'user' => $request->user(),
        ]);
    }

    public function auditLogs(Request $request, AuditLogFilterRequest $filterRequest, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-logs', [
            'items' => $action->execute($filterRequest->filters()),
            'title' => 'سجل التدقيق',
            'user' => $request->user(),
        ]);
    }

    public function auditLogDetails(Request $request, AuditLog $auditLog, QueryAuditLogsAction $action): View
    {
        Gate::forUser($request->user())->authorize('audit_logs.view');

        return view('admin.pages.audit-log-detail', [
            'item' => $action->find($auditLog->id),
            'title' => 'تفاصيل سجل التدقيق',
            'user' => $request->user(),
        ]);
    }
}
