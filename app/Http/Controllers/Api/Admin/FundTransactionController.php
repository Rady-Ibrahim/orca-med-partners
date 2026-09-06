<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Funds\CreateFundTransactionAction;
use App\Actions\Funds\ListFundTransactionsAction;
use App\Http\Requests\StoreFundTransactionRequest;
use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class FundTransactionController
{
    public function index(Request $request, Fund $fund, ListFundTransactionsAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $fund);

        return response()->json(['success' => true, 'data' => $action->execute($fund)]);
    }

    public function store(StoreFundTransactionRequest $request, Fund $fund, CreateFundTransactionAction $action): JsonResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        return response()->json([
            'success' => true,
            'data' => $action->execute($request->user(), $fund, $request->validated()),
        ], 201);
    }

    public function show(Request $request, Fund $fund, FundTransaction $transaction): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $fund);
        abort_unless($transaction->fund_id === $fund->id, 404);

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function reconcile(Request $request, Fund $fund, \App\Domain\Financial\Services\FundBalanceService $service): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $fund);

        return response()->json(['success' => true, 'data' => $service->reconcile($fund)]);
    }
}
