<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect()
                ->route($user->dashboardRoute())
                ->with('error', "You don't have access to that page.");
        }

        return $next($request);
    }
}