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

        $request->setUserResolver(fn() => $token->tokenable);

        return $next($request);
    }
}
