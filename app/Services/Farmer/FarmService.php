<?php

namespace App\Services\Farmer;

use App\Jobs\PrepareFarmerYieldEstimate;
use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A farmer's farms (2026-10, Farmer – Naicel). A farmer can have several; the first active one is the "primary" farm
 * and is kept in step with farmer_profiles (barangay, farm_area, farm_type), which the MAO pages, the mobile API and
 * the Profile page still read and write:
 *   - a farm saved here is copied onto the profile (mirrorToProfile);
 *   - a profile changed elsewhere (Profile page, registration) is copied onto the primary farm the next time the
 *     farmer's farms are read (reconcile), and a farmer with a profile but no farm yet gets "Farm 1".
 */
class FarmService
{
    /** @var array<int, Collection<int, Farm>> */
    private array $loaded = [];

    public static function ready(): bool
    {
        return SchemaCache::hasTable('farms');
    }

    /**
     * The farmer's active farms, oldest first (the first one is the primary farm).
     *
     * @return Collection<int, Farm>
     */
    public function farms(User $user): Collection
    {
        if (! self::ready()) {
            return collect();
        }
        if (! isset($this->loaded[$user->id])) {
            $this->reconcile($user);
            $this->loaded[$user->id] = Farm::query()->where('user_id', $user->id)->active()->orderBy('id')->get();
        }

        return $this->loaded[$user->id];
    }

    public function primary(User $user): ?Farm
    {
        return $this->farms($user)->first();
    }

    /** The farm the farmer picked (?farm=ID), when it is one of their active farms; otherwise the primary farm. */
    public function selected(User $user, mixed $farmId): ?Farm
    {
        $farms = $this->farms($user);

        return (is_numeric($farmId) ? $farms->firstWhere('id', (int) $farmId) : null) ?? $farms->first();
    }

    public function isPrimary(User $user, ?Farm $farm): bool
    {
        return $farm !== null && $this->primary($user)?->id === $farm->id;
    }

    /** @param  array{name: string, barangay: ?string, area_hectares: mixed, farm_type: string}  $data */
    public function create(User $user, array $data): Farm
    {
        // First, so a farmer whose profile farm is not a farm yet gets "Farm 1" before this one is added.
        $this->farms($user);
        $farm = Farm::query()->create(['user_id' => $user->id] + $data);
        $this->changed($user, $farm);

        return $farm;
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, Farm $farm, array $data): Farm
    {
        $farm->update($data);
        $this->changed($user, $farm);

        return $farm;
    }

    /** Archived, never deleted: the farm's planting and harvest records stay. */
    public function archive(User $user, Farm $farm): void
    {
        $farm->update(['archived_at' => now()]);
        $this->changed($user, $farm);
    }

    /** Copies the profile onto the primary farm when the profile is the newer of the two; starts "Farm 1" when there is no farm. */
    private function reconcile(User $user): void
    {
        $profile = $user->farmerProfile;
        if (! $profile instanceof FarmerProfile) {
            return;
        }

        if (! Farm::query()->where('user_id', $user->id)->exists()) {
            $farm = Farm::query()->create([
                'user_id' => $user->id,
                'name' => 'Farm 1',
                'barangay' => $profile->barangay ?: null,
                'area_hectares' => $profile->farm_area,
                'farm_type' => $profile->farm_type ?: FarmerProfile::FARM_TYPE_IRRIGATED,
            ]);
            if (SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id')) {
                FarmerPlantingSchedule::query()->where('user_id', $user->id)->whereNull('farm_id')->update(['farm_id' => $farm->id]);
            }

            return;
        }

        $primary = Farm::query()->where('user_id', $user->id)->active()->orderBy('id')->first();
        if ($primary === null || $profile->updated_at === null || ($primary->updated_at !== null && $profile->updated_at->lte($primary->updated_at))) {
            return;
        }
        $primary->fill([
            'barangay' => $profile->barangay ?: $primary->barangay,
            'area_hectares' => $profile->farm_area,
            'farm_type' => $profile->farm_type ?: $primary->farm_type,
        ]);
        if ($primary->isDirty()) {
            $primary->save();
        }
    }

    /** The primary farm's barangay, size and type on the farmer's profile record (made when there is none and a barangay is known). */
    private function mirrorToProfile(User $user): void
    {
        $primary = Farm::query()->where('user_id', $user->id)->active()->orderBy('id')->first();
        if ($primary === null) {
            return;
        }

        $profile = $user->farmerProfile()->first();
        if ($profile === null) {
            if ($primary->barangay) {
                $user->farmerProfile()->create([
                    'full_name' => $user->name,
                    'contact_number' => $user->contact_number,
                    'address' => $user->address,
                    'barangay' => $primary->barangay,
                    'farm_area' => $primary->area_hectares,
                    'farm_type' => $primary->farm_type,
                ]);
            }

            return;
        }

        $profile->fill([
            'barangay' => $primary->barangay ?: $profile->barangay,
            'farm_area' => $primary->area_hectares,
            'farm_type' => $primary->farm_type,
        ]);
        if ($profile->isDirty()) {
            $profile->save();
        }
    }

    /** After any change to a farm: the profile follows, Home is rebuilt and the farm's yield estimate is made again. */
    private function changed(User $user, Farm $farm): void
    {
        unset($this->loaded[$user->id]);
        $this->mirrorToProfile($user);
        $user->unsetRelation('farmerProfile');

        Cache::forget(FarmerDashboardService::predictionCacheKey($user->id));
        Cache::forget(FarmerDashboardService::farmPredictionCacheKey($user->id, $farm->id));
        FarmerDashboardService::forgetCached($user);
        if ($farm->archived_at === null) {
            PrepareFarmerYieldEstimate::queueFor($user->id, $this->isPrimary($user, $farm) ? null : $farm->id);
        }
    }
}
