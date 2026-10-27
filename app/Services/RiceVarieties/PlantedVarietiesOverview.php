<?php

namespace App\Services\RiceVarieties;

use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\MaoScope;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * MAO's "Planted in my barangays": the active (not yet harvested) planting plans in the barangays the MAO user covers
 * (App\Support\MaoScope: the Municipal Agriculturist, accounts without an assignment and the municipal view see all of
 * Lian), with their variety and expected harvest, and the summary tiles over them.
 */
final class PlantedVarietiesOverview
{
    /** Rows shown on the page; the tiles count every plan. */
    public const ROW_LIMIT = 300;

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, total: int, with_variety: int, without_variety: int,
     *     top: array<int, array{id: int, name: string, count: int}>, soon: array<int, int>, scope: array<int, string>|null}
     */
    public function for(User $user, string $direction = 'asc'): array
    {
        $empty = ['rows' => collect(), 'total' => 0, 'with_variety' => 0, 'without_variety' => 0, 'top' => [], 'soon' => [], 'scope' => MaoScope::barangays($user)];
        if (! SchemaCache::hasTable('farmer_planting_schedules') || ! SchemaCache::hasColumn('farmer_planting_schedules', 'rice_variety_id')) {
            return $empty;
        }

        $plans = $this->query($user)->with(['farmer:id,name,barangay', 'riceVariety:id,variety_name,nsic_reg_no'])->get()
            ->reject(fn (FarmerPlantingSchedule $plan): bool => $plan->isHarvested());
        $today = BusinessClock::today()->startOfDay();

        $rows = $plans->map(function (FarmerPlantingSchedule $plan) use ($today): array {
            $harvest = $plan->harvestDateForPlanning();

            return [
                'id' => $plan->id,
                'farmer' => $plan->farmer?->name,
                'barangay' => $plan->barangay ?: $plan->farmer?->barangay,
                'variety_id' => $plan->riceVariety?->id,
                'variety' => $plan->riceVariety?->displayName(),
                'establishment' => $plan->crop_establishment,
                'planted' => $plan->planting_date,
                'harvest' => $harvest,
                'days_left' => $harvest ? (int) $today->diffInDays($harvest->copy()->startOfDay(), false) : null,
            ];
        });

        $sorted = $rows->sortBy(fn (array $row): string => $row['harvest']?->toDateString() ?? '9999-12-31', SORT_STRING, $direction === 'desc')->values();

        $farmers = $plans->groupBy('user_id');
        $withVariety = $farmers->filter(fn (Collection $own): bool => $own->contains(fn ($plan) => $plan->rice_variety_id !== null))->count();

        $top = $plans->whereNotNull('rice_variety_id')->groupBy('rice_variety_id')
            ->map(fn (Collection $group): array => ['id' => (int) $group->first()->rice_variety_id, 'name' => $group->first()->riceVariety?->displayName() ?? '—', 'count' => $group->count()])
            ->sortByDesc('count')->take(5)->values()->all();

        $soon = [];
        foreach ((array) config('rice_varieties.soon_days', [14, 30]) as $days) {
            $soon[(int) $days] = $rows->filter(fn (array $row): bool => $row['days_left'] !== null && $row['days_left'] >= 0 && $row['days_left'] <= $days)->count();
        }

        return [
            'rows' => $sorted->take(self::ROW_LIMIT),
            'total' => $rows->count(),
            'with_variety' => $withVariety,
            'without_variety' => $farmers->count() - $withVariety,
            'top' => $top,
            'soon' => $soon,
            'scope' => MaoScope::barangays($user),
        ];
    }

    /** Planted, not harvested, in the user's barangays (a plan without a barangay counts by its farmer's barangay). */
    private function query(User $user): Builder
    {
        $barangays = MaoScope::barangays($user);

        return FarmerPlantingSchedule::query()
            ->whereNotNull('planting_date')
            ->when(SchemaCache::hasColumn('farmer_planting_schedules', 'harvested_on'), fn (Builder $q) => $q->whereNull('harvested_on'))
            ->where(fn (Builder $q) => $q->whereNull('progress_status')->orWhere('progress_status', '!=', 'Harvested'))
            ->whereHas('farmer', fn (Builder $q) => $q->where('role', User::ROLE_FARMER))
            ->when($barangays !== null, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereIn('barangay', $barangays)
                ->orWhere(fn (Builder $none) => $none->whereNull('barangay')->whereHas('farmer', fn (Builder $f) => $f->whereIn('barangay', $barangays)))));
    }
}
