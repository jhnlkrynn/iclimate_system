<?php

namespace App\Services\Climate;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Loads and validates the real historical monthly climate dataset that
 * replaced the old contaminated `ClimateRecord`-based training source for
 * `MonthlyWeatherRandomForest` (see the 2026-09 audit: `ClimateRecord` only
 * had 6 rows — 5 synthetic factory-seeded demo rows plus one physically
 * impossible manually-entered row — which is unusable for real training).
 *
 * Source file: `database/data/Ambulong Monthly Data.csv`
 * Headers (exact, as shipped): YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION
 *
 * This class:
 *  - Locates and parses that CSV exactly once per file version (cached).
 *  - Validates the header row contains at least the required columns.
 *  - Excludes any row that is a sentinel/missing-data marker (PAGASA/climate
 *    convention: -999 in the core numeric fields means "no observation
 *    recorded", not a real reading of -999mm rainfall / -999 degrees). This
 *    is detected generically (any row whose RAINFALL, TMAX, or TMIN equals
 *    -999), not hardcoded to the one row (2020-01) known to exist today.
 *  - Derives monthly mean temperature as (TMAX + TMIN) / 2, since the file
 *    has no TEMP_AVG column. This is a standard, disclosed climatological
 *    approximation of monthly mean temperature from max/min records — not
 *    an arbitrary substitution of one extreme for the other.
 *  - Never throws for a missing/malformed/empty file — callers always get
 *    back a clear available/unavailable structure so the risk-map API and
 *    the weather forest degrade to "No Data" instead of a 500 or, worse,
 *    fabricated numbers.
 */
class AmbulongHistoricalClimateDataset
{
    /**
     * Columns that MUST be present (case-insensitively) for the file to be
     * considered structurally valid. RH and WIND_SPEED are carried through
     * when present but are not required for the dataset to load, since the
     * forest's core target (rainfall) and its temperature derivation only
     * depend on YEAR, MONTH, RAINFALL, TMAX, TMIN.
     */
    private const REQUIRED_COLUMNS = ['YEAR', 'MONTH', 'RAINFALL', 'TMAX', 'TMIN'];

    private const OPTIONAL_COLUMNS = ['RH', 'WIND_SPEED'];

    /**
     * The PAGASA/climate-data convention for "no observation recorded" in
     * this file. Any row where a core numeric field equals this value is
     * treated as missing, never as a real reading.
     */
    private const SENTINEL = -999.0;

    private const WET_SEASON_MONTHS = [5, 6, 7, 8, 9, 10];

    public function __construct(
        private readonly ?string $path = null,
    ) {}

    public function csvPath(): string
    {
        return $this->path ?? database_path('data/Ambulong Monthly Data.csv');
    }

    /**
     * A cheap version string (no parsing) used by callers — e.g.
     * MonthlyWeatherRandomForest's outer prediction cache key — so a future
     * CSV replacement invalidates dependent caches instead of being silently
     * ignored forever.
     */
    public function version(): string
    {
        $path = $this->csvPath();

        if (! is_file($path)) {
            return 'missing';
        }

        // Clear PHP's per-request stat cache for this path so a file that
        // changes mid-process (e.g. a long-running queue worker, or this
        // very test suite) is detected immediately rather than reusing a
        // stale filemtime()/filesize() from earlier in the same request.
        clearstatcache(true, $path);

        return implode(':', [(string) filemtime($path), (string) filesize($path)]);
    }

    /**
     * @return array{
     *     available: bool,
     *     message: ?string,
     *     observations: Collection<int, array{
     *         year: int, month: int, rainfall: float, temperature: float,
     *         humidity: ?float, wind_speed: ?float, season: string
     *     }>,
     *     excluded_sentinel_count: int,
     *     duplicate_count: int,
     * }
     */
    public function load(): array
    {
        $path = $this->csvPath();
        $cacheKey = 'ambulong-historical-climate-dataset:'.md5($path).':'.$this->version();

        return Cache::rememberForever($cacheKey, fn () => $this->parse($path));
    }

    private function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return $this->unavailable('Ambulong historical climate dataset file was not found.');
        }

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return $this->unavailable('Ambulong historical climate dataset file could not be opened.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === null) {
                return $this->unavailable('Ambulong historical climate dataset file is empty.');
            }

            $normalizedHeader = array_map(fn ($column) => strtoupper(trim((string) $column)), $header);
            $columnIndex = array_flip($normalizedHeader);

            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($columnIndex)));

            if ($missing !== []) {
                return $this->unavailable(
                    'Ambulong historical climate dataset is missing required column(s): '.implode(', ', $missing).'.'
                );
            }

            $hasHumidity = array_key_exists('RH', $columnIndex);
            $hasWindSpeed = array_key_exists('WIND_SPEED', $columnIndex);

            $observations = collect();
            $seen = [];
            $sentinelCount = 0;
            $duplicateCount = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === null || $row === [null] || $row === []) {
                    continue;
                }

                $year = $this->numeric($row, $columnIndex, 'YEAR');
                $month = $this->numeric($row, $columnIndex, 'MONTH');
                $rainfall = $this->numeric($row, $columnIndex, 'RAINFALL');
                $tmax = $this->numeric($row, $columnIndex, 'TMAX');
                $tmin = $this->numeric($row, $columnIndex, 'TMIN');

                if ($year === null || $month === null || $rainfall === null || $tmax === null || $tmin === null) {
                    // Malformed/non-numeric row — skip rather than fabricate.
                    continue;
                }

                // Generic sentinel detection: any core numeric field equal to
                // the -999 "missing" convention marks the whole month as
                // unobserved, not a real reading.
                if ($this->isSentinel($rainfall) || $this->isSentinel($tmax) || $this->isSentinel($tmin)) {
                    $sentinelCount++;

                    continue;
                }

                $humidity = $hasHumidity ? $this->numeric($row, $columnIndex, 'RH') : null;
                $windSpeed = $hasWindSpeed ? $this->numeric($row, $columnIndex, 'WIND_SPEED') : null;

                if ($humidity !== null && $this->isSentinel($humidity)) {
                    $humidity = null;
                }

                if ($windSpeed !== null && $this->isSentinel($windSpeed)) {
                    $windSpeed = null;
                }

                $year = (int) $year;
                $month = (int) $month;

                if ($month < 1 || $month > 12) {
                    continue;
                }

                $key = $year.'-'.$month;

                // Duplicate year/month handling: the real file has none (we
                // verified 132/132 unique combinations), but if a future
                // update introduces a duplicate we keep the FIRST occurrence
                // encountered (stable, deterministic) and drop later
                // duplicates rather than averaging or silently overwriting —
                // this is a deliberate, documented choice, not a crash.
                if (isset($seen[$key])) {
                    $duplicateCount++;

                    continue;
                }

                $seen[$key] = true;

                $observations->push([
                    'year' => $year,
                    'month' => $month,
                    // Monthly mean temperature = (TMAX + TMIN) / 2. No
                    // TEMP_AVG column exists in this file; this is the
                    // standard, disclosed climatological approximation of
                    // mean temperature from max/min records — not an
                    // arbitrary substitution of one extreme for the other.
                    'rainfall' => (float) $rainfall,
                    'temperature' => round(((float) $tmax + (float) $tmin) / 2, 4),
                    'humidity' => $humidity !== null ? (float) $humidity : null,
                    'wind_speed' => $windSpeed !== null ? (float) $windSpeed : null,
                    'season' => in_array($month, self::WET_SEASON_MONTHS, true) ? 'Wet' : 'Dry',
                ]);
            }

            $observations = $observations->sortBy(fn (array $o) => sprintf('%04d-%02d', $o['year'], $o['month']))->values();

            if ($observations->isEmpty()) {
                return $this->unavailable('Ambulong historical climate dataset contained no usable monthly observations.');
            }

            return [
                'available' => true,
                'message' => null,
                'observations' => $observations,
                'excluded_sentinel_count' => $sentinelCount,
                'duplicate_count' => $duplicateCount,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function isSentinel(float $value): bool
    {
        return abs($value - self::SENTINEL) < 0.0001;
    }

    private function numeric(array $row, array $columnIndex, string $column): ?float
    {
        $index = $columnIndex[$column] ?? null;

        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $raw = trim((string) $row[$index]);

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function unavailable(string $message): array
    {
        return [
            'available' => false,
            'message' => $message,
            'observations' => collect(),
            'excluded_sentinel_count' => 0,
            'duplicate_count' => 0,
        ];
    }
}
