<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Audit\ListAuditLogsAction;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AuditLogController
{
    public function index(Request $request, ListAuditLogsAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', AuditLog::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute(),
        ]);
    }
}
