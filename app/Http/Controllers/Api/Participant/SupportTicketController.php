<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Http\Requests\SupportTicketRequest;
use App\Models\Participant;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupportTicketController
{
    public function store(SupportTicketRequest $request): JsonResponse
    {
        $participant = $this->participant($request);
        $input = $request->validated();

        $ticket = SupportTicket::query()->create([
            'participant_id' => $participant->id,
            'subject' => $input['subject'],
            'message' => $input['message'],
            'category' => $input['category'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at?->toISOString(),
            ],
        ], 201);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }
}