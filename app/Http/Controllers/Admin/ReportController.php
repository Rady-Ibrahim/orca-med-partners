<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ReportDataAction;
use App\Exports\ReportExport;
use App\Http\Requests\Admin\ReportFilterRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

final class ReportController
{
    public function index(ReportFilterRequest $request, ReportDataAction $reports): View
    {
        Gate::forUser($request->user())->authorize('reports.view');
        $report = (string) $request->route('report');
        $items = $reports->execute($report, $request->filters());

        return view('admin.pages.report', [
            'report' => $report,
            'title' => $reports->title($report),
            'columns' => $reports->columns($report),
            'items' => $items,
            'rows' => $reports->normalize($items->getCollection(), $report),
            'reportTotals' => $reports->totals($report, $request->filters()),
            'filters' => $request->filters(),
            'reportOptions' => ReportDataAction::REPORTS,
        ]);
    }

    public function excel(ReportFilterRequest $request, ReportDataAction $reports): Response
    {
        Gate::forUser($request->user())->authorize('reports.export');
        $report = (string) $request->route('report');

        return Excel::download(new ReportExport($reports, $report, $request->filters()), $report . '-report.xlsx');
    }

    public function pdf(ReportFilterRequest $request, ReportDataAction $reports): Response
    {
        Gate::forUser($request->user())->authorize('reports.export');
        $report = (string) $request->route('report');
        $rows = $reports->execute($report, $request->filters(), false);
        $normalized = $reports->normalize($rows, $report);

        return Pdf::loadView('admin.pages.report-pdf', [
            'title' => $reports->title($report),
            'columns' => $reports->columns($report),
            'rows' => $normalized,
            'reportTotals' => $reports->totals($report, $request->filters()),
            'filters' => $request->filters(),
        ])->setPaper('a4', 'landscape')->download($report . '-report.pdf');
    }
}
