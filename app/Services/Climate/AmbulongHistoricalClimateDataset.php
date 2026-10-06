<?php

namespace App\Services\Climate;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Loads and validates the Ambulong historical monthly climate dataset.
 *
 * Source:
 * database/data/Ambulong Monthly Data.csv
 *
 * Expected columns:
 * YEAR
 * MONTH
 * RAINFALL
 * TMAX
 * TMIN
 * RH
 * WIND_SPEED
 * WIND_DIRECTION
 *
 * The loader preserves all weather variables required by the
 * candidate rice-yield Random Forest model.
 */
class AmbulongHistoricalClimateDataset
{
    /**
     * All of these columns are required because the downstream
     * weather model and rice-yield model need them.
     */
    private const REQUIRED_COLUMNS = [
        'YEAR',
        'MONTH',
        'RAINFALL',
        'TMAX',
        'TMIN',
        'RH',
        'WIND_SPEED',
        'WIND_DIRECTION',
    ];

    /**
     * PAGASA/climate-data missing-value marker.
     */
    private const SENTINEL = -999.0;

    /**
     * Must match the candidate rice-yield model preprocessing.
     *
     * The iClimate season calendar (App\Support\RiceSeason):
     * wet May to October, dry November to April.
     */
    private const WET_SEASON_MONTHS = \App\Support\RiceSeason::WET_MONTHS;

    public function __construct(
        private readonly ?string $path = null,
    ) {
    }

    /**
     * Return absolute path to Ambulong CSV.
     */
    public function csvPath(): string
    {
        return $this->path
            ?? database_path('data/Ambulong Monthly Data.csv');
    }

    /**
     * File version used for cache invalidation.
     */
    public function version(): string
    {
        $path = $this->csvPath();

        if (! is_file($path)) {
            return 'missing';
        }

        clearstatcache(
            true,
            $path
        );

        return implode(
            ':',
            [
                (string) filemtime($path),
                (string) filesize($path),
            ]
        );
    }

    /**
     * Load parsed Ambulong observations.
     *
     * @return array{
     *     available: bool,
     *     message: ?string,
     *     observations: Collection<int, array{
     *         year: int,
     *         month: int,
     *         rainfall: float,
     *         tmax: float,
     *         tmin: float,
     *         temperature: float,
     *         humidity: float,
     *         wind_speed: float,
     *         wind_direction: float,
     *         season: string
     *     }>,
     *     excluded_sentinel_count: int,
     *     invalid_row_count: int,
     *     duplicate_count: int
     * }
     */
    public function load(): array
    {
        $path = $this->csvPath();

        $cacheKey =
            'ambulong-historical-climate-dataset:'
            .md5($path)
            .':'
            .$this->version();

        return Cache::rememberForever(
            $cacheKey,
            fn () => $this->parse($path)
        );
    }

