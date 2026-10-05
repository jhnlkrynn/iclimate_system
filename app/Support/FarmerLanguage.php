<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Farmer dashboard's language (English or Filipino). One dashboard, one set of templates: only the words change,
 * read from lang/en/farmer.php or lang/fil/farmer.php.
 *
 * The choice is kept on the farmer's account (users.preferred_language) so it follows them to any device, and in the
 * session as well, so it still works before that column has been migrated.
 */
final class FarmerLanguage
{
    public const SESSION_KEY = 'farmer_language';

    public const DEFAULT = 'en';

    /** Language code => the name shown in the selector (each in its own language). */
    public const SUPPORTED = ['en' => 'English', 'fil' => 'Filipino'];

    public static function isSupported(?string $code): bool
    {
        return is_string($code) && array_key_exists($code, self::SUPPORTED);
    }

    /** The farmer's saved choice: the account first, then the session, else English. */
    public static function resolve(Request $request): string
    {
        $user = $request->user();
        $saved = $user && self::hasColumn() ? $user->getAttribute('preferred_language') : null;
        if (self::isSupported($saved)) {
            return $saved;
        }

        $session = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return self::isSupported($session) ? $session : self::DEFAULT;
    }

    public static function store(Request $request, User $user, string $code): void
    {
        if (! self::isSupported($code)) {
            return;
        }
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $code);
        }
        if (self::hasColumn()) {
            // A normal save, not saveQuietly(): its "saved" event drops the cached signed-in user
            // (CachedEloquentUserProvider), so the very next page is already in the new language.
            $user->forceFill(['preferred_language' => $code])->save();
        }
    }

    private static function hasColumn(): bool
    {
        try {
            return SchemaCache::hasColumn('users', 'preferred_language');
        } catch (Throwable) {
            return false;
        }
    }
}
