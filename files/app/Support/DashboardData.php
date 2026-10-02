<?php

namespace App\Support;

use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Request-scoped copies of the two tables every dashboard section reads.
 *
 * A dashboard used to load rice_productions six times and heatmap_areas five
 * times per render — each one a ~300 ms round trip to Supabase. Bound as a
 * singleton so the whole request shares one read of each; saved/deleted model
 * events (see AppServiceProvider) drop the copy so later reads in the same
 * process never see stale rows.
 */
class DashboardData
{
    /** @var Collection<int, RiceProduction>|null */
    private ?Collection $rice = null;

    /** @var Collection<int, HeatmapArea>|null */
    private ?Collection $areas = null;

    /** @return Collection<int, RiceProduction> newest harvest year first */
    public function riceRows(): Collection
    {
        return $this->rice ??= SampleData::without(RiceProduction::query(), 'rice_productions')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get(['id', 'barangay', 'year', 'season', 'irrigation_type', 'area_hectares', 'total_production', 'yield_per_hectare', 'production_month', 'created_at']);
    }

    /** @return Collection<int, HeatmapArea> every stored risk rating */
    public function heatmapAreas(): Collection
    {
        return $this->areas ??= HeatmapArea::query()->orderBy('barangay')->get();
    }

    /** One rating per barangay. */
    public function currentRiskRatings(): Collection
    {
        return $this->heatmapAreas()->unique('barangay')->values();
    }

    /**
     * Cache-key suffix that changes whenever dashboard source data is saved or
     * deleted, so long-lived dashboard caches never outlive the data they summarise.
     */
    public static function version(): int
    {
        return (int) Cache::get('dashboard:data-version', 1);
    }

    public static function bump(): void
    {
        Cache::add('dashboard:data-version', 1, now()->addDays(30));
        Cache::increment('dashboard:data-version');
    }

    public function forget(): void
    {
        $this->rice = null;
        $this->areas = null;
    }
}
