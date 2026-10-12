<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel revision 4.4 A: an account still on the default MAO password (must_change_password) is sent to the
 * "Change your password" page before anything else. "Skip for now" (allowed while iclimate.mao_allow_skip_password_change
 * is true) lets them in for this session only; the prompt comes back at the next sign-in.
 */
class EnsurePasswordChanged
{
    public const SKIP_KEY = 'password_change_skipped';

    /** Routes that stay open while the change is pending. */
    private const OPEN = ['password.first-change', 'password.first-change.update', 'password.first-change.skip', 'logout', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->must_change_password || $request->session()->get(self::SKIP_KEY)
            || $request->routeIs(...self::OPEN) || $request->expectsJson() || ! $request->isMethod('GET')) {
            return $next($request);
        }

        return redirect()->route('password.first-change');
    }
}
