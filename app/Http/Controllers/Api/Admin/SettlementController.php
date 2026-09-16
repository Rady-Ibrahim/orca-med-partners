<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\MarkSettlementPaidAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Actions\Settlements\CreateSettlementAdjustmentAction;
use App\Actions\Settlements\ListAnnualSettlementsAction;
use App\Actions\Settlements\CancelSettlementAction;
use App\Actions\Settlements\CreateSettlementRevisionAction;
use App\Http\Requests\StoreAnnualSettlementRequest;
use App\Http\Requests\StoreSettlementPaymentRequest;
use App\Http\Requests\StoreSettlementAdjustmentRequest;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SettlementController
{
    public function index(Request $request, ListAnnualSettlementsAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Settlement::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute(
                $request->filled('year') ? (int) $request->integer('year') : null,
                $request->string('status')->toString() ?: null,
                (int) $request->integer('per_page', 15),
            ),
        ]);
    }

    public function store(StoreAnnualSettlementRequest $request, CreateAnnualSettlementAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', Settlement::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), (int) $request->validated('year')),
        ], 201);
    }

    public function show(Request $request, Settlement $settlement): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $settlement);

        return response()->json(['success' => true, 'data' => $settlement->load('items')]);
    }

    public function approve(Request $request, Settlement $settlement, ApproveSettlementAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('approve', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement)]);
    }

    public function paid(Request $request, Settlement $settlement, MarkSettlementPaidAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('pay', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement)]);
    }

    public function payment(StoreSettlementPaymentRequest $request, Settlement $settlement, RecordSettlementPaymentAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('pay', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement, $request->validated())]);
    }

    public function adjustment(StoreSettlementAdjustmentRequest $request, Settlement $settlement, CreateSettlementAdjustmentAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('pay', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement, $request->validated())], 201);
    }

    public function cancel(Request $request, Settlement $settlement, CancelSettlementAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('cancel', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement)]);
    }

    public function revise(Request $request, Settlement $settlement, CreateSettlementRevisionAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('revise', $settlement);

        return response()->json(['success' => true, 'data' => $action->execute($request->user(), $settlement)], 201);
    }
}
