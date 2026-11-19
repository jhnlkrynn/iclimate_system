<?php

namespace App\Support;

/**
 * Shared or published default passwords (config iclimate.known_default_passwords). They are compared only with a
 * password the person has just typed; nothing is stored, logged or shown.
 */
final class DefaultPasswords
{
    /** @return list<string> */
    public static function known(): array
    {
        return array_values(array_filter((array) config('iclimate.known_default_passwords', []), fn ($p) => is_string($p) && $p !== ''));
    }

    public static function isKnown(?string $plain): bool
    {
        if ($plain === null || $plain === '') {
            return false;
        }
        foreach (self::known() as $known) {
            if (hash_equals($known, $plain)) {
                return true;
            }
        }

        return false;
    }
}
