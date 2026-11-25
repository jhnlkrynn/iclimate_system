<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Models\YieldPrediction;
use App\Services\Prediction\BarangayYieldForecastService;
use App\Services\Prediction\YieldFarmTypeAdjustment;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregated, privacy-preserving farm harvest & stored-estimate exploration
 * using approved farms only (never runs ML models on page load).
 */
class FarmResultsExplorationService
{
    public const MIN_FARMS = 1;

    /**
     * @param  array{farm_type?: ?string, barangay?: ?string, season?: ?string, year?: ?int, variety_id?: ?int}  $filters
     * @return array<string, mixed>
     */
    public function explore(array $filters = [], string $locale = 'en'): array
    {
        $fil = $locale === 'fil';
        $farmType = in_array($filters['farm_type'] ?? null, FarmerPlantingSchedule::FARM_TYPES, true)
            ? (string) $filters['farm_type']
            : FarmerPlantingSchedule::FARM_TYPES[0];
        $barangay = LianBarangays::canonicalName($filters['barangay'] ?? null);
        $season = in_array($filters['season'] ?? null, FarmerPlantingSchedule::SEASONS, true)
            ? (string) $filters['season']
            : null;
        $year = ! empty($filters['year']) ? (int) $filters['year'] : null;
        $varietyId = ! empty($filters['variety_id']) ? (int) $filters['variety_id'] : null;

        if (! SchemaCache::hasTable('farmer_planting_schedules') || ! SchemaCache::hasTable('farms')) {
            return $this->emptyResult($farmType, $barangay, $season, $year, $varietyId, $fil);
        }

        $harvestRows = $this->filteredScheduleRows($farmType, $barangay, $season, $year, $varietyId, onlyHarvested: true);
        $hasHarvests = $harvestRows->isNotEmpty();

        $activeRows = $hasHarvests
            ? $harvestRows
            : $this->filteredScheduleRows($farmType, $barangay, $season, $year, $varietyId, onlyHarvested: false);

        $allApprovedFarms = $this->approvedFarmsQuery($farmType, $barangay)
            ->get(['id', 'user_id', 'barangay', 'farm_type', 'farm_size_hectares', 'farm_size_method', 'farm_size_estimate_range']);

        $hasScheduleFilters = $season !== null || $year !== null || $varietyId !== null;
        if ($hasScheduleFilters) {
            $scheduleFarmIds = $activeRows->pluck('farm_id')->filter()->unique()->all();
            $matchingFarms = $allApprovedFarms->whereIn('id', $scheduleFarmIds)->values();
            $farmCount = $activeRows->isNotEmpty() ? $this->distinctFarmCount($activeRows) : 0;
        } else {
            $matchingFarms = $allApprovedFarms;
            $farmCount = $matchingFarms->count();
        }

        if ($farmCount < self::MIN_FARMS) {
            return $this->emptyResult($farmType, $barangay, $season, $year, $varietyId, $fil);
        }

        $totalArea = $this->computeTotalArea($matchingFarms, $activeRows);
        $basedOn = $this->basedOnLabel($farmCount, $fil);
        $fewFarmsNote = $farmCount <= 2
            ? ($fil ? 'Kaunti pa ang bukid, kaya maaaring mag-iba ang resulta.' : 'Few farms, so results may vary.')
            : null;

        $estimate = $this->resolveEstimate($matchingFarms, $farmType, $barangay, $season, $totalArea, $fil);

        if ($hasHarvests) {
            $yields = $harvestRows->map(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield);
            $avgYield = round((float) $yields->avg(), 2);
            $minYield = round((float) $yields->min(), 2);
            $maxYield = round((float) $yields->max(), 2);
            $bySeason = $this->seasonBreakdown($harvestRows);
            $topVarieties = $this->varietyBreakdown($harvestRows, withYield: true);
            $commonMonths = $this->plantingMonthBreakdown($harvestRows, $locale);
            $insight = $this->buildInsight($farmType, $barangay, $season, $avgYield, $bySeason, $fil);
        } else {
            $avgYield = null;
            $minYield = null;
            $maxYield = null;
            $bySeason = [];
            $topVarieties = $this->varietyBreakdown($activeRows, withYield: false);
            $commonMonths = $this->plantingMonthBreakdown($activeRows, $locale);
            $insight = $this->buildNoHarvestInsight($farmType, $barangay, $season, $farmCount, $estimate, $fil);
        }

        $diffTHa = ($hasHarvests && $avgYield !== null && $estimate['t_ha'] !== null)
            ? round($avgYield - $estimate['t_ha'], 2)
            : null;
        $diffPct = ($diffTHa !== null && $estimate['t_ha'] > 0)
            ? (int) round(($diffTHa / $estimate['t_ha']) * 100)
            : null;

        return [
            'sufficient' => true,
            'has_harvests' => $hasHarvests,
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'season' => $season,
            'year' => $year,
            'variety_id' => $varietyId,
            'farm_count' => $farmCount,
            'total_area' => $totalArea,
            'based_on' => $basedOn,
            'few_farms_note' => $fewFarmsNote,
            'avg_yield' => $avgYield,
            'min_yield' => $minYield,
            'max_yield' => $maxYield,
            'estimate' => $estimate,
            'diff_t_ha' => $diffTHa,
            'diff_pct' => $diffPct,
            'by_season' => $bySeason,
            'top_varieties' => $topVarieties,
            'common_months' => $commonMonths,
            'insight' => $insight,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $varieties = SchemaCache::hasTable('rice_varieties')
            ? RiceVariety::query()->orderBy('variety_name')->get(['id', 'nsic_reg_no', 'variety_name'])
            : collect();

        $years = SchemaCache::hasTable('farmer_planting_schedules')
            ? $this->baseScheduleQuery(onlyHarvested: false)
                ->get(['harvested_on', 'planting_date'])
                ->map(fn ($r) => ($r->harvested_on ?? $r->planting_date)?->year)
                ->filter()
                ->unique()
                ->sortDesc()
                ->values()
            : collect();

        return [
            'barangays' => LianBarangays::all(),
            'varieties' => $varieties,
            'years' => $years,
        ];
    }

    /**
     * Read stored farm-level yield_predictions (with YieldFarmTypeAdjustment),
     * falling back to stored BarangayYieldForecastService estimates. Never runs ML on page load.
     *
     * @param  Collection<int, Farm>  $matchingFarms
     * @return array{state: string, source: string, badge: ?string, disclaimer: string, t_ha: ?float, total_tons: ?float, updated_at: ?CarbonImmutable, none_note: ?string}
     */
    private function resolveEstimate(
        Collection $matchingFarms,
        string $farmType,
        ?string $barangay,
        ?string $season,
        ?float $totalArea,
        bool $fil,
    ): array {
        $tz = BusinessClock::timezone();
        $disclaimer = $fil ? 'Tantiya — hindi aktuwal na ani' : 'Estimate — not an actual harvest';
        $adjuster = app(YieldFarmTypeAdjustment::class);
        $farmIds = $matchingFarms->pluck('id')->filter()->values()->all();

        // 1) Stored farm-level yield_predictions (warm-farmer-yields)
        if ($farmIds !== [] && SchemaCache::hasTable('yield_predictions')) {
            $farmPreds = YieldPrediction::query()
                ->where('scope', YieldPrediction::SCOPE_FARM)
                ->where('period', 'season')
                ->whereIn('farm_id', $farmIds)
                ->when($season !== null, fn (Builder $q) => $q->where('season', $season))
                ->orderByDesc('generated_at')
                ->get()
                ->unique('farm_id')
                ->filter(fn (YieldPrediction $p): bool => is_numeric($p->predicted_yield) && (float) $p->predicted_yield > 0);

            if ($farmPreds->isNotEmpty()) {
                $adjusted = $farmPreds->map(function (YieldPrediction $p) use ($adjuster, $farmType, $season): float {
                    $res = $adjuster->forRequest($farmType, $p->season ?? $season, (float) $p->predicted_yield);

                    return (float) ($res['adjusted_yield'] ?? $p->predicted_yield);
                });
                $avgTHa = round((float) $adjusted->avg(), 2);
                $updated = $farmPreds->map(fn (YieldPrediction $p) => $p->generated_at?->timezone($tz))->filter()->max();

                return [
                    'state' => 'ok',
                    'source' => 'farms',
                    'badge' => null,
                    'disclaimer' => $disclaimer,
                    't_ha' => $avgTHa,
                    'total_tons' => $totalArea !== null ? round($avgTHa * $totalArea, 2) : null,
                    'updated_at' => $updated,
                    'none_note' => null,
                ];
            }
        }

        // 2) Fallback: stored barangay season estimate (BarangayYieldForecastService)
        $barangayRows = rescue(fn (): array => app(BarangayYieldForecastService::class)->all('season'), [], false);
        $targetBarangays = $barangay !== null
            ? [$barangay]
            : $matchingFarms->pluck('barangay')->map(fn ($b) => LianBarangays::canonicalName((string) $b))->filter()->unique()->values()->all();

        $matchedBarangayEstimates = collect($targetBarangays)
            ->map(fn (string $b) => $barangayRows[$b] ?? null)
            ->filter(fn ($row): bool => is_array($row) && is_numeric($row['predicted_yield'] ?? null) && (float) $row['predicted_yield'] > 0)
            ->values();

        if ($matchedBarangayEstimates->isNotEmpty()) {
            $adjusted = $matchedBarangayEstimates->map(function (array $row) use ($adjuster, $farmType, $season): float {
                $res = $adjuster->forRequest($farmType, $row['season'] ?? $season, (float) $row['predicted_yield']);

                return (float) ($res['adjusted_yield'] ?? $row['predicted_yield']);
            });
            $avgTHa = round((float) $adjusted->avg(), 2);
            $updated = $matchedBarangayEstimates
                ->map(fn (array $row): ?CarbonImmutable => ! empty($row['prediction_timestamp'])
                    ? rescue(fn () => CarbonImmutable::parse((string) $row['prediction_timestamp'])->timezone($tz), null, false)
                    : null)
                ->filter()
                ->max();

            return [
                'state' => 'ok',
                'source' => 'barangay',
                'badge' => $fil ? 'Tantiya ng barangay' : 'Barangay estimate',
                'disclaimer' => $disclaimer,
                't_ha' => $avgTHa,
                'total_tons' => $totalArea !== null ? round($avgTHa * $totalArea, 2) : null,
                'updated_at' => $updated,
                'none_note' => null,
            ];
        }

        return [
            'state' => 'none',
            'source' => 'none',
            'badge' => null,
            'disclaimer' => $disclaimer,
            't_ha' => null,
            'total_tons' => null,
            'updated_at' => null,
            'none_note' => $fil
                ? 'Hindi pa handa ang tantiya — nag-a-update tuwing 3 oras.'
                : 'Estimate not ready yet — updates every 3 hours.',
        ];
    }

    /** @return Collection<int, FarmerPlantingSchedule> */
    private function filteredScheduleRows(string $farmType, ?string $barangay, ?string $season, ?int $year, ?int $varietyId, bool $onlyHarvested): Collection
    {
        return $this->baseScheduleQuery($onlyHarvested)
            ->where('farmer_planting_schedules.farm_type', $farmType)
            ->when($barangay !== null, function (Builder $q) use ($barangay): void {
                $q->where(fn (Builder $w) => $w->where('farmer_planting_schedules.barangay', $barangay)
                    ->orWhereHas('farm', fn (Builder $fq) => $fq->where('barangay', $barangay)));
            })
            ->when($season !== null, fn (Builder $q) => $q->where('farmer_planting_schedules.season', $season))
            ->when($year !== null, fn (Builder $q) => $q->whereYear(\Illuminate\Support\Facades\DB::raw('COALESCE(harvested_on, planting_date)'), $year))
            ->when($varietyId !== null, fn (Builder $q) => $q->where('farmer_planting_schedules.rice_variety_id', $varietyId))
            ->with([
                'riceVariety:id,nsic_reg_no,variety_name',
                'farm:id,farm_size_hectares,farm_size_method,farm_size_estimate_range',
            ])
            ->get([
                'farmer_planting_schedules.id',
                'farmer_planting_schedules.user_id',
                'farmer_planting_schedules.farm_id',
                'farmer_planting_schedules.season',
                'farmer_planting_schedules.farm_area_hectares',
                'farmer_planting_schedules.planting_date',
                'farmer_planting_schedules.harvested_on',
                'farmer_planting_schedules.actual_yield',
                'farmer_planting_schedules.rice_variety_id',
            ]);
    }

    private function baseScheduleQuery(bool $onlyHarvested = true): Builder
    {
        $query = FarmerPlantingSchedule::query()
            ->when($onlyHarvested, fn (Builder $q) => $q->whereNotNull('farmer_planting_schedules.actual_yield')
                ->where('farmer_planting_schedules.actual_yield', '>', 0))
            ->whereHas('farm', function (Builder $fq): void {
                $fq->where('verification_status', Farm::VERIFICATION_APPROVED)
                    ->whereNull('archived_at');
            })
            ->whereHas('farmer', function (Builder $uq): void {
                $uq->where('email', 'not like', '%@iclimate.local')
                    ->where('email', 'not like', '%@example.%');
            });

        return SampleData::without($query, 'farmer_planting_schedules');
    }

    private function approvedFarmsQuery(string $farmType, ?string $barangay): Builder
    {
        return Farm::query()
            ->where('verification_status', Farm::VERIFICATION_APPROVED)
            ->whereNull('archived_at')
            ->where('farm_type', $farmType)
            ->when($barangay !== null, fn (Builder $q) => $q->where('barangay', $barangay))
            ->whereHas('user', function (Builder $uq): void {
                $uq->where('email', 'not like', '%@iclimate.local')
                    ->where('email', 'not like', '%@example.%');
            });
    }

    /** @param Collection<int, FarmerPlantingSchedule> $rows */
    private function distinctFarmCount(Collection $rows): int
    {
        return $rows->map(fn (FarmerPlantingSchedule $r): string => $r->farm_id ? 'f:'.$r->farm_id : 'u:'.$r->user_id)->unique()->count();
    }

    /**
     * Use each approved farm's real area (knownHectares()), falling back to schedule area only if farm area is missing.
     *
     * @param  Collection<int, Farm>  $farms
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     */
    private function computeTotalArea(Collection $farms, Collection $rows): ?float
    {
        $perFarm = [];
        foreach ($farms as $farm) {
            $area = $farm->knownHectares();
            if ($area !== null && $area > 0) {
                $perFarm['f:'.$farm->getKey()] = $area;
            }
        }

        foreach ($rows as $r) {
            $key = $r->farm_id ? 'f:'.$r->farm_id : 'u:'.$r->user_id;
            if (isset($perFarm[$key])) {
                continue;
            }
            $area = $r->farm?->knownHectares()
                ?? (is_numeric($r->farm_area_hectares) && (float) $r->farm_area_hectares > 0 ? (float) $r->farm_area_hectares : null);
            if ($area !== null && $area > 0) {
                $perFarm[$key] = $area;
            }
        }

        return $perFarm !== [] ? round(array_sum($perFarm), 2) : null;
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return list<array{label: string, season: string, year: int, avg: float, farms: int}>
     */
    private function seasonBreakdown(Collection $rows): array
    {
        return $rows
            ->filter(fn (FarmerPlantingSchedule $r): bool => ($r->harvested_on ?? $r->planting_date) !== null && filled($r->season))
            ->groupBy(function (FarmerPlantingSchedule $r): string {
                $year = ($r->harvested_on ?? $r->planting_date)->year;

                return $year.'-'.($r->season === 'Dry' ? '1' : '2').'|'.$r->season.' '.$year;
            })
            ->sortKeys()
            ->map(function (Collection $group, string $key): array {
                [, $label] = explode('|', $key, 2);
                [$seasonName, $yearStr] = explode(' ', $label, 2);

                return [
                    'label' => $label,
                    'season' => $seasonName,
                    'year' => (int) $yearStr,
                    'avg' => round((float) $group->avg(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield), 2),
                    'farms' => $this->distinctFarmCount($group),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return list<array{name: string, avg: ?float, farms: int}>
     */
    private function varietyBreakdown(Collection $rows, bool $withYield = true): array
    {
        return $rows
            ->filter(fn (FarmerPlantingSchedule $r): bool => $r->riceVariety !== null)
            ->groupBy('rice_variety_id')
            ->map(function (Collection $group) use ($withYield): array {
                return [
                    'name' => (string) $group->first()->riceVariety->displayName(),
                    'avg' => $withYield ? round((float) $group->avg(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield), 2) : null,
                    'farms' => $this->distinctFarmCount($group),
                ];
            })
            ->sortByDesc(fn (array $row): float => $row['avg'] ?? (float) $row['farms'])
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return list<array{month: string, farms: int}>
     */
    private function plantingMonthBreakdown(Collection $rows, string $locale): array
    {
        return $rows
            ->filter(fn (FarmerPlantingSchedule $r): bool => $r->planting_date !== null)
            ->groupBy(fn (FarmerPlantingSchedule $r): string => (string) $r->planting_date->month)
            ->map(fn (Collection $group): array => [
                'month' => $group->first()->planting_date->locale($locale)->translatedFormat('F'),
                'farms' => $this->distinctFarmCount($group),
            ])
            ->sortByDesc('farms')
            ->take(4)
            ->values()
            ->all();
    }

    private function basedOnLabel(int $farmCount, bool $fil): string
    {
        if ($fil) {
            return $farmCount === 1 ? 'Batay sa 1 bukid' : "Batay sa {$farmCount} na bukid";
        }

        return $farmCount === 1 ? 'Based on 1 farm' : "Based on {$farmCount} farms";
    }

    private function buildInsight(string $farmType, ?string $barangay, ?string $season, float $avgYield, array $bySeason, bool $fil): string
    {
        $place = $barangay ?: ($fil ? 'buong Lian' : 'all of Lian');
        $lastSeason = $bySeason !== [] ? end($bySeason) : null;
        $seasonPhrase = $season
            ? ($fil ? "noong {$season} season" : "in the {$season} season")
            : ($lastSeason ? ($fil ? "noong huling {$lastSeason['label']}" : "in {$lastSeason['label']}") : '');
        $displayAvg = $lastSeason && ! $season ? $lastSeason['avg'] : $avgYield;

        if ($fil) {
            return trim("Ang mga bukid na {$farmType} sa {$place} ay nag-ani ng average na {$displayAvg} t/ha {$seasonPhrase}").'.';
        }

        return trim("{$farmType} farms in {$place} averaged {$displayAvg} t/ha".($seasonPhrase ? " {$seasonPhrase}" : '')).'.';
    }

    /**
     * @param  array<string, mixed>  $estimate
     */
    private function buildNoHarvestInsight(string $farmType, ?string $barangay, ?string $season, int $farmCount, array $estimate, bool $fil): string
    {
        $place = $barangay ?: ($fil ? 'buong Lian' : 'all of Lian');
        $currentSeason = strtolower($season ?? RiceSeason::forMonth((int) CarbonImmutable::now(BusinessClock::timezone())->month));

        if (($estimate['state'] ?? 'none') === 'ok' && $estimate['t_ha'] !== null) {
            $tHa = $estimate['t_ha'];
            if ($fil) {
                $seasonLabel = $currentSeason === 'wet' ? 'tag-ulan' : 'tag-araw';

                return "Ang mga bukid na {$farmType} sa {$place} ay tinatantiyang aani ng {$tHa} t/ha ngayong {$seasonLabel} (wala pang naitalang ani).";
            }

            return "{$farmType} farms in {$place} are estimated at {$tHa} t/ha this {$currentSeason} season (no harvest recorded yet).";
        }

        if ($fil) {
            return "May {$farmCount} na aprubadong {$farmType} na bukid sa {$place} — wala pang naitalang ani para sa panahong ito.";
        }

        return "{$farmCount} approved {$farmType} ".($farmCount === 1 ? 'farm' : 'farms')." in {$place} — no harvest recorded yet for this season.";
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $farmType, ?string $barangay, ?string $season, ?int $year, ?int $varietyId, bool $fil): array
    {
        return [
            'sufficient' => false,
            'has_harvests' => false,
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'season' => $season,
            'year' => $year,
            'variety_id' => $varietyId,
            'farm_count' => 0,
            'total_area' => null,
            'based_on' => $this->basedOnLabel(0, $fil),
            'few_farms_note' => null,
            'avg_yield' => null,
            'min_yield' => null,
            'max_yield' => null,
            'estimate' => ['state' => 'none', 'source' => 'none', 'badge' => null, 'disclaimer' => '', 't_ha' => null, 'total_tons' => null, 'updated_at' => null, 'none_note' => null],
            'diff_t_ha' => null,
            'diff_pct' => null,
            'by_season' => [],
            'top_varieties' => [],
            'common_months' => [],
            'insight' => $fil
                ? 'Wala pang aprubadong bukid na tugma sa mga filter. Subukan ang Buong Lian o ibang taon.'
                : 'No approved farms match these filters yet. Try All of Lian or another year.',
        ];
    }
}
