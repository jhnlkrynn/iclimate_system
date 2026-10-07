<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: a module IT took away from a role (Roles & Permissions) is closed to it:
 * the person is sent to their dashboard with a short note. Pages outside every module are not affected. Reads the
 * settings file only (no database query).
 */
class CheckModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ModuleAccess::permits($user->role, $request->route()?->getName())) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Your role does not have access to this page.'], 403);
        }

        return redirect()->route($user->dashboardRoute())->with('error', 'Your role does not have access to that page. Ask IT if you need it.');
    }
}
