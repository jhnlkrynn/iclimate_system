<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\SystemAuditLogger;
use App\Support\DefaultPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One place that sets a person's new password (first sign-in, reset link, profile, mobile app): the account no
 * longer needs a password change, every other signed-in session of the account is ended, and "remember me" tokens
 * are rotated. The identity, role, status and all records stay as they are.
 */
final class PasswordChange
{
    public static function apply(User $user, string $plain, ?Request $request = null, string $via = 'web'): void
    {
        $user->forceFill([
            'password' => Hash::make($plain),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        self::endOtherSessions($user, $request?->hasSession() ? $request->session()->getId() : null);
        SystemAuditLogger::record('Changed own password', $request, ['record_type' => 'Account', 'record_id' => $user->id, 'via' => $via], $user);
    }

    /**
     * Sign-in found a known exposed default password: from now on the account must choose its own first.
     */
    public static function flagIfDefault(User $user, ?string $plain): void
    {
        if ($user->must_change_password || ! config('iclimate.password_lockdown', true)) {
            return;
        }
        if (self::isShared($user, $plain)) {
            $user->forceFill(['must_change_password' => true])->saveQuietly();
            \App\Auth\CachedEloquentUserProvider::forget($user->getKey());
        }
    }

    /** The password just typed is a known exposed default password. */
    public static function isShared(User $user, ?string $plain): bool
    {
        return DefaultPasswords::isKnown($plain);
    }

    /** Without the typed password (an open session): the stored hash matches a known exposed default password. */
    public static function hasSharedPassword(User $user): bool
    {
        foreach (DefaultPasswords::known() as $known) {
            if (rescue(fn () => \Illuminate\Support\Facades\Hash::check($known, (string) $user->password), false, false)) {
                return true;
            }
        }

        return false;
    }

    public static function lockdown(): bool
    {
        return (bool) config('auth.default_password_lockdown', true)
            && (bool) config('iclimate.password_lockdown', true);
    }

    /**
     * Whether a shared password is refused for this request. When config('auth.default_password_lockdown') is false,
     * users with a default/seeded password are signed in and routed to password.first-change instead.
     */
    public static function lockdownFor(?\Illuminate\Http\Request $request): bool
    {
        if (! config('auth.default_password_lockdown', true)) {
            return false;
        }
        if (self::lockdown()) {
            return true;
        }
        $ip = (string) $request?->ip();

        return ! in_array($ip, ['127.0.0.1', '::1'], true);
    }

    public static function endOtherSessions(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->getKey())
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))->delete();
    }
}
