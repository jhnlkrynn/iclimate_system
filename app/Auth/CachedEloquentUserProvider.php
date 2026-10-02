<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Loads the signed-in user from a short-lived cache instead of the database on
 * every request. That is one Supabase round trip (hundreds of milliseconds)
 * saved on every authenticated page and click.
 *
 * Safety:
 *  - The entry is private to one user id and stored encrypted (it holds the
 *    password hash, which the session guard and password checks need).
 *  - It is dropped the moment that user is saved or deleted through Eloquent
 *    (role change, deactivation, password change, profile edit), see
 *    User::booted(), and it expires on its own after a few seconds either way.
 *  - Only retrieveById() is cached. Logging in (credentials), remember-me
 *    tokens and password validation still read the database.
 */
class CachedEloquentUserProvider extends EloquentUserProvider
{
    private const KEY = 'auth:user:';

    public function retrieveById($identifier): ?Authenticatable
    {
        $ttl = (int) config('auth.user_cache_seconds', 30);

        if ($ttl <= 0) {
            return parent::retrieveById($identifier);
        }

        $cached = Cache::get(self::KEY.$identifier);

        if (is_string($cached)) {
            try {
                $attributes = json_decode(Crypt::decryptString($cached), true, flags: JSON_THROW_ON_ERROR);

                if (is_array($attributes)) {
                    return $this->createModel()->newFromBuilder($attributes);
                }
            } catch (Throwable) {
                // Unreadable entry (e.g. APP_KEY changed): fall through and read the database.
                Cache::forget(self::KEY.$identifier);
            }
        }

        $user = parent::retrieveById($identifier);

        if ($user !== null) {
            Cache::put(self::KEY.$identifier, Crypt::encryptString(json_encode($user->getAttributes())), $ttl);
        }

        return $user;
    }

    /** Called when the user changes, so the next request sees the change at once. */
    public static function forget(mixed $identifier): void
    {
        Cache::forget(self::KEY.$identifier);
    }
}
