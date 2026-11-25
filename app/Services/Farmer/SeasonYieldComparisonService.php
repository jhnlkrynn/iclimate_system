<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Support\RiceSeason;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Compares the active farm's stored seasonal yield estimate against past
 * recorded actual harvests from the same season type (Dry vs Dry, Wet vs Wet),
 * falling back to approved farms in the same barangay + farm type + season.
 */
class SeasonYieldComparisonService
{
    public const MAX_SEASONS = 5;

    public const SAME_THRESHOLD_PCT = 5;

    /**
     * @param  array<string, mixed>|null  $harvest
     * @return array<string, mixed>
     */
    public function compare(
        User $user,
        ?Farm $farm,
        ?array $harvest,
        CarbonInterface $now,
        string $locale = 'en',
    ): array {
        $now = CarbonImmutable::instance($now);
        $fil = $locale === 'fil';
        $seasonWindow = RiceSeason::window($now);
        $seasonType = $seasonWindow['season'];
        $yearsLabel = $seasonType === RiceSeason::DRY
            ? $seasonWindow['start']->year.'–'.$seasonWindow['end']->year
            : (string) $seasonWindow['start']->year;
        $seasonLabel = $fil
            ? ($seasonType === 'Wet' ? 'Tag-ulan' : 'Tag-araw').' '.$yearsLabel
            : $seasonType.' season '.$yearsLabel;
        $seasonWord = $fil
            ? ($seasonType === 'Wet' ? 'tag-ulan' : 'tag-araw')
            : strtolower($seasonType).' season';
        $seasonPlural = $fil
            ? ($seasonType === 'Wet' ? 'tag-ulan' : 'tag-araw')
            : strtolower($seasonType).' seasons';

        [$estimate, $ranAt] = $this->resolveEstimate($farm, $harvest, $seasonType);

        $farmApproved = $farm !== null
            && ($farm->verification_status ?? Farm::VERIFICATION_APPROVED) === Farm::VERIFICATION_APPROVED
            && $farm->archived_at === null;

        $history = $farmApproved
            ? $this->farmSameSeasonHistory($user, $farm, $seasonType, $fil)
            : [];

        $source = 'none';
        $baseline = null;
        $best = null;
        $lowest = null;
        $historyCount = count($history);
        $barangayFarmCount = 0;
        $sourceLabel = '';

        if ($historyCount > 0) {
            $source = 'farm_history';
            $yields = array_column($history, 'yield_t_ha');
            $baseline = round(array_sum($yields) / count($yields), 2);
            $best = round(max($yields), 2);
            $lowest = round(min($yields), 2);
            $sourceLabel = $fil
                ? "karaniwan ng huling {$historyCount} {$seasonPlural}"
                : "avg of last {$historyCount} ".($historyCount === 1 ? $seasonWord : $seasonPlural);
        } else {
            $barangay = $farm?->barangay ?? $user->registeredBarangay();
            $farmType = $farm?->farm_type ?? $user->farmerProfile?->farm_type;
            $fallback = $this->barangaySameSeasonFallback($barangay, $farmType, $seasonType);

            if ($fallback !== null) {
                $source = 'barangay_fallback';
                $baseline = $fallback['average'];
                $best = $fallback['best'];
                $lowest = $fallback['lowest'];
                $barangayFarmCount = $fallback['farm_count'];
                $sourceLabel = $fil
                    ? "Karaniwan sa barangay ({$barangayFarmCount} bukid)"
                    : "Barangay average ({$barangayFarmCount} ".($barangayFarmCount === 1 ? 'farm' : 'farms').')';
            }
        }

        $diffTHa = ($estimate !== null && $baseline !== null) ? round($estimate - $baseline, 2) : null;
        $diffPct = ($diffTHa !== null && $baseline > 0) ? (int) round(($diffTHa / $baseline) * 100) : null;

        $status = null;
        $statusLabel = null;
        if ($diffPct !== null) {
            if (abs($diffPct) <= self::SAME_THRESHOLD_PCT) {
                $status = 'same';
                $statusLabel = $fil ? 'Halos pareho sa dati' : 'About the same as usual';
            } elseif ($diffPct > self::SAME_THRESHOLD_PCT) {
                $status = 'higher';
                $statusLabel = $fil ? 'Mas mataas kaysa dati' : 'Higher than usual';
            } else {
                $status = 'lower';
                $statusLabel = $fil ? 'Mas mababa kaysa dati' : 'Lower than usual';
            }
        }

        $summaryLine = $this->buildSummaryLine(
            $source,
            $estimate,
            $baseline,
            $diffPct,
            $sourceLabel,
            $fil,
        );

        $comparisonInsight = $this->buildComparisonInsight($source, $status, $seasonWord, $fil);

        return [
            'season_type' => $seasonType,
            'season_label' => $seasonLabel,
            'estimate' => $estimate,
            'ran_at' => $ranAt,
            'source' => $source,
            'source_label' => $sourceLabel,
            'baseline' => $baseline,
            'best' => $best,
            'lowest' => $lowest,
            'history' => $history,
            'history_count' => $historyCount,
            'barangay_farm_count' => $barangayFarmCount,
            'diff_t_ha' => $diffTHa,
            'diff_pct' => $diffPct,
            'status' => $status,
            'status_label' => $statusLabel,
            'summary_line' => $summaryLine,
            'comparison_insight' => $comparisonInsight,
            'empty_message' => $source === 'none'
                ? ($fil
                    ? 'Wala pang nakatalang nakaraang ani. Itala ang iyong ani upang maihambing sa susunod na panahon.'
                    : 'No past harvests yet. Record your harvest to compare next season.')
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $harvest
     * @return array{0: ?float, 1: mixed}
     */
    private function resolveEstimate(?Farm $farm, ?array $harvest, string $seasonType): array
    {
        if (is_array($harvest) && ($harvest['state'] ?? null) === 'ok' && is_numeric($harvest['yield'] ?? null)) {
            return [round((float) $harvest['yield'], 2), $harvest['ran_at'] ?? null];
        }

        if ($farm === null || ! SchemaCache::hasTable('yield_predictions')) {
            return [null, null];
        }

        $prediction = YieldPrediction::query()
            ->where('scope', YieldPrediction::SCOPE_FARM)
            ->where('farm_id', $farm->getKey())
            ->where(function (Builder $q) use ($seasonType): void {
                $q->where('season', $seasonType)->orWhereNull('season');
            })
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        if (! $prediction || ! is_numeric($prediction->predicted_yield) || (float) $prediction->predicted_yield <= 0) {
            return [null, null];
        }

        return [round((float) $prediction->predicted_yield, 2), $prediction->generated_at ?? $prediction->created_at];
    }

    /**
     * @return list<array{label: string, season_year: int, yield_t_ha: float, tons: ?float, area_ha: ?float, harvested_on: ?string}>
     */
    private function farmSameSeasonHistory(User $user, Farm $farm, string $seasonType, bool $fil): array
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules') || ! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return [];
        }

        $query = FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            ->when(SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), function (Builder $q) use ($farm): void {
                $q->where(fn (Builder $w) => $w->where('farm_id', $farm->getKey())->when($farm->is_primary, fn (Builder $p) => $p->orWhereNull('farm_id')));
            })
            ->whereNotNull('actual_yield')
            ->where('actual_yield', '>', 0)
            ->orderByDesc('harvested_on')
            ->orderByDesc('planting_date')
            ->orderByDesc('id');

