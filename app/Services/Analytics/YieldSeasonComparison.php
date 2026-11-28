<?php

namespace App\Services\Analytics;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\Climate\OpenMeteoArchiveMonthly;
use App\Services\Prediction\YieldModelGate;
use App\Support\ClimateHistory;
use App\Support\LianBarangays;
use App\Support\RecordProvenance;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * /analysis/yield: this season's yield estimate for a farm next to the same season of the last five years
 * (owner request 2026-11-28).
 *
 * Weather, for the season's growing months only (Wet May–Sep, Dry Nov–Mar, the harvest month included), one month at
 * a time, from stored data only (no outside call on a page load):
 *   1. Climate Records (the Ambulong import and MAO entries), with max / min from the Ambulong station file;
 *   2. the Ambulong station file;
 *   3. the stored Open-Meteo archive (ERA5 at Ambulong), put on the station's scale where it can be;
 *   4. for a month that is not over yet: the stored ready monthly weather forecast.
 * Rainy days come from the archive (days with >= 1 mm) because no other stored source counts them.
 *
 * Yield of a past season:
 *   1. the farm's own recorded harvests (only for an approved farm; demo harvests are never used);
 *   2. else the MAO Rice Production average of the same barangay, farm type and season (demo rows and split season
 *      totals are not barangay records);
 *   3. else the Lian-wide MAO average of that season (demo rows left out).
 * A season with none of these shows "No record". Nothing is invented.
 */
final class YieldSeasonComparison
{
    public const YEARS = 5;

    /** Growing months (harvest month included) of each season, in order. */
    public const MONTHS = [
        RiceSeason::WET => [5, 6, 7, 8, 9],
        RiceSeason::DRY => [11, 12, 1, 2, 3],
    ];

    /** A season's rain this far from the usual (fraction) is called drier / wetter. */
    private const RAIN_BAND = 0.15;

    /** A yield gap below this (t/ha) is not worth a sentence. */
    private const YIELD_GAP = 0.05;

    public const SOURCE_FARM = 'farm';

    public const SOURCE_BARANGAY = 'barangay';

    public const SOURCE_LIAN = 'lian';

    public const WEATHER_RECORDS = 'records';

    public const WEATHER_STATION = 'station';

    public const WEATHER_ARCHIVE = 'archive';

    public const WEATHER_FORECAST = 'forecast';

    /** @var array<string, string>|null */
    private ?array $riceClasses = null;

    /**
     * @return array{
     *     season: string, farm_type: string, barangay: ?string, farm_approved: bool, target_month: string,
     *     rows: list<array<string, mixed>>, current: array<string, mixed>, estimate: ?array<string, mixed>,
     *     usual: ?float, usual_count: int, diff_pct: ?float, insight: list<array{key: string, replace: array<string, string>}>,
     *     updated_at: ?CarbonImmutable
     * }
     */
    public function build(User $user, ?Farm $farm, string $season, ?CarbonImmutable $today = null): array
    {
        $season = $season === RiceSeason::DRY ? RiceSeason::DRY : RiceSeason::WET;
        $target = YieldPredictionRequest::targetMonthFor($season, $today);
        $currentStart = $season === RiceSeason::DRY ? $target->year - 1 : $target->year;
        $farmType = in_array($farm?->farm_type, ['Irrigated', 'Rainfed'], true) ? $farm->farm_type : 'Irrigated';
        $barangay = $farm ? (LianBarangays::canonicalName($farm->barangay) ?: null) : null;
        $approved = $farm !== null && $farm->isApproved();

        $weather = $this->weatherMonths($today);
        $harvests = $approved ? $this->farmHarvests($farm) : [];

        $rows = [];
        for ($start = $currentStart - self::YEARS; $start < $currentStart; $start++) {
            $rows[] = [
                'start_year' => $start,
                'is_current' => false,
                'weather' => $this->seasonWeather($season, $start, $weather),
                'yield' => $harvests[$season.'|'.$start]
                    ?? $this->barangayYield($season, $start, $barangay, $farmType)
                    ?? $this->lianYield($season, $start),
            ];
        }

        $estimate = $farm ? $this->estimate($user, $farm, $season, $target) : null;
        $current = [
            'start_year' => $currentStart,
            'is_current' => true,
            'weather' => $this->seasonWeather($season, $currentStart, $weather),
            'yield' => $estimate,
        ];

        $withYield = array_values(array_filter($rows, fn (array $r): bool => $r['yield'] !== null));
        $usual = $withYield === [] ? null : round(array_sum(array_map(fn (array $r): float => $r['yield']['t_ha'], $withYield)) / count($withYield), 2);
        $diffPct = $usual && $estimate ? round(($estimate['t_ha'] - $usual) / $usual * 100) : null;

        $stamps = array_filter(array_merge(
            array_map(fn (array $r) => $r['weather']['updated_at'], [...$rows, $current]),
            array_map(fn (array $r) => $r['yield']['updated_at'] ?? null, [...$rows, $current]),
        ));
        usort($stamps, fn (CarbonImmutable $a, CarbonImmutable $b) => $b <=> $a);

        return [
            'season' => $season,
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'farm_approved' => $approved,
            'target_month' => $target->toDateString(),
            'rows' => $rows,
            'current' => $current,
            'estimate' => $estimate,
            'estimate_shown' => (bool) rescue(fn () => YieldModelGate::showsStoredEstimate(), false, false),
            'usual' => $usual,
            'usual_count' => count($withYield),
            'diff_pct' => $diffPct,
            'insight' => $this->insight($season, $rows, $current),
            'updated_at' => $stamps[0] ?? null,
        ];
    }

