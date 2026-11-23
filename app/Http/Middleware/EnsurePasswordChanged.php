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
        // A session of an account whose password is shared (opened before the lockdown) is ended on any route: that
        // password cannot prove who is using the session, so it may not be used to choose a new one either.
        if ($user && $user->must_change_password && \App\Services\Accounts\PasswordChange::lockdownFor($request)
            && ! $request->routeIs('logout') && $this->sharedPassword($request, $user)) {
            $method = \App\Services\Accounts\AccountRecovery::method($user);
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Choose a new password with the code or email link.', 'recovery_required' => true, 'recovery_method' => $method], Response::HTTP_CONFLICT);
            }

            return redirect()->route('password.request')->with('status', 'This account uses a default password and must set a new password before signing in.');
        }

        // The session "skip" only counts while skipping is allowed (off by default): an old skipped session ends here.
        $skipped = config('iclimate.mao_allow_skip_password_change', false) && $request->hasSession() && $request->session()->get(self::SKIP_KEY);
        if (! $user || ! $user->must_change_password || ! config('auth.force_default_password_change', true) || $skipped
            || $request->routeIs(...self::OPEN)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You must change your password before continuing.'], Response::HTTP_CONFLICT);
        }

        return redirect()->route('password.first-change');
    }

    /** Memoised per session and stored hash: the bcrypt comparison runs once, not on every request. */
    private function sharedPassword(Request $request, \App\Models\User $user): bool
    {
        $key = 'password_unique_checked:'.sha1((string) $user->password);
        if ($request->hasSession() && $request->session()->get($key) === true) {
            return false;
        }
        $shared = \App\Services\Accounts\PasswordChange::hasSharedPassword($user);
        if (! $shared && $request->hasSession()) {
            $request->session()->put($key, true);
        }

        return $shared;
    }
}
