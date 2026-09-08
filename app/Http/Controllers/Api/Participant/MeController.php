<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Actions\Participant\GetParticipantDashboardDataAction;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController
{
    public function show(Request $request, GetParticipantDashboardDataAction $action): JsonResponse
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return response()->json([
            'success' => true,
            'data' => $action->profile($request->user()),
        ]);
    }
}
