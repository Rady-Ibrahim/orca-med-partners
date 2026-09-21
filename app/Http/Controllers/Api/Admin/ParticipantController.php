<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Admin\StoreParticipantRequest;
use App\Http\Requests\Admin\UpdateParticipantRequest;
use App\Models\Participant;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ParticipantController
{
    public function __construct(private SecurityAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Participant::class);

        $query = Participant::query()
            ->withSum('investments', 'amount')
            ->latest('created_at');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($request->integer('per_page', 15))->withQueryString()
            ->through(fn (Participant $participant): array => [
                'id' => $participant->getKey(),
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'name' => trim($participant->first_name.' '.$participant->last_name) ?: $participant->username,
                'username' => $participant->username,
                'code' => $participant->code,
                'email' => $participant->email,
                'status' => $participant->status,
                'role' => $participant->role,
                'total_investment' => (string) ($participant->investments_sum_amount ?? '0.00'),
                'created_at' => $participant->created_at?->toISOString(),
            ]);

        return response()->json(['success' => true, 'data' => $paginator]);
    }

    public function show(Request $request, Participant $participant): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $participant);

        return response()->json(['success' => true, 'data' => $participant->load('investments')]);
    }

    public function store(StoreParticipantRequest $request): JsonResponse
    {
        $admin = $request->user();
        Gate::forUser($admin)->authorize('participants.create');

        $data = $request->validated();

        $participant = Participant::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'code' => $data['code'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'],
            'role' => 'participant',
            'permissions' => [],
            'created_by_admin_id' => $admin->getKey(),
        ]);

        $this->audit->log('participant_created', $admin, Participant::class, $participant->getKey(), [
            'new' => [
                'username' => $participant->username,
                'code' => $participant->code,
                'email' => $participant->email,
                'status' => $participant->status,
            ],
        ]);

        return response()->json(['success' => true, 'data' => $participant->fresh()], 201);
    }

    public function update(UpdateParticipantRequest $request, Participant $participant): JsonResponse
    {
        $admin = $request->user();
        Gate::forUser($admin)->authorize('participants.update');

        $data = $request->validated();
        $old = [
            'first_name' => $participant->first_name,
            'last_name' => $participant->last_name,
            'username' => $participant->username,
            'code' => $participant->code,
            'email' => $participant->email,
            'status' => $participant->status,
        ];

        $participant->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'code' => $data['code'] ?? $participant->code,
            'email' => $data['email'] ?? null,
            'status' => $data['status'],
        ]);

        $this->audit->log('participant_updated', $admin, Participant::class, $participant->getKey(), [
            'old' => $old,
            'new' => [
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'username' => $participant->username,
                'code' => $participant->code,
                'email' => $participant->email,
                'status' => $participant->status,
            ],
        ]);

        return response()->json(['success' => true, 'data' => $participant->fresh()]);
    }

    public function destroy(Request $request, Participant $participant): JsonResponse
    {
        $admin = $request->user();
        Gate::forUser($admin)->authorize('participants.update');

        abort_if($this->hasFinancialHistory($participant), 422, 'Cannot delete a participant with financial or account history.');

        $username = $participant->username;
        $participant->delete();

        $this->audit->log('participant_deleted', $admin, Participant::class, $participant->getKey(), [
            'old' => ['username' => $username, 'status' => $participant->status],
        ]);

        return response()->json(['success' => true, 'message' => 'Participant deleted.', 'data' => ['id' => $participant->getKey()]]);
    }

    private function hasFinancialHistory(Participant $participant): bool
    {
        return $participant->investments()->exists()
            || $participant->capitalSnapshotItems()->exists()
            || $participant->profitAllocations()->exists()
            || $participant->fundAllocations()->exists()
            || $participant->depreciationNotes()->exists()
            || $participant->settlementItems()->exists()
            || $participant->notifications()->exists()
            || $participant->supportTickets()->exists();
    }
}
