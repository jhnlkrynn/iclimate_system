<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    private const THROTTLE_SECONDS = 60;

    private const ATTRIBUTE = 'track_activity_user_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->routeRequiresAuth($request)) {
            return $next($request);
        }

        try {
            $user = $request->user();

            if ($user && Cache::add("activity-throttle:{$user->id}", true, self::THROTTLE_SECONDS)) {
                // At most once a minute. The write happens in terminate(), after the response is built, so it
                // never sits in front of the page.
                $request->attributes->set(self::ATTRIBUTE, $user->id);
            }
        } catch (QueryException $exception) {
            Log::warning('Unable to track user activity.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $next($request);
    }

    /** Runs once per request, after the response has been prepared. */
    public function terminate(Request $request, Response $response): void
    {
        $userId = $request->attributes->get(self::ATTRIBUTE);

        if ($userId === null) {
            return;
        }

        try {
            User::whereKey($userId)->update(['last_active_at' => now()]);
        } catch (QueryException $exception) {
            Log::warning('Unable to track user activity.', ['message' => $exception->getMessage()]);
        }
    }

    private function routeRequiresAuth(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        return collect($route->gatherMiddleware())
            ->contains(fn (string $middleware): bool => $middleware === 'auth' || str_starts_with($middleware, 'auth:'));
    }
}
