<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Models\Notification;
use App\Models\Participant;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationActionController
{
    public function __construct(private readonly SecurityAuditService $securityAudit)
    {
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $participant = $this->participant($request);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => Notification::query()
                    ->where('participant_id', $participant->id)
                    ->where('is_read', false)
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $participant = $this->participant($request);

        if ((int) $notification->participant_id !== $participant->id) {
            $this->deny($request, $participant, $notification, 'notification_read');
        }

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => ['id' => $notification->id, 'read' => true],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $participant = $this->participant($request);

        $updated = Notification::query()
            ->where('participant_id', $participant->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data' => ['updated' => $updated],
        ]);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }

    private function deny(Request $request, Participant $participant, Notification $notification, string $action): never
    {
        $this->securityAudit->log($action, $participant, 'notification', $notification->getKey(), [
            'action' => 'view',
            'endpoint' => $request->path(),
            'reason' => 'ownership_mismatch',
        ]);

        abort(403, 'Forbidden.');
    }
}