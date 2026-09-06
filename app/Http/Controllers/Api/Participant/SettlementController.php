<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Actions\Settlements\ListParticipantSettlementsAction;
use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettlementController
{
    public function index(Request $request, ListParticipantSettlementsAction $action): JsonResponse
    {
        $participant = $request->user();
        abort_unless($participant instanceof Participant, 403, 'Participant access required.');

        return response()->json(['success' => true, 'data' => $action->execute($participant)]);
    }

    public function show(Request $request, Settlement $settlement): JsonResponse
    {
        $participant = $request->user();
        abort_unless($participant instanceof Participant && $settlement->participantItems($participant->id)->exists(), 403, 'Forbidden.');

        return response()->json([
            'success' => true,
            'data' => $settlement->load(['items' => fn($query) => $query->where('participant_id', $participant->id)]),
        ]);
    }
}
