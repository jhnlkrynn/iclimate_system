<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which of the signed-in farmer's farms they are viewing (the My Farm selector in the top bar).
 *
 * The choice is kept in the session as a farm id, and every read checks it against the farmer's own farms in use: a farm
 * id from the browser is never trusted on its own. With no choice (or one that is no longer theirs or was archived) it is
 * the primary farm, else their first farm, else none. The farmer's farm list is read once per request.
 *
 * Until the farms table exists (the multi-farm migration not run yet) there are no farms and every page keeps its
 * single-farm behaviour.
 */
final class ActiveFarm
{
    public const SESSION_KEY = 'farmer.active_farm_id';

    public static function available(): bool
    {
        return SchemaCache::hasTable('farms') && SchemaCache::hasColumn('farm_boundaries', 'farm_id');
    }

    /** @return Collection<int, Farm> the farmer's farms in use: the primary farm first, then in the order they were added */
    public static function farms(User $user): Collection
    {
        if (! self::available()) {
            return collect();
        }

        $request = app()->bound('request') ? request() : null;
        $key = 'active-farm.farms.'.$user->getKey();
        if ($request !== null && $request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $farms = Farm::query()->ownedBy($user)->active()
            ->with('boundary:id,farm_id,calculated_area_hectares')
            ->orderByDesc('is_primary')->orderBy('id')->get();
        $request?->attributes->set($key, $farms);

        return $farms;
    }

    /** Read the farm list again (after a farm was added, changed or archived in this request). */
    public static function forget(User $user): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove('active-farm.farms.'.$user->getKey());
        }
    }

    public static function current(User $user): ?Farm
    {
        $farms = self::farms($user);
        if ($farms->isEmpty()) {
            return null;
        }
        $chosen = self::chosenId();

        return ($chosen !== null ? $farms->firstWhere('id', $chosen) : null)
            ?? $farms->firstWhere('is_primary', true)
            ?? $farms->first();
    }

    /** The farmer's own farm with this id, or null (another farmer's, archived or made up): nothing is changed then. */
    public static function select(User $user, int $farmId): ?Farm
    {
        $farm = self::farms($user)->firstWhere('id', $farmId);
        if ($farm !== null && self::hasSession()) {
            request()->session()->put(self::SESSION_KEY, $farm->getKey());
        }

        return $farm;
    }

    /** One of the farmer's own farms in use, by id; null for any other id. */
    public static function owned(User $user, int|string|null $farmId): ?Farm
    {
        return is_numeric($farmId) ? self::farms($user)->firstWhere('id', (int) $farmId) : null;
    }

    private static function chosenId(): ?int
    {
        if (! self::hasSession()) {
            return null;
        }
        $id = request()->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    private static function hasSession(): bool
    {
        return app()->bound('request') && request()->hasSession();
    }
}
