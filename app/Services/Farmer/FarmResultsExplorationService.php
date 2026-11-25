<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Support\LianBarangays;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregated, privacy-preserving farm harvest exploration using actual harvest records
 * of approved farms only (seeded/demo rows excluded; groups with < 5 farms are hidden).
 */
class FarmResultsExplorationService
{
    public const MIN_FARMS = 5;

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

        $rows = $this->baseQuery()
            ->where('farmer_planting_schedules.farm_type', $farmType)
            ->when($barangay !== null, function (Builder $q) use ($barangay): void {
                $q->where(fn (Builder $w) => $w->where('farmer_planting_schedules.barangay', $barangay)
                    ->orWhereHas('farm', fn (Builder $fq) => $fq->where('barangay', $barangay)));
            })
            ->when($season !== null, fn (Builder $q) => $q->where('farmer_planting_schedules.season', $season))
            ->when($year !== null, fn (Builder $q) => $q->whereYear(\Illuminate\Support\Facades\DB::raw('COALESCE(harvested_on, planting_date)'), $year))
            ->when($varietyId !== null, fn (Builder $q) => $q->where('farmer_planting_schedules.rice_variety_id', $varietyId))
            ->with('riceVariety:id,nsic_reg_no,variety_name')
            ->get([
                'farmer_planting_schedules.id',
                'farmer_planting_schedules.user_id',
                'farmer_planting_schedules.farm_id',
                'farmer_planting_schedules.season',
                'farmer_planting_schedules.planting_date',
                'farmer_planting_schedules.harvested_on',
                'farmer_planting_schedules.actual_yield',
                'farmer_planting_schedules.rice_variety_id',
            ]);

        $farmCount = $this->distinctFarmCount($rows);
        if ($farmCount < self::MIN_FARMS) {
            return $this->emptyResult($farmType, $barangay, $season, $year, $varietyId, $fil, $farmCount);
        }

        $yields = $rows->map(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield);
        $avgYield = round((float) $yields->avg(), 2);
        $minYield = round((float) $yields->min(), 2);
        $maxYield = round((float) $yields->max(), 2);

        $bySeason = $this->seasonBreakdown($rows);
        $topVarieties = $this->varietyBreakdown($rows);
        $commonMonths = $this->plantingMonthBreakdown($rows, $locale);

        $insight = $this->buildInsight($farmType, $barangay, $season, $avgYield, $bySeason, $fil);

        return [
            'sufficient' => true,
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'season' => $season,
            'year' => $year,
            'variety_id' => $varietyId,
            'farm_count' => $farmCount,
            'avg_yield' => $avgYield,
            'min_yield' => $minYield,
            'max_yield' => $maxYield,
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
            ? $this->baseQuery()
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

    private function baseQuery(): Builder
    {
        $query = FarmerPlantingSchedule::query()
            ->whereNotNull('farmer_planting_schedules.actual_yield')
            ->where('farmer_planting_schedules.actual_yield', '>', 0)
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

    /** @param Collection<int, FarmerPlantingSchedule> $rows */
    private function distinctFarmCount(Collection $rows): int
    {
        return $rows->map(fn (FarmerPlantingSchedule $r): string => $r->farm_id ? 'f:'.$r->farm_id : 'u:'.$r->user_id)->unique()->count();
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
            ->map(function (Collection $group, string $key): ?array {
                $farms = $this->distinctFarmCount($group);
                if ($farms < self::MIN_FARMS) {
                    return null;
                }
                [, $label] = explode('|', $key, 2);
                [$seasonName, $yearStr] = explode(' ', $label, 2);

                return [
                    'label' => $label,
                    'season' => $seasonName,
                    'year' => (int) $yearStr,
                    'avg' => round((float) $group->avg(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield), 2),
                    'farms' => $farms,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return list<array{name: string, avg: float, farms: int}>
     */
    private function varietyBreakdown(Collection $rows): array
    {
        return $rows
            ->filter(fn (FarmerPlantingSchedule $r): bool => $r->riceVariety !== null)
            ->groupBy('rice_variety_id')
            ->map(function (Collection $group): ?array {
                $farms = $this->distinctFarmCount($group);
                if ($farms < self::MIN_FARMS) {
                    return null;
                }

                return [
                    'name' => (string) $group->first()->riceVariety->displayName(),
                    'avg' => round((float) $group->avg(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield), 2),
                    'farms' => $farms,
                ];
            })
            ->filter()
            ->sortByDesc('avg')
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
            ->map(function (Collection $group) use ($locale): ?array {
                $farms = $this->distinctFarmCount($group);
                if ($farms < self::MIN_FARMS) {
                    return null;
                }

                return [
                    'month' => $group->first()->planting_date->locale($locale)->translatedFormat('F'),
                    'farms' => $farms,
                ];
            })
            ->filter()
            ->sortByDesc('farms')
            ->take(4)
            ->values()
            ->all();
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
            return trim("Ang mga bukid na {$farmType} sa {$place} ay nag-ani ng average na {$displayAvg} t/ha {$seasonPhrase}.").'.';
        }

        return trim("{$farmType} farms in {$place} averaged {$displayAvg} t/ha".($seasonPhrase ? " {$seasonPhrase}" : '')).'.';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $farmType, ?string $barangay, ?string $season, ?int $year, ?int $varietyId, bool $fil, int $farmCount = 0): array
    {
        return [
            'sufficient' => false,
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'season' => $season,
            'year' => $year,
            'variety_id' => $varietyId,
            'farm_count' => $farmCount,
            'avg_yield' => null,
            'min_yield' => null,
            'max_yield' => null,
            'by_season' => [],
            'top_varieties' => [],
            'common_months' => [],
            'insight' => $fil
                ? 'Hindi pa sapat ang bilang ng mga bukid para ipakita ang resulta (kailangan ng hindi bababa sa 5 aprubadong bukid).'
                : 'Not enough farms yet to show results (at least 5 approved farms are needed).',
        ];
    }
}
