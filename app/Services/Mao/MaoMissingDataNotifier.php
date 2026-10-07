<?php

namespace App\Services\Mao;

use App\Models\ClimateRecord;
use App\Models\MaoActivity;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\RiceSeason;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;

/**
 * MAO/IT – Naicel (2026-10-07): data the MAO has not added yet goes to the notification bell, not to empty cards on the
 * MAO dashboard. Same behaviour as the farmer reminders (App\Services\Farmer\FarmerMissingInfoNotifier).
 *
 * One notification per missing item, for every MAO account, in the MAO's language (Filipino unless their saved language
 * is English), each linking to the page where the data is added. It is never duplicated (the item is the notification's
 * category, e.g. "mao-setup:climate_month") and it is removed once the data exists:
 *  - climate_month      no climate record dated this month                      → Climate Records, Add
 *  - production_season  no rice production record for the current rice season  → Rice Production, Add
 *  - advisories         no published advisory in effect today                   → Advisories, New Advisory
 *  - planner            no MAO Planner activity from today onward               → MAO Planner
 *
 * When it runs: every minute with the dashboard warm-up (iclimate:warm-dashboards, so a new month or season is noticed)
 * and once after any save or delete of those records (queueSyncAll). Never while a page loads, so the MAO dashboard stays
 * a read of its prepared copy (PagePerformanceTest). Reads saved rows only: no provider is called and no model is run.
 */
class MaoMissingDataNotifier
{
    public const CATEGORY_PREFIX = 'mao-setup:';

    public const ITEMS = ['climate_month', 'production_season', 'advisories', 'planner'];

    /** Container keys: a check of every MAO account is waiting / the after-response callback is registered. */
    private const SYNC_QUEUED = 'mao.missing-data.sync-queued';

    private const SYNC_REGISTERED = 'mao.missing-data.sync-registered';

    /**
     * A climate record, rice production record, advisory or planner activity was saved or deleted: every MAO account
     * is checked again once, after the response is sent (many saves in one request, e.g. an import, cost one check).
     */
    public static function queueSyncAll(): void
    {
        // Kept in the container, not in a static: a fresh application (the next test, the next request) starts clean.
        // Where no response is sent (a queue job), nothing runs here; the next MAO dashboard visit checks again.
        $app = app();
        $app->instance(self::SYNC_QUEUED, true);
        if ($app->bound(self::SYNC_REGISTERED)) {
            return;
        }
        $app->instance(self::SYNC_REGISTERED, true);
        $app->terminating(function () use ($app): void {
            if (! $app->bound(self::SYNC_QUEUED)) {
                return;
            }
            $app->forgetInstance(self::SYNC_QUEUED);
            rescue(fn () => app(self::class)->syncAll(), null, true);
        });
    }

    /** Checks every active MAO account; the missing items are worked out once. */
    public function syncAll(): void
    {
        if (! SchemaCache::hasColumn('notifications', 'category')) {
            return;
        }
        $missing = $this->missing();
        User::query()->where('role', User::ROLE_MAO)->get()->each(fn (User $mao) => $this->sync($mao, $missing));
    }

