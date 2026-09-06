<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\Admin;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ParticipantController
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', Participant::class);

        return response()->json([
            'success' => true,
            'message' => 'Admin participant listing is available.',
            'data' => [
                'user' => $request->user()->only(['id', 'username']),
            ],
        ]);
    }
}
