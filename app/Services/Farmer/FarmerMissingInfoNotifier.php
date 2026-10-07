<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\Notification;
use App\Models\User;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\FarmerLanguage;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;

/**
 * Missing farm information goes to the notification bell, not to warning cards on Home (2026-10, Farmer – Naicel).
 *
 * One notification per missing item (no barangay, no farm, no farm size, no planting date, a harvest without a record), in the
 * farmer's language, each linking to the page where it is filled in. It is never duplicated — the item is the
 * notification's category ("setup:planting_date") — and it is removed once the information is added.
 *
 * It uses the existing notifications table and bell as they are; NotificationController::feed() only adds the link.
 */
class FarmerMissingInfoNotifier
{
    public const CATEGORY_PREFIX = 'setup:';

    public const ITEMS = ['barangay', 'farm', 'farm_area', 'planting_date', 'harvest'];

    /** Page loads look again at most this often; a save looks at once. */
    private const PAGE_CHECK_SECONDS = 60;

    /** Called when a farmer page opens: cheap, and at most once a minute per farmer. */
    public function syncOnPageLoad(User $user): void
    {
        if (Cache::add(self::pageCheckKey($user->id), true, now()->addSeconds(self::PAGE_CHECK_SECONDS))) {
            rescue(fn () => $this->sync($user), null, true);
        }
    }

    /** Forgotten when the farmer's farm or profile is saved, so their next page checks again at once (AppServiceProvider). */
    public static function pageCheckKey(int $userId): string
    {
        return 'farmer:missing-info:checked:'.$userId;
    }

    /** Creates the notifications that are missing, rewrites one whose language changed, removes the ones no longer needed. */
    public function sync(User $user): void
    {
        if ($user->role !== User::ROLE_FARMER || ! SchemaCache::hasColumn('notifications', 'category')) {
            return;
        }

        $missing = $this->missing($user);
        $locale = $this->locale($user);
        // A saved barangay that is not one official name (e.g. a bare "Poblacion": Lian has five) is asked to be confirmed.
        $stored = trim((string) ($user->farmerProfile?->barangay ?: $user->barangay));
        $words = fn (string $item): string => $item === 'barangay' && $stored !== '' ? 'barangay_confirm' : $item;
        $existing = Notification::query()
            ->where('user_id', $user->id)
            ->where('category', 'like', self::CATEGORY_PREFIX.'%')
            ->orderBy('id')->get();

        $kept = [];
        $wrote = false;
        // Saved without model events, then only the shared notification counts are marked changed (CacheScope): the
        // bell and the Notifications page read these rows live, and the farmer's stored pages (Home) do not show them,
        // so a reminder must not make Home rebuild.
        Notification::withoutEvents(function () use ($existing, $missing, $words, $locale, $stored, $user, &$kept, &$wrote): void {
            foreach ($existing as $note) {
                $item = substr((string) $note->category, strlen(self::CATEGORY_PREFIX));
                if (! in_array($item, $missing, true) || isset($kept[$item])) {
                    $note->delete();                                        // filled in, or a duplicate
                    $wrote = true;

                    continue;
                }
                $kept[$item] = true;
                $title = trans('farmer.missing_info.'.$words($item).'.title', [], $locale);
                if ($note->title !== $title) {
                    $note->update(['title' => $title, 'message' => trans('farmer.missing_info.'.$words($item).'.message', ['barangay' => $stored], $locale)]);
                    $wrote = true;
                }
            }

            foreach (array_diff($missing, array_keys($kept)) as $item) {
                Notification::query()->create([
                    'user_id' => $user->id,
                    'title' => trans('farmer.missing_info.'.$words($item).'.title', [], $locale),
                    'message' => trans('farmer.missing_info.'.$words($item).'.message', ['barangay' => $stored], $locale),
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

    /** @return array<int, string> the items of ITEMS this farmer still has to add */
    public function missing(User $user): array
    {
        $missing = [];
        if ($user->registeredBarangay() === null) {
            $missing[] = 'barangay';
        }
        // The farmer's farms (John Luke's App\Models\Farm, read through ActiveFarm: the primary farm first).
        if (ActiveFarm::available()) {
            $farms = ActiveFarm::farms($user);
            if ($farms->isEmpty()) {
                $missing[] = 'farm';
            } elseif (! $farms->first()->hasSize()) {
                $missing[] = 'farm_area';                               // the yield estimate needs the farm's size
            }
        }

        if (SchemaCache::hasTable('farmer_planting_schedules')) {
            $schedules = FarmerPlantingSchedule::query()->where('user_id', $user->id)->get();
            if ($schedules->isEmpty()) {
                $missing[] = 'planting_date';
            } elseif (SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
                // Only asked once there is a harvest to record: a crop marked harvested, or past its harvest date.
                $today = BusinessClock::today()->toDateString();
                $unrecorded = $schedules->first(function (FarmerPlantingSchedule $s) use ($today): bool {
                    $due = $s->harvested_on !== null || $s->progress_status === 'Harvested' || ($s->harvestDateForPlanning()?->toDateString() ?? '9999') < $today;

                    return $due && ! (is_numeric($s->actual_yield) && (float) $s->actual_yield > 0);
                });
                if ($unrecorded) {
                    $missing[] = 'harvest';
                }
            }
        }

        return $missing;
    }

    /**
     * Where a missing-info notification leads; null for any other notification. A missing farm opens the Add Farm form,
     * a missing size the primary farm's own form (John Luke's farm pages).
     *
     * @return ?array{url: string, label: string}
     */
    public static function linkFor(?string $category, string $locale, ?User $user = null): ?array
    {
        if (! is_string($category) || ! str_starts_with($category, self::CATEGORY_PREFIX)) {
            return null;
        }
        $primary = fn (): ?Farm => $user !== null && ActiveFarm::available() ? ActiveFarm::farms($user)->first() : null;
        $url = match (substr($category, strlen(self::CATEGORY_PREFIX))) {
            'barangay' => route('profile.edit').'#barangay',
            'farm' => route('farmer.farms.create'),
            'farm_area' => ($farm = $primary()) ? route('farmer.farms.edit', $farm->getKey()) : route('farmer.my-farm'),
            // The harvest question sits at the top of the Activity Calendar, where a harvest is also marked.
            'planting_date', 'harvest' => route('farmer.my-farm').'#activity',
            default => null,
        };

        return $url ? ['url' => $url, 'label' => trans('farmer.missing_info.open', [], $locale)] : null;
    }

    private function locale(User $user): string
    {
        $saved = $user->getAttribute('preferred_language');

        return FarmerLanguage::isSupported($saved) ? $saved : FarmerLanguage::resolve(request());
    }
}
