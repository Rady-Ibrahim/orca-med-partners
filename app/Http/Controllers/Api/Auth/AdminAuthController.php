<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Models\Admin;
use App\Services\RefreshTokenService;
use App\Services\SecurityAuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthController
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

        $admin = Admin::query()->where('username', $validated['username'])->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            $this->securityAuditService->log('admin_login_failure', null, 'admin', null, [
                'username' => $validated['username'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (! $admin->isActive()) {
            $this->securityAuditService->log('inactive_account_attempt', $admin, 'admin', $admin->id, [
                'context' => 'admin_login',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
            ], 403);
        }

        $accessToken = $admin->createToken('admin-api', ['*'])->plainTextToken;
        $refreshToken = $this->refreshTokenService->issue($admin, 'admin-api');

        $this->securityAuditService->log('admin_login_success', $admin, 'admin', $admin->id, [
            'context' => 'admin_login',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin authenticated successfully.',
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                ],
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $token = $this->refreshTokenService->validate($validated['refresh_token']);

        if (! $token || ! $token->tokenable || ! $token->tokenable instanceof Admin) {
            $this->securityAuditService->log('admin_refresh_failure', null, 'admin', null, [
                'context' => 'admin_refresh',
                'refresh_token' => $validated['refresh_token'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        $newRefreshToken = $this->refreshTokenService->rotate($validated['refresh_token']);
        $user = $token->tokenable;
        $accessToken = $user->createToken('admin-api', ['*'])->plainTextToken;

        $this->securityAuditService->log('admin_refresh_success', $user, 'admin', $user->id, [
            'context' => 'admin_refresh',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin token refreshed successfully.',
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = $request->user();

        if (! $admin instanceof Admin || ! Hash::check($validated['current_password'], $admin->password)) {
            $this->securityAuditService->log('admin_password_change_denied', $admin, 'admin', $admin?->id, [
                'context' => 'password_change',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        $admin->tokens()->delete();
        $this->refreshTokenService->revokeForModel($admin);
        $this->securityAuditService->log('admin_password_change', $admin, 'admin', $admin->id, [
            'context' => 'password_change',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = Admin::query()->where('email', $validated['email'])->first();

        if ($admin) {
            $token = hash_hmac('sha256', (string) Str::random(60), config('app.key'));
            $this->storeResetToken($admin->email, $token);
            $this->securityAuditService->log('admin_password_reset_request', $admin, 'admin', $admin->id, [
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

        $email = $this->findResetEmailByToken($validated['token']);

        if (! $email) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        $admin->tokens()->delete();
        $this->refreshTokenService->revokeForModel($admin);
        $this->clearResetToken($email);
        $this->securityAuditService->log('admin_password_reset_success', $admin, 'admin', $admin->id, [
            'context' => 'password_reset_success',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    protected function storeResetToken(string $email, string $token): void
    {
        $table = \Illuminate\Support\Facades\DB::table('password_reset_tokens');
        $table->updateOrInsert(['email' => $email], ['token' => $token, 'created_at' => now()]);
    }

    protected function findResetEmailByToken(string $token): ?string
    {
        $row = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('token', $token)->first();

        if (! $row) {
            return null;
        }

        $createdAt = Carbon::parse($row->created_at ?? now());

        if ($createdAt->addMinutes(60)->isPast()) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $row->email)->delete();

            return null;
        }

        return $row->email;
    }

    protected function clearResetToken(string $email): void
    {
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $email)->delete();
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Admin) {
            $user->tokens()->delete();
            $this->refreshTokenService->revokeForModel($user);
            $this->securityAuditService->log('admin_logout', $user, 'admin', $user->id, [
                'context' => 'logout',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin logged out successfully.',
        ]);
    }
}
