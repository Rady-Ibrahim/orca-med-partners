<?php

use App\Domain\Financial\Exceptions\FundBalanceDriftException;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Http\Middleware\EnsureApiContext;
use App\Http\Middleware\EnsureWebAdminContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ensure.api.context' => EnsureApiContext::class,
            'ensure.web.admin' => EnsureWebAdminContext::class,
        ]);

        $middleware->trustProxies(
            at: explode(',', (string) env('TRUSTED_PROXIES', '*')),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $fail = fn (Request $request, string $message, int $status, array $errors = [], array $headers = []) => (
            $request->is('api/*')
                ? response()->json(array_merge([
                    'success' => false,
                    'message' => $message,
                ], $errors !== [] ? ['errors' => $errors] : []), $status, $headers)
                : null
        );

        $exceptions->render(function (ValidationException $e, Request $request) use ($fail) {
            return $fail($request, $e->getMessage(), $e->status, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($fail) {
            return $fail($request, 'Unauthenticated.', 401);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($fail) {
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
            ImmutableFinancialRecordException::class,
            InvalidAnnualSettlementException::class,
            InvalidCapitalSnapshotException::class,
            InvalidGrossProfitException::class,
            FundBalanceDriftException::class,
        ];

        foreach ($domainExceptions as $domainExceptionClass) {
            $exceptions->render(function (RuntimeException $e, Request $request) use ($fail, $domainExceptionClass) {
                if (! $e instanceof $domainExceptionClass) {
                    return null;
                }

                return $fail($request, $e->getMessage(), 422);
            });
        }
    })->create();
