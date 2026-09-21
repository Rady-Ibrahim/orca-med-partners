<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ProjectionAnalyticsDataAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProjectionAnalyticsController
{
    public function index(Request $request, ProjectionAnalyticsDataAction $action): View
    {
        Gate::forUser($request->user())->authorize('projections.view');

        return view('admin.projections.index', [
            'data' => $action->execute($request->only(['search', 'period_type'])),
            'filters' => $request->only(['search', 'period_type']),
            'title' => 'تحليلات توقعات الاستثمار',
        ]);
    }
}
