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

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->routeRequiresAuth($request)) {
            return $next($request);
        }

        try {
            $user = $request->user();

            if ($user && Cache::add("activity-throttle:{$user->id}", true, self::THROTTLE_SECONDS)) {
                User::whereKey($user->id)->update(['last_active_at' => now()]);
            }
        } catch (QueryException $exception) {
            Log::warning('Unable to track user activity.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $next($request);
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