        $rows = SampleData::without($query, 'farmer_planting_schedules')->get();
        $matching = $this->filterSameSeasonRows($rows, $seasonType);

        if ($matching->isEmpty()) {
            return [];
        }

        $farmArea = $farm->knownHectares();

        $items = $matching->take(self::MAX_SEASONS)->map(function (FarmerPlantingSchedule $row) use ($farmArea, $seasonType, $fil): array {
            $yield = round((float) $row->actual_yield, 2);
            $area = (is_numeric($row->farm_area_hectares) && (float) $row->farm_area_hectares > 0)
                ? (float) $row->farm_area_hectares
                : $farmArea;
            $tons = ($area !== null && $area > 0) ? round($yield * $area, 2) : null;
            $refDate = $row->harvested_on ?? $row->planting_date;
            $year = $refDate?->year ?? (int) now('Asia/Manila')->year;
            $shortSeason = $fil ? ($seasonType === 'Wet' ? 'Tag-ulan' : 'Tag-araw') : $seasonType;

            return [
                'label' => "{$shortSeason} {$year}",
                'season_year' => $year,
                'yield_t_ha' => $yield,
                'tons' => $tons,
                'area_ha' => $area !== null ? round($area, 2) : null,
                'harvested_on' => $row->harvested_on?->format('M j, Y'),
            ];
        })->values()->all();

