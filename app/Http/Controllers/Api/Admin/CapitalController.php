<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CapitalController
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('capital.view');

        $snapshots = CapitalSnapshot::query()
            ->with(['items.participant'])
            ->when($request->filled('year'), fn($query) => $query->where('year', (int) $request->integer('year')))
            ->when($request->filled('month'), fn($query) => $query->where('month', (int) $request->integer('month')))
            ->when($request->string('status')->toString(), fn($query, $status) => $query->where('status', $status))
            ->latest('snapshot_date')
            ->paginate((int) $request->integer('per_page', 15))
            ->withQueryString();

        return response()->json(['success' => true, 'data' => $snapshots]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('capital.manage');

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'total_capital' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.participant_id' => ['required', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['required', 'numeric', 'min:0'],
        ]);

        $admin = $request->user();

        $snapshot = DB::transaction(function () use ($data, $admin): CapitalSnapshot {
            $totalCapital = '0.00';
            foreach ($data['items'] as $item) {
                $totalCapital = bcadd($totalCapital, number_format((float) $item['capital'], 2, '.', ''), 2);
            }

            $snapshot = CapitalSnapshot::query()->create([
                'snapshot_date' => $data['snapshot_date'],
                'year' => (int) $data['year'],
                'month' => (int) $data['month'],
                'total_capital' => isset($data['total_capital']) ? (string) $data['total_capital'] : $totalCapital,
                'status' => 'final',
                'created_by_admin_id' => $admin->id,
            ]);

            foreach ($data['items'] as $index => $item) {
                $capital = number_format((float) $item['capital'], 2, '.', '');
                $ratio = bccomp((string) $totalCapital, '0.00', 2) === 0
                    ? '0.0000'
                    : bcdiv($capital, $totalCapital, 4);

                CapitalSnapshotItem::query()->create([
                    'capital_snapshot_id' => $snapshot->id,
                    'participant_id' => (int) $item['participant_id'],
                    'participant_capital_snapshot' => $capital,
                    'participant_ratio_snapshot' => $ratio,
                    'calculation_metadata' => ['index' => $index],
                ]);
            }

            return $snapshot;
        });

        return response()->json([
            'success' => true,
            'data' => $snapshot->load('items.participant'),
        ], 201);
    }

    public function update(Request $request, CapitalSnapshot $capitalSnapshot): JsonResponse
    {
        Gate::forUser($request->user())->authorize('capital.manage');

        $data = $request->validate([
            'snapshot_date' => ['sometimes', 'date'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'total_capital' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.participant_id' => ['sometimes', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $admin = $request->user();

        try {
            DB::transaction(function () use ($data, $admin, $capitalSnapshot): void {
                $capitalSnapshot->fill($data)->save();

                if (array_key_exists('items', $data) && $data['items'] !== null) {
                    $totalCapital = '0.00';
                    foreach ($data['items'] as $item) {
                        $totalCapital = bcadd($totalCapital, number_format((float) $item['capital'], 2, '.', ''), 2);
                    }

                    $capitalSnapshot->items()->delete();

                    foreach ($data['items'] as $index => $item) {
                        $capital = number_format((float) $item['capital'], 2, '.', '');
                        $ratio = bccomp((string) $totalCapital, '0.00', 2) === 0
                            ? '0.0000'
                            : bcdiv($capital, $totalCapital, 4);

                        CapitalSnapshotItem::query()->create([
                            'capital_snapshot_id' => $capitalSnapshot->id,
                            'participant_id' => (int) $item['participant_id'],
                            'participant_capital_snapshot' => $capital,
                            'participant_ratio_snapshot' => $ratio,
                            'calculation_metadata' => ['index' => $index],
                        ]);
                    }

                    $capitalSnapshot->update(['total_capital' => $totalCapital]);
                }
            });
        } catch (ImmutableFinancialRecordException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $capitalSnapshot->load('items.participant'),
        ]);
    }
}