    /**
     * Parse Ambulong CSV.
     */
    private function parse(string $path): array
    {
        if (
            ! is_file($path)
            ||
            ! is_readable($path)
        ) {
            return $this->unavailable(
                'Ambulong historical climate dataset file was not found.'
            );
        }

        $handle = @fopen(
            $path,
            'r'
        );

        if ($handle === false) {
            return $this->unavailable(
                'Ambulong historical climate dataset file could not be opened.'
            );
        }

        try {
            $header = fgetcsv(
                $handle
            );

            if (
                $header === false
                ||
                $header === null
            ) {
                return $this->unavailable(
                    'Ambulong historical climate dataset file is empty.'
                );
            }

            /**
             * Normalize headers.
             */
            $normalizedHeader = array_map(
                fn ($column) =>
                    strtoupper(
                        trim(
                            (string) $column
                        )
                    ),
                $header
            );

            $columnIndex = array_flip(
                $normalizedHeader
            );

            /**
             * Validate required columns.
             */
            $missing = array_values(
                array_diff(
                    self::REQUIRED_COLUMNS,
                    array_keys(
                        $columnIndex
                    )
                )
            );

            if ($missing !== []) {
                return $this->unavailable(
                    'Ambulong historical climate dataset is missing required column(s): '
                    .implode(', ', $missing)
                    .'.'
                );
            }

            $observations = collect();

            $seen = [];

            $sentinelCount = 0;

            $invalidRowCount = 0;

            $duplicateCount = 0;

            /**
             * Process rows.
             */
            while (
                ($row = fgetcsv($handle))
                !== false
            ) {
                if (
                    $row === null
                    ||
                    $row === [null]
                    ||
                    $row === []
                ) {
                    continue;
                }

                $year = $this->numeric(
                    $row,
                    $columnIndex,
                    'YEAR'
                );

                $month = $this->numeric(
                    $row,
                    $columnIndex,
                    'MONTH'
                );

                $rainfall = $this->numeric(
                    $row,
                    $columnIndex,
                    'RAINFALL'
                );

                $tmax = $this->numeric(
                    $row,
                    $columnIndex,
                    'TMAX'
                );

                $tmin = $this->numeric(
                    $row,
                    $columnIndex,
                    'TMIN'
                );

                $humidity = $this->numeric(
                    $row,
                    $columnIndex,
                    'RH'
                );

                $windSpeed = $this->numeric(
                    $row,
                    $columnIndex,
                    'WIND_SPEED'
                );

                $windDirection = $this->numeric(
                    $row,
                    $columnIndex,
                    'WIND_DIRECTION'
                );

                /**
                 * All model-required fields must exist.
                 */
                if (
                    $year === null
                    ||
                    $month === null
                    ||
                    $rainfall === null
                    ||
                    $tmax === null
                    ||
                    $tmin === null
                    ||
                    $humidity === null
                    ||
                    $windSpeed === null
                    ||
                    $windDirection === null
                ) {
                    $invalidRowCount++;

                    continue;
                }

                /**
                 * Exclude any row containing sentinel values.
                 */
                if (
                    $this->isSentinel($rainfall)
                    ||
                    $this->isSentinel($tmax)
                    ||
                    $this->isSentinel($tmin)
                    ||
                    $this->isSentinel($humidity)
                    ||
                    $this->isSentinel($windSpeed)
                    ||
                    $this->isSentinel($windDirection)
                ) {
                    $sentinelCount++;

                    continue;
                }

                $year = (int) $year;

                $month = (int) $month;

                /**
                 * Validate month.
                 */
                if (
                    $month < 1
                    ||
                    $month > 12
                ) {
                    $invalidRowCount++;

                    continue;
                }

                /**
                 * Basic physical validation.
                 */
                if ($rainfall < 0) {
                    $invalidRowCount++;

                    continue;
                }

                if (
                    $humidity < 0
                    ||
                    $humidity > 100
                ) {
                    $invalidRowCount++;

                    continue;
                }

                if ($windSpeed < 0) {
                    $invalidRowCount++;

                    continue;
                }

                if (
                    $windDirection < 0
                    ||
                    $windDirection > 360
                ) {
                    $invalidRowCount++;

                    continue;
                }

                if ($tmax < $tmin) {
                    $invalidRowCount++;

                    continue;
                }

                /**
                 * Prevent duplicate year/month observations.
                 */
                $key =
                    $year
                    .'-'
                    .$month;

                if (isset($seen[$key])) {
                    $duplicateCount++;

                    continue;
                }

                $seen[$key] = true;

                /**
                 * Mean temperature is retained for compatibility
                 * with existing weather UI/features.
                 */
                $temperature = round(
                    (
                        (float) $tmax
                        +
                        (float) $tmin
                    ) / 2,
                    4
                );

                /**
                 * Preserve ALL weather values.
                 */
                $observations->push([
                    'year' =>
                        $year,

                    'month' =>
                        $month,

                    'rainfall' =>
                        (float) $rainfall,

                    'tmax' =>
                        (float) $tmax,

                    'tmin' =>
                        (float) $tmin,

                    'temperature' =>
                        $temperature,

                    'humidity' =>
                        (float) $humidity,

                    'wind_speed' =>
                        (float) $windSpeed,

                    'wind_direction' =>
                        (float) $windDirection,

                    'season' =>
                        in_array(
                            $month,
                            self::WET_SEASON_MONTHS,
                            true
                        )
                            ? 'Wet'
                            : 'Dry',
                ]);
            }

            /**
             * Sort chronologically.
             */
            $observations = $observations
                ->sortBy(
                    fn (array $observation) =>
                        sprintf(
                            '%04d-%02d',
                            $observation['year'],
                            $observation['month']
                        )
                )
                ->values();

            if ($observations->isEmpty()) {
                return $this->unavailable(
                    'Ambulong historical climate dataset contained no usable monthly observations.'
                );
            }

            return [
                'available' =>
                    true,

                'message' =>
                    null,

                'observations' =>
                    $observations,

                'excluded_sentinel_count' =>
                    $sentinelCount,

                'invalid_row_count' =>
                    $invalidRowCount,

                'duplicate_count' =>
                    $duplicateCount,
            ];
        } finally {
            fclose(
                $handle
            );
        }
    }

    /**
     * Check -999 missing-data marker.
     */
    private function isSentinel(float $value): bool
    {
        return abs(
            $value
            - self::SENTINEL
        ) < 0.0001;
    }

    /**
     * Safely obtain numeric CSV value.
     */
    private function numeric(
        array $row,
        array $columnIndex,
        string $column
    ): ?float {
        $index =
            $columnIndex[$column]
            ?? null;

        if (
            $index === null
            ||
            ! array_key_exists(
                $index,
                $row
            )
        ) {
            return null;
        }

        $raw = trim(
            (string) $row[$index]
        );

        if (
            $raw === ''
            ||
            ! is_numeric($raw)
        ) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * Standard unavailable result.
     */
    private function unavailable(
        string $message
    ): array {
        return [
            'available' =>
                false,

            'message' =>
                $message,

            'observations' =>
                collect(),

            'excluded_sentinel_count' =>
                0,

            'invalid_row_count' =>
                0,

            'duplicate_count' =>
                0,
        ];
    }
}