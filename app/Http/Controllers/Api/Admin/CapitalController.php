<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Financial\Services\CapitalCalculatorService;
use App\Models\CapitalSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CapitalController
{
    public function __construct(
        private CapitalCalculatorService $capital,
    ) {}
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('capital.view');

        $snapshots = CapitalSnapshot::query()
            ->with(['items.participant'])
            ->when($request->filled('year'), fn ($query) => $query->where('year', (int) $request->integer('year')))
            ->when($request->filled('month'), fn ($query) => $query->where('month', (int) $request->integer('month')))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
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
            'total_capital' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.participant_id' => ['required', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['required', 'numeric', 'min:0'],
        ]);

        $admin = $request->user();
        $snapshotDate = Carbon::parse($data['snapshot_date']);

        $snapshot = DB::transaction(function () use ($data, $snapshotDate, $admin): CapitalSnapshot {
            $normalized = $this->capital->normalizeItems($data['items']);

            $snapshot = CapitalSnapshot::query()->create([
                'snapshot_date' => $data['snapshot_date'],
                'year' => $snapshotDate->year,
                'month' => $snapshotDate->month,
                'total_capital' => $normalized['total_capital'],
                'status' => 'final',
                'created_by_admin_id' => $admin->id,
            ]);

            $snapshot->syncItems($normalized['items'], $admin);

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
            'total_capital' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.participant_id' => ['sometimes', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $admin = $request->user();

        DB::transaction(function () use ($data, $admin, $capitalSnapshot): void {
            if (array_key_exists('snapshot_date', $data)) {
                $capitalSnapshot->snapshot_date = $data['snapshot_date'];
                $parsed = Carbon::parse($data['snapshot_date']);
                $capitalSnapshot->year = $parsed->year;
                $capitalSnapshot->month = $parsed->month;
            }
            if (array_key_exists('total_capital', $data) && $data['total_capital'] !== null && ! array_key_exists('items', $data)) {
                $capitalSnapshot->total_capital = (string) $data['total_capital'];
            }
            $capitalSnapshot->save();

            if (array_key_exists('items', $data) && $data['items'] !== null) {
                $capitalSnapshot->syncItems($data['items'], $admin);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $capitalSnapshot->load('items.participant'),
        ]);
    }
}
