<?php

namespace App\Services\Risk;

use App\Services\Analytics\MaoDashboardService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The Heat Map's rating (2026-10): each barangay's farm risk for [This Week] (days 1–7) or [2 Weeks] (days 1–14),
 * day 1 being today, worked out from the same saved daily forecast the Predictions page shows
 * (LianBarangayWeatherService's stored `daily` list). Reads saved data only: never calls a provider.
 *
 * The rule looks at four weather hazards over the period and takes the worst one:
 *   heavy rain   a day with HEAVY_RAIN_MM or more: 1–2 such days = Moderate; 3 or more, or one day with
 *                VERY_HEAVY_RAIN_MM or more = High
 *   strong wind  the strongest day's wind: STRONG_WIND_KMH or more = Moderate; STORM_WIND_KMH or more = High
 *   heat         days reaching HOT_DAY_C: 1–2 = Moderate; 3 or more = High
 *   no rain      (rainfed barangays only) no day with RAIN_DAY_MM or more in the whole period = Moderate
 * No hazard = Low. A barangay with no saved forecast days is "nodata", never Low.
 *
 * A forecast that could not be refreshed stays in use for its days that have not passed yet, with its own time;
 * the period is marked "delayed" once the oldest one is DELAYED_AFTER_HOURS old.
 *
 * These limits are working defaults and must be checked by the Municipal Agriculture Office before they are treated
 * as official advice (the same caveat as config/farmer_dashboard.php).
 */
class BarangayWeekRiskService
{
    public const TIMEZONE = 'Asia/Manila';

    public const RANGES = ['week' => 7, '2weeks' => 14];

    public const HEAVY_RAIN_MM = MaoDashboardService::HEAVY_RAIN_MM;

    public const VERY_HEAVY_RAIN_MM = 50.0;

    public const STRONG_WIND_KMH = MaoDashboardService::STRONG_WIND_KMH;

    /** Tropical-storm strength wind (PAGASA: 62 km/h and above). */
    public const STORM_WIND_KMH = 62.0;

    public const HOT_DAY_C = 35.0;

    public const RAIN_DAY_MM = 1.0;

    /** A saved forecast older than this is behind the provider's model runs and is shown as delayed. */
    public const DELAYED_AFTER_HOURS = 6;

    /** A dry spell is only called when most of the period is covered by the forecast. */
    private const MIN_DAYS_FOR_DRY = 5;

    private const RANK = ['low' => 1, 'moderate' => 2, 'high' => 3];

    public function __construct(
        private readonly LianBarangayWeatherService $weather,
        private readonly BarangayRiskMapService $map,
    ) {}

    /**
     * All 19 barangays for one range.
     *
     * @return array{range: string, days: int, state: string, updated_at: ?CarbonImmutable, delayed: bool, counts: array<string, int>, barangays: array<string, array<string, mixed>>}
     */
    public function forRange(string $range): array
    {
        $range = isset(self::RANGES[$range]) ? $range : 'week';
        $records = rescue(fn (): array => $this->weather->savedForecasts(), [], false);
        $farmTypes = rescue(fn (): array => $this->map->dominantFarmTypes(), [], false);

        return $this->buildRange($range, $records, $farmTypes);
    }

    /**
     * All ranges from one saved forecast and farm-type snapshot. The Heat Map page displays both ranges together;
     * sharing these inputs avoids repeating the same cache read and database aggregate.
     *
     * @return array<string, array<string, mixed>>
     */
    public function ranges(): array
    {
        $records = rescue(fn (): array => $this->weather->savedForecasts(), [], false);
        $farmTypes = rescue(fn (): array => $this->map->dominantFarmTypes(), [], false);

        return collect(array_keys(self::RANGES))
            ->mapWithKeys(fn (string $range): array => [$range => $this->buildRange($range, $records, $farmTypes)])
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $records
     * @param  array<string, string>  $farmTypes
     * @return array<string, mixed>
     */
    private function buildRange(string $range, array $records, array $farmTypes): array
    {
        $days = self::RANGES[$range];
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $barangays = [];
        $updated = null;
        foreach (LianBarangays::all() as $name) {
            $record = is_array($records[$name] ?? null) ? $records[$name] : [];
            $rated = self::rate($this->daysInRange((array) ($record['daily'] ?? []), $today, $days), (string) ($farmTypes[$name] ?? 'Rainfed'));
            $barangays[$name] = ['barangay' => $name] + $rated;

            $fetched = $rated['level'] !== 'nodata' && ! empty($record['fetched_at']) ? rescue(fn () => CarbonImmutable::parse($record['fetched_at'])->timezone(self::TIMEZONE), null, false) : null;
            if ($fetched && ($updated === null || $fetched->lessThan($updated))) {
                $updated = $fetched;   // the oldest reading on the map, so "Updated" never overstates freshness
            }
        }

        $counts = ['high' => 0, 'moderate' => 0, 'low' => 0, 'nodata' => 0];
        foreach ($barangays as $b) {
            $counts[$b['level']]++;
        }

        return [
            'range' => $range,
            'days' => $days,
            'state' => $counts['nodata'] === count($barangays) ? 'nodata' : 'ok',
            'updated_at' => $updated,
            'delayed' => $updated !== null && $updated->lessThan(CarbonImmutable::now()->subHours(self::DELAYED_AFTER_HOURS)),
            'counts' => $counts,
            'barangays' => $barangays,
        ];
    }

    /**
     * One barangay for one range; null when the name is not one of Lian's 19 barangays.
     *
     * @return array<string, mixed>|null
     */
    public function forBarangay(?string $barangay, string $range = 'week'): ?array
    {
        $name = LianBarangays::canonicalName($barangay);
        if ($name === null) {
            return null;
        }
        try {
            $all = $this->forRange($range);
        } catch (Throwable) {
            return null;
        }

        return ($all['barangays'][$name] ?? null) ? $all['barangays'][$name] + ['updated_at' => $all['updated_at']] : null;
    }

    /**
     * The rule, on a list of forecast days (each: rainfall, wind_speed_max, temperature_max).
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array{level: string, reason: string, reasons: array<int, array{key: string, level: string, dates: array<int, string>}>, days_covered: int, rain_total_mm: ?float, wettest_day_mm: ?float, heavy_rain_days: int, wind_max_kmh: ?float, temp_max_c: ?float, hot_days: int, farm_type: string}
     */
    public static function rate(array $days, string $farmType = 'Rainfed'): array
    {
        $number = fn (array $day, string $key): ?float => is_numeric($day[$key] ?? null) ? (float) $day[$key] : null;
        $rain = array_values(array_filter(array_map(fn (array $d) => $number($d, 'rainfall'), $days), fn ($v) => $v !== null));
        $wind = array_values(array_filter(array_map(fn (array $d) => $number($d, 'wind_speed_max'), $days), fn ($v) => $v !== null));
        $temp = array_values(array_filter(array_map(fn (array $d) => $number($d, 'temperature_max'), $days), fn ($v) => $v !== null));

        $facts = [
            'days_covered' => count($days),
            'rain_total_mm' => $rain === [] ? null : round(array_sum($rain), 1),
            'wettest_day_mm' => $rain === [] ? null : round(max($rain), 1),
            'heavy_rain_days' => count(array_filter($rain, fn (float $v): bool => $v >= self::HEAVY_RAIN_MM)),
            'wind_max_kmh' => $wind === [] ? null : round(max($wind), 1),
            'temp_max_c' => $temp === [] ? null : round(max($temp), 1),
            'hot_days' => count(array_filter($temp, fn (float $v): bool => $v >= self::HOT_DAY_C)),
            'farm_type' => strtolower($farmType) === 'irrigated' ? 'Irrigated' : 'Rainfed',
        ];
        if ($rain === [] && $wind === [] && $temp === []) {
            return ['level' => 'nodata', 'reason' => 'nodata', 'reasons' => []] + $facts;
        }

        // The dates (Y-m-d) on which a measurement reaches a limit, so the panel can name the days.
        $dates = fn (string $key, float $limit): array => array_values(array_map(
            fn (array $d): string => (string) ($d['date'] ?? ''),
            array_filter($days, fn (array $d): bool => $number($d, $key) !== null && $number($d, $key) >= $limit),
        ));

        $reasons = [];
        if ($facts['heavy_rain_days'] > 0) {
            $reasons[] = ['key' => 'heavy_rain', 'level' => $facts['heavy_rain_days'] >= 3 || $facts['wettest_day_mm'] >= self::VERY_HEAVY_RAIN_MM ? 'high' : 'moderate', 'dates' => $dates('rainfall', self::HEAVY_RAIN_MM)];
        }
        if ($facts['wind_max_kmh'] !== null && $facts['wind_max_kmh'] >= self::STRONG_WIND_KMH) {
            $reasons[] = ['key' => 'strong_wind', 'level' => $facts['wind_max_kmh'] >= self::STORM_WIND_KMH ? 'high' : 'moderate', 'dates' => $dates('wind_speed_max', self::STRONG_WIND_KMH)];
        }
        if ($facts['hot_days'] > 0) {
            $reasons[] = ['key' => 'heat', 'level' => $facts['hot_days'] >= 3 ? 'high' : 'moderate', 'dates' => $dates('temperature_max', self::HOT_DAY_C)];
        }
        $rainDays = count(array_filter($rain, fn (float $v): bool => $v >= self::RAIN_DAY_MM));
        if ($facts['farm_type'] === 'Rainfed' && count($rain) >= self::MIN_DAYS_FOR_DRY && $rainDays === 0) {
            $reasons[] = ['key' => 'dry', 'level' => 'moderate', 'dates' => []];
        }

        // The worst hazard first; hazards of the same level keep the order above (rain, wind, heat, no rain).
        usort($reasons, fn (array $a, array $b): int => self::RANK[$b['level']] <=> self::RANK[$a['level']]);

        return [
            'level' => $reasons[0]['level'] ?? 'low',
            'reason' => $reasons[0]['key'] ?? 'calm',
            'reasons' => $reasons,
        ] + $facts;
    }

    /**
     * The saved forecast days that fall inside the range (today … today + days − 1).
     *
     * @param  array<int, mixed>  $daily
     * @return array<int, array<string, mixed>>
     */
    private function daysInRange(array $daily, CarbonImmutable $today, int $days): array
    {
        $first = $today->toDateString();
        $last = $today->addDays($days - 1)->toDateString();

        return array_values(array_filter($daily, fn ($day): bool => is_array($day) && is_string($day['date'] ?? null) && $day['date'] >= $first && $day['date'] <= $last));
    }
}
