<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Http\Requests\Toggle2faRequest;
use App\Models\Participant;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class SecuritySettingsController
{
    public function __construct(private readonly SecurityAuditService $securityAudit)
    {
    }

    public function show2fa(Request $request): JsonResponse
    {
        $participant = $this->participant($request);

        return response()->json([
            'success' => true,
            'data' => [
                '2fa_enabled' => $participant->two_factor_enabled,
                'enabled_at' => $participant->two_factor_enabled_at?->toISOString(),
            ],
        ]);
    }

    public function toggle2fa(Toggle2faRequest $request): JsonResponse
    {
        $participant = $this->participant($request);

        abort_unless(Hash::check((string) $request->validated('password'), $participant->password), 422, 'Current password is incorrect.');

        $enable = $request->validated('enable') === null
            ? ! $participant->two_factor_enabled
            : (bool) $request->validated('enable');

        $participant->update([
            'two_factor_enabled' => $enable,
            'two_factor_enabled_at' => $enable ? now() : null,
        ]);

        $this->securityAudit->log('two_factor_toggled', $participant, 'participant', $participant->id, [
            'endpoint' => $request->path(),
            'enabled' => $enable,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                '2fa_enabled' => $participant->two_factor_enabled,
                'enabled_at' => $participant->two_factor_enabled_at?->toISOString(),
            ],
        ]);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }
}