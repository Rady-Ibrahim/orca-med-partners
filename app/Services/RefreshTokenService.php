<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function issue(Admin|Participant $user, string $purpose): string
    {
        $rawToken = hash('sha256', Str::random(64));
        $family = (string) Str::uuid();

        $refreshToken = RefreshToken::query()->create([
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->getKey(),
            'family' => $family,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'last_used_at' => now(),
        ]);

        return $rawToken;
    }

    public function rotate(string $rawRefreshToken): string
    {
        $hash = hash('sha256', $rawRefreshToken);

        $current = RefreshToken::query()->where('token_hash', $hash)->first();

        if (! $current || ! $current->isActive()) {
            throw new \RuntimeException('Invalid refresh token.');
        }

        $current->update([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ]);

        $newRawToken = hash('sha256', Str::random(64));

        $newToken = RefreshToken::query()->create([
            'tokenable_type' => $current->tokenable_type,
            'tokenable_id' => $current->tokenable_id,
            'family' => $current->family,
            'token_hash' => hash('sha256', $newRawToken),
            'expires_at' => now()->addDays(7),
            'replaced_by_token_id' => null,
            'last_used_at' => now(),
        ]);

        $current->update(['replaced_by_token_id' => $newToken->getKey()]);

        return $newRawToken;
    }

    public function revokeForModel(Admin|Participant $user): void
    {
        RefreshToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeByRawToken(string $rawRefreshToken): bool
    {
        $hash = hash('sha256', $rawRefreshToken);

        $token = RefreshToken::query()->where('token_hash', $hash)->first();

        if (! $token) {
            return false;
        }

        $token->update(['revoked_at' => now(), 'last_used_at' => now()]);

        return true;
    }

    public function validate(string $rawRefreshToken): ?RefreshToken
    {
        $hash = hash('sha256', $rawRefreshToken);

        $token = RefreshToken::query()->where('token_hash', $hash)->first();

        if (! $token || ! $token->isActive()) {
            return null;
        }

        return $token;
    }
}
