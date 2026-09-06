<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Financial;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitRevisionAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MonthlyProfitController
{
    public function store(Request $request, CreateMonthlyProfitAction $action): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->can('create', MonthlyProfit::class), 403, 'Forbidden.');

        $data = $request->validate([
            'capital_snapshot_id' => ['required', 'integer', 'exists:capital_snapshots,id'],
            'distribution_rule_id' => ['required', 'integer', 'exists:distribution_rules,id'],
            'gross_profit' => ['required', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $profit = $action->execute(
            $admin,
            CapitalSnapshot::query()->findOrFail($data['capital_snapshot_id']),
            DistributionRule::query()->findOrFail($data['distribution_rule_id']),
            (string) $data['gross_profit'],
            (int) $data['year'],
            (int) $data['month'],
        );

        return response()->json(['success' => true, 'data' => $profit], 201);
    }

    public function approve(Request $request, MonthlyProfit $monthlyProfit, ApproveMonthlyProfitAction $action): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->can('approve', $monthlyProfit), 403, 'Forbidden.');

        return response()->json(['success' => true, 'data' => $action->execute($admin, $monthlyProfit)]);
    }

    public function revise(Request $request, MonthlyProfit $monthlyProfit, CreateMonthlyProfitRevisionAction $action): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->can('approve', $monthlyProfit), 403, 'Forbidden.');
        $data = $request->validate(['gross_profit' => ['required', 'numeric', 'min:0']]);

        return response()->json([
            'success' => true,
            'data' => $action->execute($admin, $monthlyProfit, (string) $data['gross_profit']),
        ], 201);
    }

    public function showForParticipant(Request $request, MonthlyProfit $monthlyProfit): JsonResponse
    {
        $participant = $request->user();
        abort_unless($participant && $participant->can('view', $monthlyProfit), 403, 'Forbidden.');

        return response()->json([
            'success' => true,
            'data' => $monthlyProfit->load(['allocations' => fn($query) => $query->where('participant_id', $participant->id)]),
        ]);
    }
}