        // Chronological order (oldest -> newest) for the bar chart.
        return array_reverse($items);
    }

    /**
     * @return ?array{average: float, best: float, lowest: float, farm_count: int}
     */
    private function barangaySameSeasonFallback(?string $barangay, ?string $farmType, string $seasonType): ?array
    {
        if (! $barangay || ! SchemaCache::hasTable('farmer_planting_schedules') || ! SchemaCache::hasTable('farms')) {
            return null;
        }

        $query = FarmerPlantingSchedule::query()
            ->whereNotNull('farmer_planting_schedules.actual_yield')
            ->where('farmer_planting_schedules.actual_yield', '>', 0)
            ->whereHas('farm', function (Builder $fq) use ($barangay, $farmType): void {
                $fq->where('verification_status', Farm::VERIFICATION_APPROVED)
                    ->whereNull('archived_at')
                    ->where('barangay', $barangay)
                    ->when($farmType !== null, fn (Builder $q) => $q->where('farm_type', $farmType));
            })
            ->whereHas('farmer', function (Builder $uq): void {
                $uq->where('email', 'not like', '%@iclimate.local');
            });

        $rows = SampleData::without($query, 'farmer_planting_schedules')->get([
            'farmer_planting_schedules.id',
            'farmer_planting_schedules.farm_id',
            'farmer_planting_schedules.user_id',
            'farmer_planting_schedules.season',
            'farmer_planting_schedules.planting_date',
            'farmer_planting_schedules.harvested_on',
            'farmer_planting_schedules.actual_yield',
        ]);

        $matching = $this->filterSameSeasonRows($rows, $seasonType);
        if ($matching->isEmpty()) {
            return null;
        }

        $yields = $matching->map(fn (FarmerPlantingSchedule $r): float => (float) $r->actual_yield);
        $farmCount = $matching->map(fn (FarmerPlantingSchedule $r): string => $r->farm_id ? 'f:'.$r->farm_id : 'u:'.$r->user_id)
            ->unique()
            ->count();

        return [
            'average' => round((float) $yields->avg(), 2),
            'best' => round((float) $yields->max(), 2),
            'lowest' => round((float) $yields->min(), 2),
            'farm_count' => $farmCount,
        ];
    }

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $rows
     * @return Collection<int, FarmerPlantingSchedule>
     */
    private function filterSameSeasonRows(Collection $rows, string $seasonType): Collection
    {
        return $rows->filter(function (FarmerPlantingSchedule $row) use ($seasonType): bool {
            $rowSeason = $row->season;
            if (! $rowSeason && ($row->planting_date || $row->harvested_on)) {
                $rowSeason = RiceSeason::forDate($row->planting_date ?? $row->harvested_on);
            }

            return strcasecmp((string) $rowSeason, $seasonType) === 0;
        })->values();
    }

    private function buildSummaryLine(
        string $source,
        ?float $estimate,
        ?float $baseline,
        ?int $diffPct,
        string $sourceLabel,
        bool $fil,
    ): ?string {
        if ($source === 'none') {
            return null;
        }

        $pctText = $diffPct !== null ? (($diffPct > 0 ? '+' : '').$diffPct.'%') : null;
        $estFmt = $estimate !== null ? number_format($estimate, 2) : null;
        $baseFmt = $baseline !== null ? number_format($baseline, 2) : null;

        if ($source === 'farm_history') {
            if ($estFmt !== null && $baseFmt !== null && $pctText !== null) {
                return $fil
                    ? "Tantiya {$estFmt} t/ha laban sa dati mong {$baseFmt} t/ha ({$sourceLabel}) · {$pctText}"
                    : "Estimate {$estFmt} t/ha vs your usual {$baseFmt} t/ha ({$sourceLabel}) · {$pctText}";
            }

            return $fil
                ? "Dati mong ani: {$baseFmt} t/ha ({$sourceLabel})"
                : "Your usual yield: {$baseFmt} t/ha ({$sourceLabel})";
        }

        // barangay_fallback
        if ($estFmt !== null && $baseFmt !== null && $pctText !== null) {
            return $fil
                ? "Tantiya {$estFmt} t/ha laban sa {$sourceLabel} na {$baseFmt} t/ha · {$pctText}"
                : "Estimate {$estFmt} t/ha vs {$sourceLabel} {$baseFmt} t/ha · {$pctText}";
        }

        return "{$sourceLabel}: {$baseFmt} t/ha";
    }

    private function buildComparisonInsight(string $source, ?string $status, string $seasonWord, bool $fil): ?string
    {
        if ($source === 'none' || $status === null) {
            return null;
        }

        if ($source === 'farm_history') {
            return match ($status) {
                'higher' => $fil
                    ? "Mas mataas kaysa dati mong ani sa {$seasonWord}."
                    : "Higher than your usual {$seasonWord} harvest.",
                'lower' => $fil
                    ? "Mas mababa kaysa dati mong ani sa {$seasonWord}."
                    : "Lower than your usual {$seasonWord} harvest.",
                default => $fil
                    ? "Halos pareho sa dati mong ani sa {$seasonWord}."
                    : "About the same as your usual {$seasonWord} harvest.",
            };
        }

        return match ($status) {
            'higher' => $fil
                ? "Mas mataas kaysa karaniwang ani sa barangay ngayong {$seasonWord}."
                : "Higher than the barangay average for {$seasonWord}.",
            'lower' => $fil
                ? "Mas mababa kaysa karaniwang ani sa barangay ngayong {$seasonWord}."
                : "Lower than the barangay average for {$seasonWord}.",
            default => $fil
                ? "Halos pareho sa karaniwang ani sa barangay ngayong {$seasonWord}."
                : "About the same as the barangay average for {$seasonWord}.",
        };
    }
}
