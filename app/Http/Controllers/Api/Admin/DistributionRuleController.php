<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Models\Admin;
use App\Models\DistributionRule;
use InvalidArgumentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class DistributionRuleController
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', DistributionRule::class);

        $rules = DistributionRule::query()
            ->with(['createdBy', 'approvedBy'])
            ->when($request->string('status')->toString(), fn($query, $status) => $query->where('status', $status))
            ->when($request->string('is_default')->toString() === 'true', fn($query) => $query->where('is_default', true))
            ->latest('effective_from')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function show(Request $request, DistributionRule $distributionRule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $distributionRule);

        return response()->json([
            'success' => true,
            'data' => $distributionRule->load(['createdBy', 'approvedBy']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();
        Gate::forUser($admin)->authorize('create', DistributionRule::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'management_fee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'depreciation_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'growth_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'incentive_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'distributed_share_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'status' => ['required', 'in:draft,active,locked'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DistributionRuleValidator::validate([
                'management_fee_rate' => (string) $data['management_fee_rate'],
                'depreciation_fund_rate' => (string) $data['depreciation_fund_rate'],
                'growth_fund_rate' => (string) $data['growth_fund_rate'],
                'incentive_fund_rate' => (string) $data['incentive_fund_rate'],
                'distributed_share_rate' => (string) $data['distributed_share_rate'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $rule = DistributionRule::query()->create([
            ...$data,
            'is_default' => $data['is_default'] ?? false,
            'created_by_admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $rule->load(['createdBy', 'approvedBy']),
        ], 201);
    }

    public function update(Request $request, DistributionRule $distributionRule): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $distributionRule);

        $data = $request->validate([
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'management_fee_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'depreciation_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'growth_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'incentive_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'distributed_share_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'status' => ['sometimes', 'in:draft,active,locked'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DistributionRuleValidator::validate([
                'management_fee_rate' => (string) ($data['management_fee_rate'] ?? $distributionRule->management_fee_rate),
                'depreciation_fund_rate' => (string) ($data['depreciation_fund_rate'] ?? $distributionRule->depreciation_fund_rate),
                'growth_fund_rate' => (string) ($data['growth_fund_rate'] ?? $distributionRule->growth_fund_rate),
                'incentive_fund_rate' => (string) ($data['incentive_fund_rate'] ?? $distributionRule->incentive_fund_rate),
                'distributed_share_rate' => (string) ($data['distributed_share_rate'] ?? $distributionRule->distributed_share_rate),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $distributionRule->fill($data)->save();

        return response()->json([
            'success' => true,
            'data' => $distributionRule->load(['createdBy', 'approvedBy']),
        ]);
    }
}