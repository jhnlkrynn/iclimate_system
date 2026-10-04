<?php

use App\Http\Middleware\ReadPreparedPredictionsOnly;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            // A database outage must not turn every database-backed page into the generic 500 screen.
            // Walk the exception chain because Laravel commonly wraps PDOException in QueryException.
            for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
                if (! $current instanceof ConnectionException && ! $current instanceof \PDOException) {
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
