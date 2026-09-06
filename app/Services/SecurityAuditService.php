<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Participant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;

class SecurityAuditService
{
    public function log(
        string $action,
        Admin|Participant|null $actor = null,
        ?string $auditableType = null,
        mixed $auditableId = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $actorType = $actor ? $actor::class : 'system';
        $actorId = $actor?->getKey();

        $safeMetadata = $this->sanitizeMetadata($metadata);

        AuditLog::query()->create([
            'auditable_type' => $auditableType ?? 'security_event',
            'auditable_id' => $auditableId ?? 0,
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId ?? 0,
            'metadata' => $safeMetadata,
            'ip_address' => $ipAddress ?? Request::ip(),
            'user_agent' => $userAgent ?? Request::header('User-Agent'),
            'created_at' => now(),
        ]);
    }

    protected function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (str_contains($normalizedKey, 'password') || str_contains($normalizedKey, 'token') || str_contains($normalizedKey, 'secret') || str_contains($normalizedKey, 'credential')) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
                continue;
            }

            if (is_string($value) && (
                str_contains(strtolower($value), 'password') ||
                str_contains(strtolower($value), 'token') ||
                str_contains(strtolower($value), 'secret') ||
                str_contains(strtolower($value), 'bearer ')
            )) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
