<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account the moment IT sets it to Inactive. The status used to be checked only when logging in,
 * so someone already signed in (or remembered by the browser) kept full access after being deactivated.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'This account is inactive. Please contact the administrator.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasApplicationAccess()) {
            return $next($request);
        }

        SystemAuditLogger::record('Inactive Account Signed Out', $request, user: $user);

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Manuscript – Naicel: a Pending or Rejected farmer is told why (User::signInRefusal).
        $message = in_array($user->role, User::ROLES, true) ? User::signInRefusal($user->status) : self::MESSAGE;

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
