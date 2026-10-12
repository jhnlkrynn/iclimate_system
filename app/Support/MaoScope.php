<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Panel revision 4.4 B: which barangays the signed-in MAO user is working on.
 *
 *  - The Municipal Agriculturist (head) always sees all of Lian.
 *  - An Agricultural Technologist / Extension Worker sees their assigned barangays by default ("assigned" view), and can
 *    switch to the municipal view to read municipality-wide summaries. The municipal view is read-only: records outside
 *    their barangays can be seen there but not changed (canManageBarangay()).
 *  - An MAO account without any assignment yet works municipality-wide, as before this revision.
 *
 * The choice is kept in the session (key below) and switched by MaoAccountController::switchView().
 */
final class MaoScope
{
    public const SESSION_KEY = 'mao_view';

    public const VIEW_ASSIGNED = 'assigned';

    public const VIEW_MUNICIPAL = 'municipal';

    /**
     * Barangays to filter by, or null for all of Lian.
     *
     * @return array<int, string>|null
     */
    public static function barangays(?User $user = null): ?array
    {
        $user ??= auth()->user();
        if (! $user instanceof User || ! $user->isMao() || $user->isMaoHead()) {
            return null;
        }
        $covered = $user->coveredBarangays();
        if ($covered === [] || self::view($user) === self::VIEW_MUNICIPAL) {
            return null;
        }

        return $covered;
    }

    /** "assigned" or "municipal" (the head and unassigned accounts are always municipal). */
    public static function view(?User $user = null): string
    {
        $user ??= auth()->user();
        if (! $user instanceof User || ! $user->isMao() || $user->isMaoHead() || $user->coveredBarangays() === []) {
            return self::VIEW_MUNICIPAL;
        }

        return session(self::SESSION_KEY) === self::VIEW_MUNICIPAL ? self::VIEW_MUNICIPAL : self::VIEW_ASSIGNED;
    }

    /** True when the user can switch between their barangays and the municipal view. */
    public static function canSwitch(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->isMao() && ! $user->isMaoHead() && $user->coveredBarangays() !== [];
    }

    public static function isFiltered(?User $user = null): bool
    {
        return self::barangays($user) !== null;
    }

    /**
     * Whether the user may change a record of this barangay. Staff other than MAO field personnel are not limited here
     * (their own permission checks apply). A record without a barangay (municipal records) stays editable.
     */
    public static function canManageBarangay(?string $barangay, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User || ! $user->isMao() || $user->isMaoHead()) {
            return true;
        }
        $covered = $user->coveredBarangays();
        $name = LianBarangays::canonicalName($barangay);

        return $covered === [] || $name === null || in_array($name, $covered, true);
    }

    /** Limits a query to the user's barangays (no-op for the municipal view). */
    public static function apply(Builder $query, string $column = 'barangay', ?User $user = null): Builder
    {
        $barangays = self::barangays($user);

        return $barangays === null ? $query : $query->whereIn($column, $barangays);
    }

    /** Cache-key part for data that depends on the scope ("all" or a short hash of the barangays). */
    public static function cacheKey(?User $user = null): string
    {
        $barangays = self::barangays($user);

        return $barangays === null ? 'all' : substr(sha1(implode('|', $barangays)), 0, 12);
    }
}
