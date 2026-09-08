<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;

final class ParticipantNotificationService
{
    public function afterCommit(\Closure $callback): void
    {
        DB::afterCommit($callback);
    }

    public function createForParticipant(Participant $participant, string $type, string $title, string $body, ?Admin $admin = null, array $metadata = []): void
    {
        Notification::query()->create([
            'participant_id' => $participant->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'is_read' => false,
            'metadata' => $this->safeMetadata($metadata),
            'created_by_admin_id' => $admin?->id,
        ]);
    }

    private function safeMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);
            if (str_contains($normalized, 'password') || str_contains($normalized, 'token') || str_contains($normalized, 'secret') || str_contains($normalized, 'credential')) {
                $metadata[$key] = '[redacted]';
            }
        }

        return $metadata;
    }
}
