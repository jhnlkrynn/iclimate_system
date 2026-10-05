<?php

use App\Http\Middleware\ReadPreparedPredictionsOnly;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetFarmerLanguage;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            TrackUserActivity::class,
            // No page request trains or rebuilds a model; it reads what the scheduled jobs prepared.
            ReadPreparedPredictionsOnly::class,
        ]);

        $middleware->api(append: [
            ReadPreparedPredictionsOnly::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'farmer.language' => SetFarmerLanguage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every reported error says where it happened and for which role (a fatal timeout otherwise logs only the
        // user id). No input, headers, cookies or tokens: only the request line and the route.
        $exceptions->context(function (): array {
            $request = app()->bound('request') ? request() : null;
            if ($request === null || $request->route() === null) {                 // commands and queue jobs
                return [];
            }

            return array_filter([
                'path' => '/'.ltrim($request->path(), '/'),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'role' => rescue(fn () => $request->user()?->role, null, false),
                'request_id' => $request->headers->get('X-Request-Id'),
            ], fn ($value) => $value !== null && $value !== '');
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            // A database outage must not turn every database-backed page into the generic 500 screen.
            // Only an unreachable or dropped connection counts as an outage: any other database error
            // (bad SQL, missing table) is a bug and goes through the normal 500 path, so it is not
            // disguised as "Data Service Unavailable". With APP_DEBUG on, Laravel shows the real exception.
            if (config('app.debug')) {
                return null;
            }

            $lostConnection = app(\Illuminate\Contracts\Database\LostConnectionDetector::class);

            // Walk the exception chain because Laravel commonly wraps PDOException in QueryException.
            for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
                $unavailable = $current instanceof ConnectionException
                    || $current instanceof \Illuminate\Database\LostConnectionException
                    // SQLSTATE class 08 = connection exception (e.g. 08006 could not connect).
                    || ($current instanceof \PDOException && (str_starts_with((string) $current->getCode(), '08') || $lostConnection->causedByLostConnection($current)));

                if (! $unavailable) {
                    continue;
                }

                Log::error('Database unavailable while handling a request.', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'The data service is temporarily unavailable. Please try again shortly.',
                    ], 503);
                }

                return response()->view('errors.503', [
                    'exception' => $exception,
                    'title' => 'Data Service Unavailable',
                    'message' => 'The data service is temporarily unavailable. Please try again shortly.',
                ], 503);
            }

            if (! $exception instanceof HttpExceptionInterface || $exception->getStatusCode() !== 505) {
                return null;
            }

            Log::warning('HTTP 505 intercepted before response rendering.', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The request could not be processed because of an unsupported HTTP version.',
                ], 400);
            }

            return response()->view('errors.500', [
                'exception' => $exception,
            ], 500);
        });
    })->create();
