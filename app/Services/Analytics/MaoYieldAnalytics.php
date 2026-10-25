<?php

namespace App\Services\Analytics;

use App\Models\FarmerPlantingSchedule;
use App\Models\MunicipalYieldPrediction;
use App\Support\ClimateHistory;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;

/**
 * Panel revisions 3.1, 3.2 and 4.2: the yield series behind the MAO dashboard's charts. Every series carries its year
 * and season, so the page's Year range / Season filter (one filter for all three charts) recomputes the charts and their
 * totals in the browser without another request. All yields are t/ha, rounded to two decimals.
 *
 *  - yieldByYear():     average yield per year, for all months and per season (Rice Yield by Year)
 *  - rainAndYield():    per rice season, total rainfall (mm) and average yield (t/ha) (Weather Impact: Rain and Yield)
 *  - predictedActual(): the model's predicted yield next to the recorded yield for all of Lian, per month, with the
 *                       error measures, plus a per-barangay drill-down from farmers' own harvests
 */
class MaoYieldAnalytics
{
    public function __construct(private readonly ProjectYieldDataset $yields) {}

    /**
     * @return array{state: string, years: array<int, int>, all: array<int, ?float>, wet: array<int, ?float>, dry: array<int, ?float>, months: array<int, int>, source: string}
     */
    public function yieldByYear(): array
    {
        $rows = $this->yields->rows()->filter(fn ($r): bool => (float) ($r['yield'] ?? 0) > 0);
        $years = $rows->pluck('year')->map(fn ($y): int => (int) $y)->unique()->sort()->values()->all();
        $mean = fn (Collection $set): ?float => $set->isEmpty() ? null : round((float) $set->avg('yield'), 2);

        return [
            'state' => $years === [] ? 'nodata' : 'ok',
            'years' => $years,
            'all' => array_map(fn (int $y) => $mean($rows->where('year', $y)), $years),
            'wet' => array_map(fn (int $y) => $mean($rows->where('year', $y)->filter(fn ($r) => RiceSeason::forMonth((int) $r['month']) === RiceSeason::WET)), $years),
            'dry' => array_map(fn (int $y) => $mean($rows->where('year', $y)->filter(fn ($r) => RiceSeason::forMonth((int) $r['month']) === RiceSeason::DRY)), $years),
            'months' => array_map(fn (int $y): int => $rows->where('year', $y)->count(), $years),
            'source' => ProjectYieldDataset::SOURCE_NAME,
        ];
    }

    /**
     * One entry per rice season: total rainfall over the season's months on record (mm) and the season's average yield
     * (t/ha). A Dry season runs November to April and belongs to the year it starts in.
     *
     * @return array{state: string, periods: array<int, array{label: string, season: string, year: int, rain: float, yield: float, rain_months: int, yield_months: int}>}
     */
    public function rainAndYield(): array
    {
        $yield = $this->yields->rows()->filter(fn ($r): bool => (float) ($r['yield'] ?? 0) > 0)
            ->groupBy(fn ($r): string => $this->seasonKey((int) $r['year'], (int) $r['month']));
        $rain = (ClimateHistory::stationMonths() ?? collect())
            ->groupBy(fn (object $m): string => $this->seasonKey((int) substr($m->record_date, 0, 4), (int) substr($m->record_date, 5, 2)));

        $periods = $yield->filter(fn (Collection $rows, string $key): bool => $rain->has($key))
            ->map(function (Collection $rows, string $key) use ($rain): array {
                [$season, $year] = explode(' ', $key);

                return [
                    'label' => $season === RiceSeason::WET ? 'Wet '.$year : 'Dry '.$year.'–'.((int) $year + 1),
                    'season' => $season,
                    'year' => (int) $year,
                    'rain' => round((float) $rain[$key]->sum('rainfall'), 1),
                    'yield' => round((float) $rows->avg('yield'), 2),
                    'rain_months' => $rain[$key]->count(),
                    'yield_months' => $rows->count(),
                ];
            })
            ->sortBy(fn (array $p): string => $p['year'].($p['season'] === RiceSeason::WET ? 'a' : 'b'))
            ->values()->all();

        return ['state' => count($periods) >= 3 ? 'ok' : 'nodata', 'periods' => $periods];
    }

    /**
     * The model's predicted municipal yield against the recorded yield, per month, with error measures. Months the
     * model was trained on (TRAIN) are kept apart from held-out months (TEST): only TEST shows the model's true accuracy.
     *
     * @param  array<int, string>|null  $barangays  the MAO user's barangays for the drill-down (null = all)
     * @return array<string, mixed>
     */
    public function predictedActual(?array $barangays = null): array
    {
        $months = SchemaCache::hasTable('municipal_yield_predictions')
            ? MunicipalYieldPrediction::query()->whereNull('barangay')->whereNotNull('actual_yield')->orderBy('year')->orderBy('month')
                ->get(['year', 'month', 'season', 'predicted_yield', 'actual_yield', 'split', 'model'])
            : collect();
        // Expected vs Actual Yield: until iclimate:import-yield-backtest has been run, read the backtest file it loads.
        if ($months->isEmpty()) {
            $months = $this->backtestFile();
        }

        $rows = $months->map(fn (object $m): array => [
            'year' => $m->year,
            'month' => $m->month,
            // The rice season the month belongs to (a Dry season keeps the year it started in).
            'season' => RiceSeason::forMonth($m->month),
            'season_year' => RiceSeason::forMonth($m->month) === RiceSeason::DRY && $m->month <= 4 ? $m->year - 1 : $m->year,
            'predicted' => round($m->predicted_yield, 2),
            'actual' => round((float) $m->actual_yield, 2),
            'split' => $m->split,
        ])->values();

        return [
            'state' => $rows->isEmpty() ? 'nodata' : 'ok',
            'model' => $months->first()?->model,
            'months' => $rows->all(),
            'overall' => self::accuracy($rows),
            'test' => self::accuracy($rows->where('split', 'TEST')),
            'train' => self::accuracy($rows->where('split', 'TRAIN')),
            'barangays' => $this->barangayDrilldown($barangays),
        ];
    }

