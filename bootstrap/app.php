<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.admin.context' => \App\Http\Middleware\EnsureAdminContext::class,
            'ensure.participant.context' => \App\Http\Middleware\EnsureParticipantContext::class,
            'ensure.web.admin' => \App\Http\Middleware\EnsureWebAdminContext::class,
        ]);

        $middleware->trustProxies(
            at: explode(',', (string) env('TRUSTED_PROXIES', '*')),
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $fail = fn (\Illuminate\Http\Request $request, string $message, int $status, array $errors = [], array $headers = []) => (
            $request->is('api/*')
                ? response()->json(array_merge([
                    'success' => false,
                    'message' => $message,
                ], $errors !== [] ? ['errors' => $errors] : []), $status, $headers)
                : null
        );

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) use ($fail) {
            return $fail($request, $e->getMessage(), $e->status, $e->errors());
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) use ($fail) {
            return $fail($request, 'Unauthenticated.', 401);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) use ($fail) {
            $labels = [
                400 => 'Bad request.',
                401 => 'Unauthenticated.',
                403 => 'Forbidden.',
                404 => 'Not found.',
                405 => 'Method not allowed.',
                419 => 'Request timed out.',
                429 => 'Too many requests.',
            ];
            $message = $e->getMessage() ?: ($labels[$e->getStatusCode()] ?? 'Request failed.');

            return $fail($request, $message, $e->getStatusCode(), [], $e->getHeaders());
        });

        $domainExceptions = [
            \App\Domain\Financial\Exceptions\ImmutableFinancialRecordException::class,
            \App\Domain\Financial\Exceptions\InvalidAnnualSettlementException::class,
            \App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException::class,
            \App\Domain\Financial\Exceptions\InvalidGrossProfitException::class,
            \App\Domain\Financial\Exceptions\FundBalanceDriftException::class,
        ];

        foreach ($domainExceptions as $domainExceptionClass) {
            $exceptions->render(function (\RuntimeException $e, \Illuminate\Http\Request $request) use ($fail, $domainExceptionClass) {
                if (! $e instanceof $domainExceptionClass) {
                    return null;
                }

                return $fail($request, $e->getMessage(), 422);
            });
        }
    })->create();
