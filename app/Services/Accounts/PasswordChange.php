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
     * Sign-in found a shared password: from now on the account must choose its own first. "Shared" is a published
     * default, or a password hash identical to another account's (hashes are salted, so an identical hash can only
     * come from a copied password: imports and seeders did that for whole batches of accounts).
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

    /** The password just typed is shared: a published default, or the account's hash is copied on another account. */
    public static function isShared(User $user, ?string $plain): bool
    {
        return DefaultPasswords::isKnown($plain) || self::hashIsCopied($user);
    }

    /** Without the typed password (an open session): copied hash, or the stored hash is a published default. */
    public static function hasSharedPassword(User $user): bool
    {
        if (self::hashIsCopied($user)) {
            return true;
        }
        foreach (DefaultPasswords::known() as $known) {
            if (rescue(fn () => \Illuminate\Support\Facades\Hash::check($known, (string) $user->password), false, false)) {
                return true;
            }
        }

        return false;
    }

    public static function lockdown(): bool
    {
        return (bool) config('iclimate.password_lockdown', true);
    }

    /**
     * Whether a shared password is refused for this request. With the rollout switch off, it is still refused for
     * any request that does not come from this machine: a published password must never work from the network, even
     * if a development instance is exposed or production starts with the switch off. (Behind Nginx, PHP-FPM receives
     * the real client address; no trusted proxies are configured, so X-Forwarded-For cannot fake a loopback address.)
     */
    public static function lockdownFor(?\Illuminate\Http\Request $request): bool
    {
        if (self::lockdown()) {
            return true;
        }
        $ip = (string) $request?->ip();

        return ! in_array($ip, ['127.0.0.1', '::1'], true);
    }

    private static function hashIsCopied(User $user): bool
    {
        return User::withDeleted()->where('password', $user->password)->whereKeyNot($user->getKey())->exists();
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
