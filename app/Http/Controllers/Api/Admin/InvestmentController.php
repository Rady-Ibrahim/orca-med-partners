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
            'data' => $action->execute(
                $request->string('search')->toString() ?: null,
                $request->string('status')->toString() ?: null,
                (int) $request->integer('per_page', 15),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Investment::class);
        abort_unless($request->user() instanceof Admin, 403, 'Admin access required.');

        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:participants,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'invested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $investment = Investment::query()->create([
            'participant_id' => (int) $data['participant_id'],
            'amount' => (string) $data['amount'],
            'invested_at' => $data['invested_at'] ?? now()->toDateString(),
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'created_by_admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $investment->load('participant'),
        ], 201);
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
