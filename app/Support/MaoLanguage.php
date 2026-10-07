<?php

namespace App\Support;

use App\Models\User;

/**
 * The MAO account's language (2026-10-08). English unless the MAO picked Filipino (Tagalog) with the selector on the
 * MAO Dashboard. The choice is kept on the account (users.preferred_language, the same column the farmers use), so it
 * follows the MAO to any device.
 *
 * What follows it: the MAO Dashboard, the MAO menu, the MAO Planner's name and the "no data yet" bell notifications.
 * Every other MAO page is written in English only.
 */
final class MaoLanguage
{
    public const DEFAULT = 'en';

    /** Language code => the name shown in the selector. */
    public const SUPPORTED = ['en' => 'English', 'fil' => 'Filipino (Tagalog)'];

    public static function of(?User $user): string
    {
        return $user?->getAttribute('preferred_language') === 'fil' ? 'fil' : self::DEFAULT;
    }
}
