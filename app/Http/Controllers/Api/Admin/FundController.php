<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Funds\CreateFundAction;
use App\Actions\Funds\ListFundsAction;
use App\Actions\Funds\UpdateFundAction;
use App\Http\Requests\StoreFundRequest;
use App\Http\Requests\UpdateFundRequest;
use App\Models\Fund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class FundController
{
    public function index(Request $request, ListFundsAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Fund::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute(
                $request->string('search')->toString() ?: null,
                $request->string('status')->toString() ?: null,
                (int) $request->integer('per_page', 15),
            ),
        ]);
    }

    public function store(StoreFundRequest $request, CreateFundAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', Fund::class);

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), $request->validated()),
        ], 201);
    }

    public function show(Request $request, Fund $fund): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $fund);

        return response()->json(['success' => true, 'data' => $fund->loadCount('transactions')]);
    }

    public function update(UpdateFundRequest $request, Fund $fund, UpdateFundAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), $fund, $request->validated()),
        ]);
    }
}
