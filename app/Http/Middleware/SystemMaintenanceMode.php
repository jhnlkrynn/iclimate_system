<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: Maintenance mode (System Settings). While it is on, everyone except IT
 * sees the maintenance page; the sign-in pages stay open so IT can log in and switch it off. Reads the settings file
 * only (no database query).
 */
class SystemMaintenanceMode
{
    /** Routes that stay open while maintenance is on (sign in / out, captcha, the landing page). */
    private const OPEN = ['login', 'logout', 'captcha.*', 'password.*', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        // The landing page holds the sign-in card (GET /login only sends there), and POST /login has no route name.
        if (! SystemSettings::maintenanceOn() || $request->user()?->role === User::ROLE_IT_EXPERT || $request->routeIs(...self::OPEN) || $request->is('/', 'login')) {
            return $next($request);
        }

        $message = (string) SystemSettings::get('maintenance_message');

        return $request->expectsJson()
            ? response()->json(['message' => $message], 503)
            : response()->view('errors.system-maintenance', ['message' => $message], 503);
    }
}