    // ---------------------------------------------------------------- weather

    /**
     * Every stored month keyed Y-m: rain, tmax, tmin, humidity, rainy_days, source, updated_at, forecast.
     *
     * @return array<string, array<string, mixed>>
     */
    private function weatherMonths(?CarbonImmutable $today): array
    {
        $thisMonth = ($today ?? CarbonImmutable::now(\App\Support\BusinessClock::timezone()))->format('Y-m');

        $station = [];
        $dataset = rescue(fn () => app(AmbulongHistoricalClimateDataset::class)->load(), [], false);
        foreach (collect($dataset['observations'] ?? [])->all() as $obs) {
            if (is_numeric($obs['rainfall'] ?? null)) {
                $station[sprintf('%04d-%02d', (int) $obs['year'], (int) $obs['month'])] = $obs;
            }
        }

        $archive = rescue(fn () => OpenMeteoArchiveMonthly::months(), [], false);
        $stationRows = array_map(fn (array $o): object => (object) $o, $station);
        $calibrated = rescue(fn () => OpenMeteoArchiveMonthly::calibrated($stationRows), [], false);
        $archiveAt = rescue(fn () => OpenMeteoArchiveMonthly::fetchedAt(), null, false);

        $months = [];
        // 4. stored monthly forecasts, only for months not over yet
        foreach ($this->forecasts() as $key => $f) {
            if ($key >= $thisMonth) {
                $months[$key] = $f;
            }
        }
        // 3. Open-Meteo archive
        foreach ($archive as $key => $a) {
            if ($key >= $thisMonth) {
                continue;
            }
            $c = $calibrated[$key] ?? null;
            $shift = $c ? (float) $c->temperature - (float) $a->temperature : 0.0;
            $months[$key] = [
                'rain' => (float) ($c->rainfall ?? $a->rainfall),
                'tmax' => $a->tmax !== null ? $a->tmax + $shift : null,
                'tmin' => $a->tmin !== null ? $a->tmin + $shift : null,
                'humidity' => $c->humidity ?? $a->humidity,
                'source' => self::WEATHER_ARCHIVE,
                'updated_at' => $archiveAt,
                'forecast' => false,
            ];
        }
        // 2. Ambulong station file
        foreach ($station as $key => $s) {
            $months[$key] = [
                'rain' => (float) $s['rainfall'],
                'tmax' => is_numeric($s['tmax'] ?? null) ? (float) $s['tmax'] : null,
                'tmin' => is_numeric($s['tmin'] ?? null) ? (float) $s['tmin'] : null,
                'humidity' => is_numeric($s['humidity'] ?? null) ? (float) $s['humidity'] : null,
                'source' => self::WEATHER_STATION,
                'updated_at' => null,
                'forecast' => false,
            ];
        }
        // 1. Climate Records (plausible, not sample rows), averaged per month
        foreach ($this->climateRecords() as $key => $r) {
            if ($key >= $thisMonth) {
                continue;
            }
            $s = $station[$key] ?? null;
            $months[$key] = [
                'rain' => $r['rain'],
                'tmax' => is_numeric($s['tmax'] ?? null) ? (float) $s['tmax'] : null,
                'tmin' => is_numeric($s['tmin'] ?? null) ? (float) $s['tmin'] : null,
                'humidity' => $r['humidity'],
                'source' => self::WEATHER_RECORDS,
                'updated_at' => $r['updated_at'],
                'forecast' => false,
            ];
        }
        // Rainy days are only counted by the archive.
        foreach ($months as $key => $m) {
            $months[$key]['rainy_days'] = ! $m['forecast'] && isset($archive[$key]) ? $archive[$key]->rainy_days : null;
        }

        return $months;
    }

