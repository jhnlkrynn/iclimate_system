<?php

namespace App\Services\Climate;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Monthly weather from the Open-Meteo Historical Weather API (ERA5 reanalysis) at the PAGASA Ambulong station's
 * coordinates, the station the deployed rice-yield model was trained on (owner decision 2026-11-20).
 *
 * It is the LAST source for the model's observed-month inputs (App\Support\YieldModelClimateWindow): an entered and
 * verified MAO Climate Record or an Ambulong station month always takes its place. It is never written as a Climate
 * Record. Against the station in 2024-2025 it reads about 0.6 degC cooler on (TMAX+TMIN)/2 and about 5 points more humid,
 * and single months of rainfall can differ a lot, so a prediction that uses it says so (reanalysis_months).
 *
 * Only complete months are kept: every day present with every reading, and the month fully past. A month is built the
 * way the station file is: rainfall = sum of daily precipitation (mm), temperature = (mean TMAX + mean TMIN) / 2 (degC),
 * humidity = mean daily relative humidity (%), wind_speed = mean daily 10 m wind (m/s).
 */
final class OpenMeteoArchiveMonthly
{
    public const SOURCE = 'Open-Meteo Historical Weather (ERA5) at Ambulong';

    private const DAILY = ['precipitation_sum', 'temperature_2m_max', 'temperature_2m_min', 'relative_humidity_2m_mean', 'wind_speed_10m_mean'];

    /** Overlapping station years a calendar month needs before its archive values are put on the station scale. */
    public const MIN_CALIBRATION_YEARS = 5;

    /** Months the archive is fetched from for calibration: the start of the station record. */
    public const CALIBRATION_FROM = '2015-01';

    /** @var array{mtime: int|false, path: string, months: array<string, object>}|null */
    private static ?array $loaded = null;

    /** @var array{memo: string, months: array<string, object>}|null */
    private static ?array $calibrated = null;

