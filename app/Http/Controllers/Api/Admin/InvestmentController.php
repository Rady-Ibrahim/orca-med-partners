<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Investment\ApproveInvestmentAction;
use App\Actions\Investment\ListInvestmentsAction;
use App\Models\Admin;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class InvestmentController
{
    public function index(Request $request, ListInvestmentsAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Investment::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute(),
        ]);
    }

    public function approve(Request $request, Investment $investment, ApproveInvestmentAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('approve', $investment);
        abort_unless($request->user() instanceof Admin, 403, 'Admin access required.');

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), $investment),
        ]);
    }
}
