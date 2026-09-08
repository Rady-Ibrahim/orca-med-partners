<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Actions\Participant\GetParticipantDashboardDataAction;
use App\Http\Requests\ParticipantCollectionRequest;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController
{
    public function me(Request $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->profile($this->participant($request)));
    }

    public function investment(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->investments($this->participant($request), $request->filters()));
    }

    public function capital(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->capital($this->participant($request), $request->filters()));
    }

    public function profits(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->profits($this->participant($request), $request->filters()));
    }

    public function funds(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->funds($this->participant($request), $request->filters()));
    }

    public function depreciation(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->depreciation($this->participant($request), $request->filters()));
    }

    public function settlements(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->settlements($this->participant($request), $request->filters()));
    }

    public function notifications(ParticipantCollectionRequest $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        return $this->success($action->notifications($this->participant($request), $request->filters()));
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');
        return $request->user();
    }

    private function success(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