    public static function path(): string
    {
        $path = (string) config('services.open_meteo.archive_file');
        if ($path === '') {
            return storage_path('app/climate/open-meteo-archive-monthly.json');
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * Fetch the months from $from to $to (inclusive) and return the complete ones keyed Y-m.
     *
     * @return array<string, array{rainfall: float, tmax: float, tmin: float, temperature: float, humidity: float, wind_speed: float, days: int}>
     */
    public function fetch(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = $from->startOfMonth();
        // Only whole months that are over; the archive also lags a few days, which the completeness check catches.
        $end = $to->endOfMonth()->startOfDay();
        if ($end->gte($today)) {
            $end = $today->subDay();
        }
        if ($end->lt($start)) {
            return [];
        }

        $response = Http::timeout((int) config('services.open_meteo.archive_timeout', 30))
            ->retry(2, 1000, throw: false)
            ->get((string) config('services.open_meteo.archive_url', 'https://archive-api.open-meteo.com/v1/archive'), [
                'latitude' => config('services.open_meteo.archive_latitude', 14.0886),
                'longitude' => config('services.open_meteo.archive_longitude', 121.0553),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'daily' => implode(',', self::DAILY),
                'wind_speed_unit' => 'ms',
                'timezone' => $timezone,
            ]);
        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo archive request failed: HTTP '.$response->status());
        }
        $daily = $response->json('daily');
        if (! is_array($daily) || ! is_array($daily['time'] ?? null)) {
            throw new RuntimeException('Open-Meteo archive returned no daily data.');
        }

        $byMonth = [];
        foreach ($daily['time'] as $i => $date) {
            $key = substr((string) $date, 0, 7);
            $values = [];
            foreach (self::DAILY as $field) {
                $v = $daily[$field][$i] ?? null;
                $values[$field] = is_numeric($v) ? (float) $v : null;
            }
            $byMonth[$key][(string) $date] = $values;
        }

        $months = [];
        foreach ($byMonth as $key => $days) {
            $first = CarbonImmutable::parse($key.'-01', $timezone);
            $complete = count($days) === $first->daysInMonth && $first->endOfMonth()->lt($today)
                && collect($days)->every(fn (array $d): bool => ! in_array(null, $d, true));
            if (! $complete) {
                continue;
            }
            $col = fn (string $f): array => array_column($days, $f);
            $tmax = array_sum($col('temperature_2m_max')) / count($days);
            $tmin = array_sum($col('temperature_2m_min')) / count($days);
            $months[$key] = [
                'rainfall' => round(array_sum($col('precipitation_sum')), 2),
                'tmax' => round($tmax, 2),
                'tmin' => round($tmin, 2),
                'temperature' => round(($tmax + $tmin) / 2, 2),
                'humidity' => round(array_sum($col('relative_humidity_2m_mean')) / count($days), 2),
                'wind_speed' => round(array_sum($col('wind_speed_10m_mean')) / count($days), 2),
                'days' => count($days),
            ];
        }
        ksort($months);

        return $months;
    }

    /**
     * Merge fetched months into the stored file (a later fetch replaces the same month) and return how many are stored.
     *
     * @param  array<string, array<string, float|int>>  $months
     */
    public function store(array $months): int
    {
        $path = self::path();
        $existing = is_file($path) ? (json_decode((string) file_get_contents($path), true)['months'] ?? []) : [];
        $all = array_merge(is_array($existing) ? $existing : [], $months);
        ksort($all);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $payload = [
            'source' => self::SOURCE,
            'source_url' => 'https://open-meteo.com/en/docs/historical-weather-api',
            'latitude' => (float) config('services.open_meteo.archive_latitude', 14.0886),
            'longitude' => (float) config('services.open_meteo.archive_longitude', 121.0553),
            'fetched_at' => CarbonImmutable::now()->toIso8601String(),
            'months' => $all,
        ];
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
        self::$loaded = null;

        return count($all);
    }

    /**
     * Stored months keyed Y-m, shaped like ClimateHistory rows. Empty when nothing has been fetched.
     *
     * @return array<string, object>
     */
    public static function months(): array
    {
        $path = self::path();
        $mtime = is_file($path) ? filemtime($path) : false;
        if (self::$loaded !== null && self::$loaded['path'] === $path && self::$loaded['mtime'] === $mtime) {
            return self::$loaded['months'];
        }
        $months = [];
        if ($mtime !== false) {
            try {
                foreach ((array) (json_decode((string) file_get_contents($path), true)['months'] ?? []) as $key => $m) {
                    if (! preg_match('/^\d{4}-\d{2}$/', (string) $key) || ! is_numeric($m['rainfall'] ?? null) || ! is_numeric($m['temperature'] ?? null)) {
                        continue;
                    }
                    $months[$key] = (object) [
                        'record_date' => $key.'-01',
                        'rainfall' => (float) $m['rainfall'],
                        'temperature' => (float) $m['temperature'],
                        'humidity' => is_numeric($m['humidity'] ?? null) ? (float) $m['humidity'] : null,
                        'wind_speed' => is_numeric($m['wind_speed'] ?? null) ? (float) $m['wind_speed'] : null,
                        'source' => self::SOURCE,
                        'reanalysis' => true,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
                $months = [];
            }
        }
        self::$loaded = ['path' => $path, 'mtime' => $mtime, 'months' => $months];

        return $months;
    }

    /**
     * The stored months put on the Ambulong station's scale, which is what the model was trained on. Per calendar month,
     * from the months both have (2015-2025): temperature, humidity and wind get the mean station-minus-archive
     * difference added, rainfall is multiplied by station total / archive total. Leave-one-year-out 2015-2025 this
     * cut the mean-temperature error 0.45 -> 0.24 degC, monthly rain 62 -> 55 mm and Rainfall_6M 35 -> 29 mm.
     * A calendar month with fewer than MIN_CALIBRATION_YEARS overlapping years is not used: it stays missing rather than
     * reaching the model on the wrong scale. Raw archive values are kept on each row (*_archive).
     *
     * @param  array<string, object>  $station  station months keyed Y-m (rainfall, temperature, humidity, wind_speed)
     * @return array<string, object>
     */
    public static function calibrated(array $station): array
    {
        $archive = self::months();
        $memo = md5(json_encode([array_keys($archive), self::$loaded['mtime'] ?? null, array_keys($station)]));
        if ((self::$calibrated['memo'] ?? null) === $memo) {
            return self::$calibrated['months'];
        }

        $pairs = [];
        foreach ($archive as $key => $a) {
            $s = $station[$key] ?? null;
            if ($s !== null && is_numeric($s->rainfall ?? null) && is_numeric($s->temperature ?? null)) {
                $pairs[(int) substr($key, 5, 2)][] = [$s, $a];
            }
        }
        $factors = [];
        foreach ($pairs as $month => $list) {
            if (count($list) < self::MIN_CALIBRATION_YEARS) {
                continue;
            }
            $diff = function (string $f) use ($list): float {
                $d = array_values(array_filter(array_map(fn (array $p): ?float => is_numeric($p[0]->{$f} ?? null) && is_numeric($p[1]->{$f} ?? null) ? (float) $p[0]->{$f} - (float) $p[1]->{$f} : null, $list), fn ($v) => $v !== null));

                return $d === [] ? 0.0 : array_sum($d) / count($d);
            };
            $archiveRain = array_sum(array_map(fn (array $p): float => (float) $p[1]->rainfall, $list));
            $factors[$month] = [
                'rain' => $archiveRain > 0 ? array_sum(array_map(fn (array $p): float => (float) $p[0]->rainfall, $list)) / $archiveRain : 1.0,
                'temperature' => $diff('temperature'),
                'humidity' => $diff('humidity'),
                'wind_speed' => $diff('wind_speed'),
                'years' => count($list),
            ];
        }

        $out = [];
        foreach ($archive as $key => $a) {
            $f = $factors[(int) substr($key, 5, 2)] ?? null;
            if ($f === null) {
                continue;
            }
            $out[$key] = (object) [
                'record_date' => $a->record_date,
                'rainfall' => round($a->rainfall * $f['rain'], 2),
                'temperature' => round($a->temperature + $f['temperature'], 2),
                'humidity' => $a->humidity === null ? null : round(min(100.0, $a->humidity + $f['humidity']), 2),
                'wind_speed' => $a->wind_speed === null ? null : round(max(0.0, $a->wind_speed + $f['wind_speed']), 2),
                'rainfall_archive' => $a->rainfall,
                'temperature_archive' => $a->temperature,
                'calibration_years' => $f['years'],
                'source' => self::SOURCE.', calibrated to the station',
                'reanalysis' => true,
            ];
        }
        self::$calibrated = ['memo' => $memo, 'months' => $out];

        return $out;
    }

    public static function forget(): void
    {
        self::$loaded = null;
        self::$calibrated = null;
    }
}