    /**
     * The months of the generated backtest CSV (ImportYieldBacktest::DEFAULT_FILE) that have a recorded yield, shaped
     * like MunicipalYieldPrediction rows; empty when the file is missing.
     *
     * @return Collection<int, object{year: int, month: int, predicted_yield: float, actual_yield: float, split: ?string, model: string}>
     */
    private function backtestFile(): Collection
    {
        $path = base_path(\App\Console\Commands\ImportYieldBacktest::DEFAULT_FILE);
        if (! is_file($path) || ($handle = fopen($path, 'r')) === false) {
            return collect();
        }
        $header = array_map(fn ($h) => strtoupper(trim((string) $h)), (array) fgetcsv($handle));
        $months = collect();
        while (($line = fgetcsv($handle)) !== false) {
            $row = count($line) === count($header) ? array_combine($header, $line) : [];
            if (! is_numeric($row['YEAR'] ?? null) || ! is_numeric($row['MONTH'] ?? null) || ! is_numeric($row['PREDICTED_YIELD'] ?? null) || ! is_numeric($row['ACTUAL_YIELD'] ?? null)) {
                continue;
            }
            $months->push((object) [
                'year' => (int) $row['YEAR'],
                'month' => (int) $row['MONTH'],
                'predicted_yield' => (float) $row['PREDICTED_YIELD'],
                'actual_yield' => (float) $row['ACTUAL_YIELD'],
                'split' => strtoupper((string) ($row['SPLIT'] ?? '')) ?: null,
                'model' => (string) ($row['MODEL'] ?? 'Rice yield model'),
            ]);
        }
        fclose($handle);

        return $months->sortBy(fn (object $m): int => $m->year * 100 + $m->month)->values();
    }

    /**
     * MAE, MAPE and accuracy (100 − MAPE) of a set of months, or null without months.
     *
     * @param  Collection<int, array{predicted: float, actual: float}>  $rows
     * @return array{count: int, predicted: float, actual: float, difference: float, mae: float, mape: float, accuracy: float}|null
     */
    public static function accuracy(Collection $rows): ?array
    {
        $rows = $rows->filter(fn (array $r): bool => $r['actual'] > 0)->values();
        if ($rows->isEmpty()) {
            return null;
        }
        $mape = $rows->avg(fn (array $r): float => abs($r['predicted'] - $r['actual']) / $r['actual'] * 100);

        return [
            'count' => $rows->count(),
            'predicted' => round((float) $rows->avg('predicted'), 2),
            'actual' => round((float) $rows->avg('actual'), 2),
            'difference' => round((float) $rows->avg('predicted') - (float) $rows->avg('actual'), 2),
            'mae' => round((float) $rows->avg(fn (array $r): float => abs($r['predicted'] - $r['actual'])), 2),
            'mape' => round((float) $mape, 1),
            'accuracy' => round(max(0, 100 - (float) $mape), 1),
        ];
    }

    /**
     * Predicted (the estimate saved for the planting) against the yield the farmer harvested, per barangay.
     *
     * @param  array<int, string>|null  $barangays
     * @return array<int, array<string, mixed>>
     */
    private function barangayDrilldown(?array $barangays): array
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return [];
        }

        return FarmerPlantingSchedule::query()
            ->where('actual_yield', '>', 0)->where('predicted_yield', '>', 0)
            ->when($barangays !== null, fn ($q) => $q->whereIn('barangay', $barangays))
            ->get(['barangay', 'predicted_yield', 'actual_yield'])
            ->groupBy(fn (FarmerPlantingSchedule $s): string => (string) (LianBarangays::canonicalName($s->barangay) ?? $s->barangay ?? 'Unknown'))
            ->map(fn (Collection $plans, string $barangay): array => ['barangay' => $barangay] + (self::accuracy($plans->map(fn (FarmerPlantingSchedule $s): array => [
                'predicted' => (float) $s->predicted_yield, 'actual' => (float) $s->actual_yield,
            ])) ?? []))
            ->sortBy('barangay')->values()->all();
    }

    /** "Wet 2024" / "Dry 2024": a month's rice season, keyed by the year the season starts. */
    private function seasonKey(int $year, int $month): string
    {
        if (RiceSeason::forMonth($month) === RiceSeason::WET) {
            return RiceSeason::WET.' '.$year;
        }

        return RiceSeason::DRY.' '.($month >= 11 ? $year : $year - 1);
    }
}
