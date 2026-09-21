<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\DepreciationNote;
use App\Models\Fund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DepreciationController
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.view');

        $notes = DepreciationNote::query()
            ->with(['participant', 'fund'])
            ->when($request->filled('year'), fn ($query) => $query->where('year', (int) $request->integer('year')))
            ->when($request->filled('month'), fn ($query) => $query->where('month', (int) $request->integer('month')))
            ->when($request->filled('fund_id'), fn ($query) => $query->where('fund_id', (int) $request->integer('fund_id')))
            ->latest('transaction_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return response()->json(['success' => true, 'data' => $notes]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.create');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'transaction_date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'description' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'monthly_profit_id' => ['nullable', 'integer', 'exists:monthly_profits,id'],
        ]);

        $admin = $request->user();

        $note = DepreciationNote::query()->create([
            'participant_id' => isset($data['participant_id']) ? (int) $data['participant_id'] : null,
            'fund_id' => isset($data['fund_id'])
                ? (int) $data['fund_id']
                : Fund::query()->where('code', 'depreciation_fund')->value('id'),
            'monthly_profit_id' => isset($data['monthly_profit_id']) ? (int) $data['monthly_profit_id'] : null,
            'amount' => (string) $data['amount'],
            'rate' => (string) $data['rate'],
            'transaction_date' => $data['transaction_date'],
            'year' => (int) $data['year'],
            'month' => (int) $data['month'],
            'description' => $data['description'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
            'created_by_admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $note->load(['participant', 'fund']),
        ], 201);
    }

    public function update(Request $request, DepreciationNote $depreciationNote): JsonResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.update');

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:99999999999999.99'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'transaction_date' => ['sometimes', 'date'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'description' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $depreciationNote->fill($data)->save();

        return response()->json([
            'success' => true,
            'data' => $depreciationNote->load(['participant', 'fund']),
        ]);
    }

    public function destroy(Request $request, DepreciationNote $depreciationNote): JsonResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.update');

        $depreciationNote->delete();

        return response()->json(['success' => true, 'message' => 'Depreciation note deleted.']);
    }
}
