<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Participant;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureParticipantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            abort(401, 'Unauthenticated.');
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        if (! $token || ! $token->tokenable instanceof Participant) {
            abort(403, 'Participant access required.');
        }

        $request->setUserResolver(fn () => $token->tokenable);

        return $next($request);
    }
}