    /** @return array<string, array{rain: float, humidity: ?float, updated_at: ?CarbonImmutable}> */
    private function climateRecords(): array
    {
        try {
            if (! SchemaCache::hasTable('climate_records')) {
                return [];
            }
            $out = [];
            foreach (ClimateHistory::entered()->whereNotNull('rainfall')->get(['record_date', 'rainfall', 'humidity', 'updated_at'])
                ->filter(fn ($r) => $r->record_date !== null)
                ->groupBy(fn ($r) => $r->record_date->format('Y-m')) as $key => $rows) {
                $humidity = $rows->pluck('humidity')->filter(fn ($v) => is_numeric($v));
                $updated = $rows->pluck('updated_at')->filter()->max();
                $out[$key] = [
                    'rain' => round((float) $rows->avg('rainfall'), 2),
                    'humidity' => $humidity->isEmpty() ? null : round((float) $humidity->avg(), 2),
                    'updated_at' => $updated ? CarbonImmutable::instance($updated) : null,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** @return array<string, array<string, mixed>> the latest ready monthly forecast per month */
    private function forecasts(): array
    {
        try {
            if (! SchemaCache::hasTable('monthly_weather_predictions')) {
                return [];
            }
            $out = [];
            foreach (\App\Models\MonthlyWeatherPrediction::query()->where('status', 'ready')->whereNotNull('rainfall')
                ->orderBy('generated_at')->get(['prediction_year', 'prediction_month', 'rainfall', 'tmax', 'tmin', 'humidity', 'generated_at']) as $row) {
                $out[sprintf('%04d-%02d', $row->prediction_year, $row->prediction_month)] = [
                    'rain' => (float) $row->rainfall,
                    'tmax' => is_numeric($row->tmax) ? (float) $row->tmax : null,
                    'tmin' => is_numeric($row->tmin) ? (float) $row->tmin : null,
                    'humidity' => is_numeric($row->humidity) ? (float) $row->humidity : null,
                    'source' => self::WEATHER_FORECAST,
                    'updated_at' => $row->generated_at ? CarbonImmutable::parse($row->generated_at) : null,
                    'forecast' => true,
                ];
            }

            return $out;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $months
     * @return array<string, mixed>
     */
    private function seasonWeather(string $season, int $startYear, array $months): array
    {
        $found = [];
        foreach (self::MONTHS[$season] as $m) {
            $year = $season === RiceSeason::DRY && $m < 7 ? $startYear + 1 : $startYear;
            $key = sprintf('%04d-%02d', $year, $m);
            if (isset($months[$key])) {
                $found[$key] = $months[$key];
            }
        }
        $all = count(self::MONTHS[$season]);
        $avg = function (string $field) use ($found): ?float {
            $v = array_values(array_filter(array_column($found, $field), fn ($x) => $x !== null));

            return $v === [] ? null : round(array_sum($v) / count($v), 1);
        };
        $rainy = array_column($found, 'rainy_days');
        $stamps = array_filter(array_column($found, 'updated_at'));
        usort($stamps, fn ($a, $b) => $b <=> $a);

        return [
            'months' => count($found),
            'months_needed' => $all,
            'complete' => count($found) === $all,
            'rain' => $found === [] ? null : round(array_sum(array_column($found, 'rain'))),
            'tmax' => $avg('tmax'),
            'tmin' => $avg('tmin'),
            'humidity' => $avg('humidity'),
            'rainy_days' => count($found) === $all && ! in_array(null, $rainy, true) ? (int) array_sum($rainy) : null,
            'sources' => array_values(array_unique(array_column($found, 'source'))),
            'forecast_months' => count(array_filter($found, fn (array $m): bool => $m['forecast'])),
            'updated_at' => $stamps[0] ?? null,
        ];
    }

    // ---------------------------------------------------------------- yield

    /**
     * The farm's recorded harvests per season ("Wet|2024", "Dry|2024" = Nov 2024 – Apr 2025), demo harvests left out.
     *
     * @return array<string, array<string, mixed>>
     */
    private function farmHarvests(Farm $farm): array
    {
        try {
            if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
                return [];
            }
            $plans = RecordProvenance::withoutDemoHarvests(FarmerPlantingSchedule::query())
                ->where('farm_id', $farm->getKey())
                ->whereNotNull('actual_yield')->where('actual_yield', '>', 0)
                ->get(['id', 'farm_id', 'season', 'farm_area_hectares', 'actual_yield', 'harvested_on', 'scheduled_harvest_date', 'harvest_month', 'planting_date', 'updated_at']);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $fallbackArea = $farm->modelArea();
        $groups = [];
        foreach ($plans as $plan) {
            $date = $plan->harvested_on ?? $plan->scheduled_harvest_date ?? $plan->harvest_month ?? $plan->planting_date;
            if ($date === null) {
                continue;
            }
            $date = CarbonImmutable::parse($date);
            $season = in_array($plan->season, [RiceSeason::WET, RiceSeason::DRY], true) ? $plan->season : RiceSeason::forDate($date);
            // A dry crop is harvested in the new year of the season that started in November.
            $start = $season === RiceSeason::DRY && $date->month < 7 ? $date->year - 1 : $date->year;
            $groups[$season.'|'.$start][] = $plan;
        }

        $out = [];
        foreach ($groups as $key => $list) {
            $weighted = 0.0;
            $area = 0.0;
            $tons = 0.0;
            $tonsKnown = true;
            foreach ($list as $plan) {
                $ha = (float) ($plan->farm_area_hectares ?: $fallbackArea);
                $t = (float) $plan->actual_yield;
                if ($ha > 0) {
                    $weighted += $t * $ha;
                    $area += $ha;
                    $tons += $t * $ha;
                } else {
                    $tonsKnown = false;
                }
            }
            $tHa = $area > 0 ? $weighted / $area : collect($list)->avg(fn ($p) => (float) $p->actual_yield);
            $updated = collect($list)->pluck('updated_at')->filter()->max();
            $out[$key] = [
                't_ha' => round((float) $tHa, 2),
                'tons' => $tonsKnown && $area > 0 ? round($tons, 1) : null,
                'source' => self::SOURCE_FARM,
                'count' => count($list),
                'actual' => true,
                'updated_at' => $updated ? CarbonImmutable::instance($updated) : null,
            ];
        }

        return $out;
    }

    /** MAO Rice Production records of the barangay, farm type and season (measured records only). */
    private function barangayYield(string $season, int $startYear, ?string $barangay, string $farmType): ?array
    {
        if ($barangay === null) {
            return null;
        }
        $rows = $this->riceRows($season, $startYear)
            ->filter(fn (RiceProduction $r) => LianBarangays::canonicalName($r->barangay) === $barangay && $r->irrigation_type === $farmType)
            ->filter(fn (RiceProduction $r) => in_array($this->riceClass($r), [RecordProvenance::VERIFIED, RecordProvenance::UNVERIFIED], true));

        return $this->riceYield($rows, self::SOURCE_BARANGAY);
    }

    /** All of Lian's MAO Rice Production records of the season (demo rows left out). */
    private function lianYield(string $season, int $startYear): ?array
    {
        $rows = $this->riceRows($season, $startYear)->filter(fn (RiceProduction $r) => $this->riceClass($r) !== RecordProvenance::DEMO);

        return $this->riceYield($rows, self::SOURCE_LIAN);
    }

    /** @param  Collection<int, RiceProduction>  $rows */
    private function riceYield(Collection $rows, string $source): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }
        $updated = $rows->pluck('updated_at')->filter()->max();

        return [
            't_ha' => round((float) $rows->avg('yield_per_hectare'), 2),
            'tons' => null,
            'source' => $source,
            'count' => $rows->count(),
            'actual' => true,
            'updated_at' => $updated ? CarbonImmutable::instance($updated) : null,
        ];
    }

    /** @var array<string, Collection<int, RiceProduction>> */
    private array $riceCache = [];

    /**
     * MAO records of one season with a yield. The MAO stores a Dry season under the year it starts (Dry 2024 =
     * Nov 2024 – Apr 2025).
     *
     * @return Collection<int, RiceProduction>
     */
    private function riceRows(string $season, int $startYear): Collection
    {
        return $this->riceCache[$season.'|'.$startYear] ??= rescue(fn () => SchemaCache::hasTable('rice_productions')
            ? RiceProduction::query()->where('season', $season)->where('year', $startYear)
                ->whereNotNull('yield_per_hectare')->where('yield_per_hectare', '>', 0)
                ->get(['id', 'barangay', 'season', 'irrigation_type', 'yield_per_hectare', 'year', 'updated_at'])
            : collect(), collect(), false);
    }

    private function riceClass(RiceProduction $row): string
    {
        $this->riceClasses ??= rescue(fn (): array => RecordProvenance::riceClasses(), [], false);

        return $this->riceClasses[$row->getKey()] ?? RecordProvenance::UNVERIFIED;
    }

    /** The farm's latest finished estimate for this season, when the estimate may be shown. */
    private function estimate(User $user, Farm $farm, string $season, CarbonImmutable $target): ?array
    {
        if (! rescue(fn () => YieldModelGate::showsStoredEstimate(), false, false)
            || ! SchemaCache::hasColumn('yield_prediction_requests', 'farm_id')) {
            return null;
        }
        $request = rescue(fn () => YieldPredictionRequest::query()
            ->where('user_id', $user->getKey())->where('farm_id', $farm->getKey())
            ->where('season', $season)->where('status', YieldPredictionRequest::DONE)
            ->whereDate('target_month', $target->toDateString())
            ->whereNotNull('predicted_yield')
            ->latest('created_at')->first(), null, false);
        if ($request === null) {
            return null;
        }

        return [
            't_ha' => round((float) $request->predicted_yield, 2),
            'tons' => $request->total_tons !== null ? round((float) $request->total_tons, 1) : null,
            'source' => 'estimate',
            'count' => 1,
            'actual' => false,
            'updated_at' => ($request->completed_at ?? $request->updated_at) ? CarbonImmutable::instance($request->completed_at ?? $request->updated_at) : null,
        ];
    }

    // ---------------------------------------------------------------- insight

    /**
     * One or two plain lines from the stored figures only. Each is a translation key with its replacements.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{key: string, replace: array<string, string>}>
     */
    private function insight(string $season, array $rows, array $current): array
    {
        $past = array_values(array_filter($rows, fn (array $r): bool => $r['weather']['complete']));
        if (! $current['weather']['complete'] || count($past) < 3) {
            return [];
        }
        $rains = array_map(fn (array $r): float => (float) $r['weather']['rain'], $past);
        $usualRain = array_sum($rains) / count($rains);
        if ($usualRain <= 0) {
            return [];
        }
        $ratio = (float) $current['weather']['rain'] / $usualRain;
        $direction = $ratio < 1 - self::RAIN_BAND ? 'drier' : ($ratio > 1 + self::RAIN_BAND ? 'wetter' : 'usual');
        $count = (string) count($past);
        $lines = [['key' => 'cmp_insight_rain_'.$direction.'_'.strtolower($season), 'replace' => ['count' => $count]]];

        if ($direction === 'usual') {
            return $lines;
        }

        // Past seasons with both a full weather record and a yield, split at the median rain.
        $both = array_values(array_filter($past, fn (array $r): bool => $r['yield'] !== null));
        if (count($both) >= 3) {
            $sorted = $both;
            usort($sorted, fn ($a, $b) => $a['weather']['rain'] <=> $b['weather']['rain']);
            $mid = intdiv(count($sorted), 2);
            $dry = array_slice($sorted, 0, $mid);
            $wet = array_slice($sorted, count($sorted) - $mid);
            $mean = fn (array $set): float => array_sum(array_map(fn ($r) => $r['yield']['t_ha'], $set)) / count($set);
            $gap = $direction === 'drier' ? $mean($dry) - $mean($wet) : $mean($wet) - $mean($dry);
            if (abs($gap) >= self::YIELD_GAP) {
                $lines[] = ['key' => 'cmp_insight_yield_'.$direction.'_'.($gap < 0 ? 'lower' : 'higher'), 'replace' => ['value' => number_format(abs($gap), 1)]];

                return $lines;
            }
        }
        $lines[] = ['key' => 'cmp_insight_action_'.$direction, 'replace' => []];

        return $lines;
    }
}