    /**
     * Creates the notifications that are missing, rewrites one whose language changed, removes the ones no longer needed.
     *
     * @param  array<int, string>|null  $missing  the items still missing (worked out here when null)
     */
    public function sync(User $user, ?array $missing = null): void
    {
        if ($user->role !== User::ROLE_MAO || ! SchemaCache::hasColumn('notifications', 'category')) {
            return;
        }

        $missing ??= $this->missing();
        $locale = self::locale($user);
        $existing = Notification::query()
            ->where('user_id', $user->id)
            ->where('category', 'like', self::CATEGORY_PREFIX.'%')
            ->orderBy('id')->get();

        $kept = [];
        $wrote = false;
        // Saved without model events (as the farmer reminders): the bell reads these rows live.
        Notification::withoutEvents(function () use ($existing, $missing, $locale, $user, &$kept, &$wrote): void {
            foreach ($existing as $note) {
                $item = substr((string) $note->category, strlen(self::CATEGORY_PREFIX));
                if (! in_array($item, $missing, true) || isset($kept[$item])) {
                    $note->delete();                                        // added since, or a duplicate
                    $wrote = true;

                    continue;
                }
                $kept[$item] = true;
                $title = trans('mao.missing.'.$item.'.title', [], $locale);
                if ($note->title !== $title) {
                    $note->update(['title' => $title, 'message' => trans('mao.missing.'.$item.'.message', [], $locale)]);
                    $wrote = true;
                }
            }

            foreach (array_diff($missing, array_keys($kept)) as $item) {
                Notification::query()->create([
                    'user_id' => $user->id,
                    'title' => trans('mao.missing.'.$item.'.title', [], $locale),
                    'message' => trans('mao.missing.'.$item.'.message', [], $locale),
                    'type' => 'Announcement',
                    'category' => self::CATEGORY_PREFIX.$item,
                    'is_read' => false,
                ]);
                $wrote = true;
            }
        });

        if ($wrote) {
            \App\Support\CacheScope::changed(Notification::class);
        }

        Cache::forget('sidebar:unread-notifications:'.$user->id);
    }

    /** @return array<int, string> the items of ITEMS that have no data yet (an unreadable table counts as not missing) */
    public function missing(): array
    {
        $today = BusinessClock::today();
        $season = RiceSeason::window($today);
        $checks = [
            'climate_month' => fn (): bool => SchemaCache::hasTable('climate_records') && ! SampleData::without(ClimateRecord::query(), 'climate_records')
                ->whereBetween('record_date', [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()])
                ->exists(),
            // A record of this season: its month falls inside the season, or it is filed under this season's name and years.
            'production_season' => fn (): bool => SchemaCache::hasTable('rice_productions') && ! SampleData::without(RiceProduction::query(), 'rice_productions')
                ->where(fn ($query) => $query
                    ->whereBetween('production_month', [$season['start']->toDateString(), $season['end']->toDateString()])
                    ->orWhere(fn ($named) => $named->where('season', $season['season'])->whereIn('year', array_unique([$season['start']->year, $season['end']->year]))))
                ->exists(),
            'advisories' => fn (): bool => SchemaCache::hasTable('planting_advisories') && ! PlantingAdvisory::query()->active()->exists(),
            'planner' => fn (): bool => SchemaCache::hasTable('mao_activities') && ! MaoActivity::query()
                ->where(fn ($query) => $query->whereDate('starts_on', '>=', $today->toDateString())->orWhereDate('ends_on', '>=', $today->toDateString()))
                ->exists(),
        ];

        return array_values(array_filter(self::ITEMS, fn (string $item): bool => (bool) rescue($checks[$item], false, false)));
    }

    /**
     * Where a MAO missing-data notification leads; null for any other notification.
     *
     * @return ?array{url: string, label: string}
     */
    public static function linkFor(?string $category, ?User $user = null): ?array
    {
        if (! is_string($category) || ! str_starts_with($category, self::CATEGORY_PREFIX)) {
            return null;
        }
        $url = match (substr($category, strlen(self::CATEGORY_PREFIX))) {
            'climate_month' => route('climate-records.create'),
            'production_season' => route('rice-productions.create'),
            'advisories' => route('planting-advisories.create'),
            'planner' => route('mao.planner.index'),
            default => null,
        };

        return $url ? ['url' => $url, 'label' => trans('mao.missing.open', [], self::locale($user))] : null;
    }

    /** Filipino unless the MAO saved English (the wording the office asked for: "Walang climate records ngayong buwan"). */
    public static function locale(?User $user): string
    {
        return $user?->getAttribute('preferred_language') === 'en' ? 'en' : 'fil';
    }
}
