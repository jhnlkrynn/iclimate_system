<?php

namespace App\Services\Farms;

use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\ActiveFarm;
use App\Support\LianBarangays;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes, archives and picks the primary farm for a farmer, and keeps the farmer profile's old single-farm
 * fields (barangay, farm_area, farm_type) equal to the PRIMARY farm, so the MAO/IT pages and reports that still read
 * the profile keep working. Farm pages read the farms themselves.
 *
 * The other way round, a change made on the profile (MAO editing a farmer, the mobile app, registration) is written to
 * the primary farm; a farmer with no farm yet gets "Farm 1" from it. A guard stops the two from repeating each other.
 */
class FarmService
{
    private static bool $syncing = false;

    /**
     * @param  array<string, mixed>  $data  validated form values (see FarmRequestRules)
     */
    public function create(User $user, array $data): Farm
    {
        $count = Farm::query()->ownedBy($user)->active()->count();
        if ($count >= Farm::MAX_PER_FARMER) {
            throw ValidationException::withMessages(['farm_name' => [__('farms.errors.too_many', ['max' => Farm::MAX_PER_FARMER])]]);
        }

        $farm = DB::transaction(function () use ($user, $data): Farm {
            $farm = new Farm(['user_id' => $user->getKey()]);
            $farm->fill($this->attributes($data, null));
            $farm->farm_name = trim((string) ($data['farm_name'] ?? '')) ?: $this->defaultName($user);
            // The first farm is the primary farm; a farmer can change that later.
            $farm->is_primary = ! Farm::query()->ownedBy($user)->active()->where('is_primary', true)->exists();
            $farm->save();

            return $farm;
        });

        ActiveFarm::forget($user);
        if ($farm->is_primary) {
            $this->mirrorToProfile($user);
        }

        return $farm;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Farm $farm, array $data): Farm
    {
        $farm->fill($this->attributes($data, $farm));
        if (array_key_exists('farm_name', $data)) {
            $farm->farm_name = trim((string) $data['farm_name']) ?: $farm->farm_name;
        }
        $farm->save();

        ActiveFarm::forget($farm->user);
        if ($farm->is_primary) {
            $this->mirrorToProfile($farm->user);
        }

        return $farm;
    }

    /** Makes this the farmer's only primary farm (the one their pages open on). */
    public function makePrimary(Farm $farm): void
    {
        DB::transaction(function () use ($farm): void {
            Farm::query()->ownedBy($farm->user_id)->where('id', '!=', $farm->getKey())->where('is_primary', true)->update(['is_primary' => false]);
            $farm->forceFill(['is_primary' => true])->save();
        });

        ActiveFarm::forget($farm->user);
        $this->mirrorToProfile($farm->user);
    }

    /**
     * Removes the farm from the farmer's list. It is archived, not deleted: its planting plans, map and harvest records
     * stay in the database. When it was the primary farm, the next farm becomes primary.
     */
    public function archive(Farm $farm): void
    {
        DB::transaction(function () use ($farm): void {
            $farm->forceFill(['archived_at' => now(), 'is_primary' => false])->save();
            if (! Farm::query()->ownedBy($farm->user_id)->active()->where('is_primary', true)->exists()) {
                Farm::query()->ownedBy($farm->user_id)->active()->orderBy('id')->first()?->forceFill(['is_primary' => true])->save();
            }
        });

        ActiveFarm::forget($farm->user);
        $this->mirrorToProfile($farm->user);
    }

    /** The size from the farm's saved map: used when the farmer chose "Measure on map" or has not added a size yet. */
    public function sizeFromMap(Farm $farm, float $hectares): void
    {
        if (in_array($farm->farm_size_method, [Farm::SIZE_MAPPED, Farm::SIZE_UNKNOWN], true) || $farm->farm_size_method === null) {
            $farm->forceFill([
                'farm_size_hectares' => round($hectares, 4),
                'farm_size_method' => Farm::SIZE_MAPPED,
                'farm_size_estimate_range' => null,
                'farm_size_unit' => Farm::UNIT_HECTARES,
            ]);
        }
        $farm->location_method = Farm::LOCATION_BOUNDARY;
        $farm->save();

        if ($farm->is_primary) {
            $this->mirrorToProfile($farm->user);
        }
    }

    /**
     * The primary farm's values on the farmer profile (the profile keeps its own value where the farm has none).
     */
    public function mirrorToProfile(User $user): void
    {
        $primary = Farm::query()->ownedBy($user)->active()->where('is_primary', true)->first();
        $profile = $user->farmerProfile()->first();
        if ($primary === null || $profile === null) {
            return;
        }

        $changes = array_filter([
            'barangay' => LianBarangays::canonicalName($primary->barangay),
            'farm_type' => in_array($primary->farm_type, ['Irrigated', 'Rainfed'], true) ? $primary->farm_type : null,
        ], fn ($value) => $value !== null);
        // Only a known size is a farm area; an estimated range is not written as one.
        $changes['farm_area'] = $primary->knownHectares();

        $this->withoutEcho(fn () => $profile->fill($changes)->isDirty() ? $profile->save() : null);
    }

