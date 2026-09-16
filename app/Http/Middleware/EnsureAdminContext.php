<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminContext
{
    private function isExpired(PersonalAccessToken $token): bool
    {
        return $token->expires_at !== null && $token->expires_at->isPast();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            abort(401, 'Unauthenticated.');
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        if (! $token || ! $token->tokenable instanceof Admin) {
            abort(403, 'Admin access required.');
        }

        if ($this->isExpired($token) || ! $token->tokenable->isActive()) {
            $token->delete();

            abort(401, 'Access token expired or account is inactive.');
        }

        $request->setUserResolver(fn() => $token->tokenable);

        return $next($request);
    }
}
