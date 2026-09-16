<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Models\Participant;
use App\Services\RefreshTokenService;
use App\Services\SecurityAuditService;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParticipantAuthController
{
    public function __construct(
        protected RefreshTokenService $refreshTokenService,
        protected SecurityAuditService $securityAuditService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $participant = Participant::query()->where('username', $validated['username'])->first();

        if (! $participant || ! Hash::check($validated['password'], $participant->password)) {
            $this->securityAuditService->log('participant_login_failure', null, 'participant', null, [
                'username' => $validated['username'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (! $participant->isActive()) {
            $this->securityAuditService->log('inactive_account_attempt', $participant, 'participant', $participant->id, [
                'context' => 'participant_login',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
            ], 403);
        }

        $accessToken = $participant->createToken('participant-api', ['*'])->plainTextToken;
        $refreshToken = $this->refreshTokenService->issue($participant, 'participant-api');

        $this->securityAuditService->log('participant_login_success', $participant, 'participant', $participant->id, [
            'context' => 'participant_login',
        ]);

        return $this->authenticationResponse($participant, $accessToken, $refreshToken, 'Participant authenticated successfully.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $token = $this->refreshTokenService->validate($validated['refresh_token']);

        if (! $token || ! $token->tokenable || ! $token->tokenable instanceof Participant) {
            $this->securityAuditService->log('participant_refresh_failure', null, 'participant', null, [
                'context' => 'participant_refresh',
                'refresh_token' => $validated['refresh_token'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        if (! $token->tokenable->isActive()) {
            $this->refreshTokenService->revokeForModel($token->tokenable);
            $this->securityAuditService->log('inactive_account_attempt', $token->tokenable, 'participant', $token->tokenable->id, [
                'context' => 'participant_refresh',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
            ], 403);
        }

        $newRefreshToken = $this->refreshTokenService->rotate($validated['refresh_token']);
        $user = $token->tokenable;
        $accessToken = $user->createToken('participant-api', ['*'])->plainTextToken;

        $this->securityAuditService->log('participant_refresh_success', $user, 'participant', $user->id, [
            'context' => 'participant_refresh',
        ]);

        return $this->authenticationResponse($user, $accessToken, $newRefreshToken, 'Participant token refreshed successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $participant = $request->user();

        if (! $participant instanceof Participant || ! Hash::check($validated['current_password'], $participant->password)) {
            $this->securityAuditService->log('participant_password_change_denied', $participant, 'participant', $participant?->id, [
                'context' => 'password_change',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $participant->update([
            'password' => Hash::make($validated['password']),
        ]);

        $participant->tokens()->delete();
        $this->refreshTokenService->revokeForModel($participant);
        $this->securityAuditService->log('participant_password_change', $participant, 'participant', $participant->id, [
            'context' => 'password_change',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    private function authenticationResponse(Participant $user, string $accessToken, string $refreshToken, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('sanctum.expiration', 60) * 60,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ],
        ]);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $participant = Participant::query()->where('email', $validated['email'])->first();

        if ($participant) {
            $token = hash_hmac('sha256', (string) Str::random(60), config('app.key'));
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $participant->email], ['token' => $token, 'created_at' => now()]);
            $this->securityAuditService->log('participant_password_reset_request', $participant, 'participant', $participant->id, [
                'context' => 'password_reset_request',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $row = DB::table('password_reset_tokens')->where('token', $validated['token'])->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $createdAt = \Illuminate\Support\Carbon::parse($row->created_at ?? now());

        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $row->email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $email = $row->email;
        $participant = Participant::query()->where('email', $email)->first();

        if (! $participant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $participant->update([
            'password' => Hash::make($validated['password']),
        ]);

        $participant->tokens()->delete();
        $this->refreshTokenService->revokeForModel($participant);
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $this->securityAuditService->log('participant_password_reset_success', $participant, 'participant', $participant->id, [
            'context' => 'password_reset_success',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Participant) {
            $user->tokens()->delete();
            $this->refreshTokenService->revokeForModel($user);
            $this->securityAuditService->log('participant_logout', $user, 'participant', $user->id, [
                'context' => 'logout',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Participant logged out successfully.',
        ]);
    }
}