    /**
     * A change made on the farmer profile itself: written to the primary farm, or the farmer's first farm is made from
     * it when they have none. Nothing happens while a farm is being written to the profile.
     */
    public function fromProfile(FarmerProfile $profile): void
    {
        if (self::$syncing || ! ActiveFarm::available() || ! $profile->user_id) {
            return;
        }

        $farms = Farm::query()->ownedBy($profile->user_id)->active();
        $barangay = LianBarangays::canonicalName($profile->barangay);
        $area = is_numeric($profile->farm_area) && (float) $profile->farm_area > 0 ? round((float) $profile->farm_area, 4) : null;
        $type = in_array($profile->farm_type, ['Irrigated', 'Rainfed'], true) ? $profile->farm_type : null;

        $this->withoutEcho(function () use ($profile, $farms, $barangay, $area, $type): void {
            $primary = (clone $farms)->where('is_primary', true)->first() ?? (clone $farms)->orderBy('id')->first();
            if ($primary === null) {
                if ($barangay === null && $area === null && $type === null) {
                    return;
                }
                Farm::query()->create([
                    'user_id' => $profile->user_id, 'farm_name' => 'Farm 1', 'barangay' => $barangay,
                    'location_method' => Farm::LOCATION_BARANGAY, 'farm_type' => $type, 'is_primary' => true,
                    'farm_size_hectares' => $area, 'farm_size_method' => $area !== null ? Farm::SIZE_EXACT : Farm::SIZE_UNKNOWN,
                    'farm_size_unit' => $area !== null ? Farm::UNIT_HECTARES : null,
                ]);

                return;
            }

            $changes = [];
            if ($profile->wasChanged('barangay') && $barangay !== null) {
                $changes['barangay'] = $barangay;
            }
            if ($profile->wasChanged('farm_type') && $type !== null) {
                $changes['farm_type'] = $type;
            }
            if ($profile->wasChanged('farm_area')) {
                $changes += $area !== null
                    ? ['farm_size_hectares' => $area, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_estimate_range' => null, 'farm_size_unit' => Farm::UNIT_HECTARES]
                    : ['farm_size_hectares' => null, 'farm_size_method' => Farm::SIZE_UNKNOWN, 'farm_size_estimate_range' => null, 'farm_size_unit' => null];
            }
            if ($changes !== []) {
                $primary->forceFill($changes + ['is_primary' => true])->save();
            }
        });

        if ($profile->user) {
            ActiveFarm::forget($profile->user);
        }
    }

    /**
     * The farm's attributes from the form: location, type and the size in the way the farmer gave it.
     *   exact      value in hectares or square meters (square meters are turned into hectares here)
     *   estimated  one of Farm::ESTIMATE_RANGES; no number is stored
     *   mapped     the size measured from the farm's map (kept as it is; the map page measures it)
     *   unknown    no size yet
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attributes(array $data, ?Farm $farm): array
    {
        $attributes = [];
        if (array_key_exists('barangay', $data)) {
            $attributes['barangay'] = LianBarangays::canonicalName($data['barangay']);
        }
        if (array_key_exists('location_description', $data)) {
            $attributes['location_description'] = trim((string) $data['location_description']) ?: null;
        }
        if (array_key_exists('farm_type', $data)) {
            $attributes['farm_type'] = in_array($data['farm_type'], ['Irrigated', 'Rainfed'], true) ? $data['farm_type'] : null;
        }
        if (! $farm || $farm->location_method === null) {
            $attributes['location_method'] = Farm::LOCATION_BARANGAY;
        }

        $method = $data['farm_size_method'] ?? null;
        if (! in_array($method, Farm::SIZE_METHODS, true)) {
            return $attributes;
        }

        return $attributes + match ($method) {
            Farm::SIZE_EXACT => [
                'farm_size_method' => Farm::SIZE_EXACT,
                'farm_size_hectares' => ($data['farm_size_unit'] ?? Farm::UNIT_HECTARES) === Farm::UNIT_SQUARE_METERS
                    ? round((float) $data['farm_size_value'] / 10000, 4)
                    : round((float) $data['farm_size_value'], 4),
                'farm_size_unit' => ($data['farm_size_unit'] ?? null) === Farm::UNIT_SQUARE_METERS ? Farm::UNIT_SQUARE_METERS : Farm::UNIT_HECTARES,
                'farm_size_estimate_range' => null,
            ],
            Farm::SIZE_ESTIMATED => [
                'farm_size_method' => Farm::SIZE_ESTIMATED,
                'farm_size_estimate_range' => $data['farm_size_estimate_range'],
                'farm_size_hectares' => null,
                'farm_size_unit' => null,
            ],
            // Measured from the map: an existing measurement stays; the map page fills it in when the farm is mapped.
            Farm::SIZE_MAPPED => [
                'farm_size_method' => Farm::SIZE_MAPPED,
                'farm_size_hectares' => $farm?->farm_size_method === Farm::SIZE_MAPPED ? $farm->farm_size_hectares
                    : ($farm?->boundary?->calculated_area_hectares ? round((float) $farm->boundary->calculated_area_hectares, 4) : null),
                'farm_size_estimate_range' => null,
                'farm_size_unit' => Farm::UNIT_HECTARES,
            ],
            default => [
                'farm_size_method' => Farm::SIZE_UNKNOWN,
                'farm_size_hectares' => null,
                'farm_size_estimate_range' => null,
                'farm_size_unit' => null,
            ],
        };
    }

    /** "Farm 2" for a farmer whose farms are already named Farm 1, North Farm, … (never a name they already use). */
    public function defaultName(User $user): string
    {
        $names = Farm::query()->ownedBy($user)->active()->pluck('farm_name')->map(fn ($n) => mb_strtolower((string) $n))->all();
        for ($n = count($names) + 1; ; $n++) {
            $name = __('farms.default_name', ['number' => $n]);
            if (! in_array(mb_strtolower($name), $names, true)) {
                return $name;
            }
        }
    }

    private function withoutEcho(callable $write): void
    {
        $was = self::$syncing;
        self::$syncing = true;
        try {
            $write();
        } finally {
            self::$syncing = $was;
        }
    }
}
