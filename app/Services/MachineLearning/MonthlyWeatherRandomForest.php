<?php

namespace App\Services\MachineLearning;

use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Improved Ambulong Monthly Weather Random Forest.
 *
 * Improvements:
 * - Strict training cutoff / no target leakage
 * - Previous-month context
 * - 3-month rolling context
 * - 6-month rolling context
 * - Same month last year
 * - Same month two years ago
 * - Historical climatology for the same calendar month
 * - Circular wind-direction encoding
 *
 * Output:
 * - rainfall
 * - tmax
 * - tmin
 * - humidity
 * - wind_speed
 * - wind_direction
 * - temperature
 * - season
 */
class MonthlyWeatherRandomForest
{
    /**
     * Formal version marker for this model's trained output — matches the
     * "v5" already embedded in the cache key below and in source_name.
     * Persisted alongside every stored prediction (PredictionOrchestrator /
     * monthly_weather_predictions.weather_model) so a future retrain that
     * changes this string automatically produces new rows/cache keys
     * instead of silently being served as if it were the old model's output.
     */
    public const MODEL_VERSION = 'monthly_weather_rf_v5';

    private const TARGETS = [
        'rainfall',
        'tmax',
        'tmin',
        'humidity',
        'wind_speed',
        'wind_direction_sin',
        'wind_direction_cos',
    ];

    /**
     * Must remain consistent with the candidate
     * rice-yield Random Forest preprocessing.
     */
    private const WET_SEASON_MONTHS = [
        5,
        6,
        7,
        8,
        9,
    ];

    public function __construct(
        private readonly AmbulongHistoricalClimateDataset $dataset =
            new AmbulongHistoricalClimateDataset,
    ) {
    }

    /**
     * Exposes the underlying historical dataset's cheap version signature
     * (file mtime/size, not a re-parse) so callers that persist predictions
     * outside this class's own 15-minute cache — e.g. PredictionOrchestrator
     * — can detect "the source CSV changed since this row was generated"
     * without needing their own copy of AmbulongHistoricalClimateDataset.
     */
    public function datasetVersion(): string
    {
        return $this->dataset->version();
    }

    /**
     * Predict monthly weather.
     *
     * If $trainingCutoff is not provided:
     * use observations only BEFORE the target month.
     *
     * If explicitly provided:
     * use observations up to that cutoff.
     */
    public function predict(
        CarbonImmutable $targetMonth,
        ?CarbonImmutable $trainingCutoff = null
    ): array {
        $targetMonth = $targetMonth->startOfMonth();

        $effectiveCutoff = $trainingCutoff !== null
            ? $trainingCutoff->endOfMonth()
            : $targetMonth->subMonth()->endOfMonth();

        $cacheKey = implode(':', [
            'weather-random-forest-v5',
            $targetMonth->format('Y-m'),
            'cutoff',
            $effectiveCutoff->format('Y-m-d'),
            $this->dataset->version(),
        ]);

        return Cache::remember(
            $cacheKey,
            // The key holds everything training depends on (target month, cutoff, dataset version),
            // so the result cannot go stale; retraining the forest takes ~20 seconds, so keep it a day.
            now()->addHours(max(1, (int) config('ai.forest_cache_hours', 24))),
            fn () => $this->buildPrediction(
                $targetMonth,
                $effectiveCutoff
            )
        );
    }

    private function buildPrediction(
        CarbonImmutable $targetMonth,
        CarbonImmutable $trainingCutoff
    ): array {
        $dataset = $this->dataset->load();

        if (! $dataset['available']) {
            return $this->notReady(
                $targetMonth,
                $trainingCutoff,
                0,
                $dataset['message']
                    ?? 'The Ambulong historical climate dataset is unavailable.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Strict Training Window
        |--------------------------------------------------------------------------
        */

        $trainingObservations = $dataset['observations']
            ->filter(
                function (array $observation) use ($trainingCutoff): bool {
                    $observationDate =
                        CarbonImmutable::createFromDate(
                            (int) $observation['year'],
                            (int) $observation['month'],
                            1
                        )->endOfMonth();

                    return $observationDate->lessThanOrEqualTo(
                        $trainingCutoff
                    );
                }
            )
            ->values();

        $monthly = $this->monthlyClimateRecords(
            $trainingObservations
        );

        if ($monthly->count() < 12) {
            return $this->notReady(
                $targetMonth,
                $trainingCutoff,
                $monthly->count(),
                'At least 12 usable historical monthly observations are required.'
            );
        }

        [$samples, $context] =
            $this->buildTrainingSamples(
                $monthly
            );

        if (count($samples) < 6) {
            return $this->notReady(
                $targetMonth,
                $trainingCutoff,
                $monthly->count(),
                'Not enough usable training samples were generated.'
            );
        }

        $features = $this->featuresForTargetMonth(
            $targetMonth,
            $context
        );

        $rawPredictions = [];

        $training = [];

        /*
        |--------------------------------------------------------------------------
        | Train Independent Forest for Every Weather Target
        |--------------------------------------------------------------------------
        */

        foreach (self::TARGETS as $target) {
            $forest = new RandomForestRegressor(
                treeCount: 100,
                maxDepth: 8,
                minSamplesSplit: 3,
                seed: crc32(
                    'iclimate-'.$target
                )
            );

            $forest->train(
                $samples,
                $target
            );

            $rawPredictions[$target] =
                $forest->predict(
                    $features
                );

            $training[$target] = [
                'trees' =>
                    $forest->treeCount(),

                'samples' =>
                    count($samples),

                'features' =>
                    count($features),

                'stability' =>
                    $this->targetStability(
                        $samples,
                        $target
                    ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Convert Circular Wind Components Back to Degrees
        |--------------------------------------------------------------------------
        */

        $windDirection =
            $this->windDirectionFromComponents(
                (float) $rawPredictions[
                    'wind_direction_sin'
                ],
                (float) $rawPredictions[
                    'wind_direction_cos'
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Final Predictions
        |--------------------------------------------------------------------------
        */

        $predictions = [
            'rainfall' =>
                round(
                    max(
                        0,
                        (float)
                        $rawPredictions[
                            'rainfall'
                        ]
                    ),
                    2
                ),

            'tmax' =>
                round(
                    (float)
                    $rawPredictions[
                        'tmax'
                    ],
                    2
                ),

            'tmin' =>
                round(
                    (float)
                    $rawPredictions[
                        'tmin'
                    ],
                    2
                ),

            'humidity' =>
                round(
                    max(
                        0,
                        min(
                            100,
                            (float)
                            $rawPredictions[
                                'humidity'
                            ]
                        )
                    ),
                    2
                ),

            'wind_speed' =>
                round(
                    max(
                        0,
                        (float)
                        $rawPredictions[
                            'wind_speed'
                        ]
                    ),
                    2
                ),

            'wind_direction' =>
                round(
                    $windDirection,
                    2
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Physical Temperature Check
        |--------------------------------------------------------------------------
        */

        if (
            $predictions['tmax']
            <
            $predictions['tmin']
        ) {
            $high = max(
                $predictions['tmax'],
                $predictions['tmin']
            );

            $low = min(
                $predictions['tmax'],
                $predictions['tmin']
            );

            $predictions['tmax'] =
                $high;

            $predictions['tmin'] =
                $low;
        }

        /*
        |--------------------------------------------------------------------------
        | Derived Average Temperature
        |--------------------------------------------------------------------------
        */

        $predictions['temperature'] =
            round(
                (
                    $predictions['tmax']
                    +
                    $predictions['tmin']
                ) / 2,
                2
            );

        $predictions['season'] =
            $this->seasonForMonth(
                (int)
                $targetMonth->format('n')
            );

        $confidence =
            $this->confidence(
                $monthly->count(),
                $training
            );

        return [
            'ready' =>
                true,

            'message' =>
                'This forecast was made using years of past weather records for this area.',

            'source_type' =>
                'Trained Model',

            'source_name' =>
                'iClimate Weather System',

            'source_note' =>
                'Uses only real, recorded weather history from the Ambulong weather station and never guesses using future data.',

            'weather_basis' =>
                'Ambulong',

            'training_cutoff' =>
                $trainingCutoff->format(
                    'Y-m-d'
                ),

            'training_period' => [
                'from' =>
                    $monthly
                        ->first()[
                            'month'
                        ]
                        ->format(
                            'Y-m'
                        ),

                'to' =>
                    $monthly
                        ->last()[
                            'month'
                        ]
                        ->format(
                            'Y-m'
                        ),
            ],

            'months_available' =>
                $monthly->count(),

            'target_month' =>
                $targetMonth,

            'predictions' =>
                $predictions,

            'training' =>
                $training,

            'confidence' =>
                $confidence,

            'insights' =>
                $this->insights(
                    $predictions,
                    $confidence
                ),
        ];
    }

    private function notReady(
        CarbonImmutable $targetMonth,
        CarbonImmutable $trainingCutoff,
        int $monthsAvailable,
        string $message
    ): array {
        return [
            'ready' =>
                false,

            'message' =>
                $message,

            'source_type' =>
                'Trained Model',

            'source_name' =>
                'iClimate Weather System',

            'source_note' =>
                'Uses recorded weather history from the Ambulong weather station.',

            'weather_basis' =>
                'Ambulong',

            'training_cutoff' =>
                $trainingCutoff->format(
                    'Y-m-d'
                ),

            'training_period' =>
                null,

            'months_available' =>
                $monthsAvailable,

            'target_month' =>
                $targetMonth,

            'predictions' =>
                [],

            'training' =>
                [],

            'confidence' =>
                null,

            'insights' =>
                [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Historical Dataset
    |--------------------------------------------------------------------------
    */

    private function monthlyClimateRecords(
        Collection $observations
    ): Collection {
        return $observations
            ->map(
                function (
                    array $observation
                ): array {
                    $month =
                        CarbonImmutable::createFromDate(
                            (int)
                            $observation[
                                'year'
                            ],
                            (int)
                            $observation[
                                'month'
                            ],
                            1
                        )->startOfMonth();

                    $windDirection =
                        (float)
                        $observation[
                            'wind_direction'
                        ];

                    $windRadians =
                        deg2rad(
                            $windDirection
                        );

                    return [
                        'month' =>
                            $month,

                        'year' =>
                            (int)
                            $observation[
                                'year'
                            ],

                        'month_number' =>
                            (int)
                            $observation[
                                'month'
                            ],

                        'rainfall' =>
                            (float)
                            $observation[
                                'rainfall'
                            ],

                        'tmax' =>
                            (float)
                            $observation[
                                'tmax'
                            ],

                        'tmin' =>
                            (float)
                            $observation[
                                'tmin'
                            ],

                        'temperature' =>
                            (float)
                            $observation[
                                'temperature'
                            ],

                        'humidity' =>
                            (float)
                            $observation[
                                'humidity'
                            ],

                        'wind_speed' =>
                            (float)
                            $observation[
                                'wind_speed'
                            ],

                        'wind_direction' =>
                            $windDirection,

                        'wind_direction_sin' =>
                            sin(
                                $windRadians
                            ),

                        'wind_direction_cos' =>
                            cos(
                                $windRadians
                            ),

                        'season' =>
                            $this->seasonForMonth(
                                (int)
                                $observation[
                                    'month'
                                ]
                            ),
                    ];
                }
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Build Training Samples
    |--------------------------------------------------------------------------
    */

    private function buildTrainingSamples(
        Collection $monthly
    ): array {
        $samples = [];

        /*
         * We begin at index 6 because the improved model
         * uses up to six months of historical context.
         */
        for (
            $i = 6;
            $i < $monthly->count();
            $i++
        ) {
            $current =
                $monthly[$i];

            /*
             * Strictly historical data.
             * Current month is NOT included.
             */
            $history = $monthly
                ->slice(
                    0,
                    $i
                )
                ->values();

            if ($history->isEmpty()) {
                continue;
            }

            $features =
                $this->featureVector(
                    $current['month'],
                    $history,
                    $i
                );

            foreach (
                self::TARGETS
                as $target
            ) {
                $features[$target] =
                    $current[$target];
            }

            $samples[] =
                $features;
        }

        return [
            $samples,

            [
                'history' =>
                    $monthly->values(),

                'index' =>
                    $monthly->count(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Target Month Features
    |--------------------------------------------------------------------------
    */

    private function featuresForTargetMonth(
        CarbonImmutable $targetMonth,
        array $context
    ): array {
        return $this->featureVector(
            $targetMonth->startOfMonth(),
            $context[
                'history'
            ],
            $context[
                'index'
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Feature Engineering
    |--------------------------------------------------------------------------
    */

    private function featureVector(
        CarbonImmutable $targetMonth,
        Collection $history,
        int $index
    ): array {
        $monthNumber =
            (int)
            $targetMonth->format(
                'n'
            );

        $year =
            (int)
            $targetMonth->format(
                'Y'
            );

        $angle =
            2
            * pi()
            * (
                $monthNumber
                / 12
            );

        $previous =
            $history->last();

        $last3 =
            $history
                ->slice(
                    max(
                        0,
                        $history->count() - 3
                    )
                )
                ->values();

        $last6 =
            $history
                ->slice(
                    max(
                        0,
                        $history->count() - 6
                    )
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Same Calendar Month History
        |--------------------------------------------------------------------------
        */

        $sameMonthHistory =
            $history
                ->filter(
                    fn (
                        array $row
                    ): bool =>
                        (int)
                        $row[
                            'month_number'
                        ]
                        ===
                        $monthNumber
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Exact Same Month Last Year
        |--------------------------------------------------------------------------
        */

        $sameMonthLastYear =
            $history
                ->first(
                    fn (
                        array $row
                    ): bool =>
                        (int)
                        $row['year']
                            ===
                            ($year - 1)
                        &&
                        (int)
                        $row[
                            'month_number'
                        ]
                            ===
                            $monthNumber
                );

        /*
        |--------------------------------------------------------------------------
        | Same Month Two Years Ago
        |--------------------------------------------------------------------------
        */

        $sameMonthTwoYearsAgo =
            $history
                ->first(
                    fn (
                        array $row
                    ): bool =>
                        (int)
                        $row['year']
                            ===
                            ($year - 2)
                        &&
                        (int)
                        $row[
                            'month_number'
                        ]
                            ===
                            $monthNumber
                );

        /*
        |--------------------------------------------------------------------------
        | Fallbacks
        |--------------------------------------------------------------------------
        */

        $sameMonthLastYear =
            $sameMonthLastYear
            ??
            $sameMonthHistory->last()
            ??
            $previous;

        $sameMonthTwoYearsAgo =
            $sameMonthTwoYearsAgo
            ??
            (
                $sameMonthHistory->count()
                    >= 2

                    ? $sameMonthHistory[
                        $sameMonthHistory->count()
                        - 2
                    ]

                    : $sameMonthLastYear
            );

        /*
        |--------------------------------------------------------------------------
        | Same-Month Historical Climatology
        |--------------------------------------------------------------------------
        */

        $sameMonthAverage =
            $sameMonthHistory->isNotEmpty()
                ? $sameMonthHistory
                : collect([
                    $previous,
                ]);

        return [
            /*
            |--------------------------------------------------------------------------
            | Calendar
            |--------------------------------------------------------------------------
            */

            'month' =>
                $monthNumber,

            'month_sin' =>
                sin(
                    $angle
                ),

            'month_cos' =>
                cos(
                    $angle
                ),

            'year' =>
                $year,

            'time_index' =>
                $index,

            'is_wet_season' =>
                $this->seasonForMonth(
                    $monthNumber
                ) === 'Wet'
                    ? 1
                    : 0,


            /*
            |--------------------------------------------------------------------------
            | Previous Month
            |--------------------------------------------------------------------------
            */

            'prev_rainfall' =>
                (float)
                $previous[
                    'rainfall'
                ],

            'prev_tmax' =>
                (float)
                $previous[
                    'tmax'
                ],

            'prev_tmin' =>
                (float)
                $previous[
                    'tmin'
                ],

            'prev_temperature' =>
                (float)
                $previous[
                    'temperature'
                ],

            'prev_humidity' =>
                (float)
                $previous[
                    'humidity'
                ],

            'prev_wind_speed' =>
                (float)
                $previous[
                    'wind_speed'
                ],

            'prev_wind_direction_sin' =>
                (float)
                $previous[
                    'wind_direction_sin'
                ],

            'prev_wind_direction_cos' =>
                (float)
                $previous[
                    'wind_direction_cos'
                ],


            /*
            |--------------------------------------------------------------------------
            | Rolling 3-Month Average
            |--------------------------------------------------------------------------
            */

            'avg3_rainfall' =>
                $this->average(
                    $last3,
                    'rainfall'
                ),

            'avg3_tmax' =>
                $this->average(
                    $last3,
                    'tmax'
                ),

            'avg3_tmin' =>
                $this->average(
                    $last3,
                    'tmin'
                ),

            'avg3_temperature' =>
                $this->average(
                    $last3,
                    'temperature'
                ),

            'avg3_humidity' =>
                $this->average(
                    $last3,
                    'humidity'
                ),

            'avg3_wind_speed' =>
                $this->average(
                    $last3,
                    'wind_speed'
                ),

            'avg3_wind_direction_sin' =>
                $this->average(
                    $last3,
                    'wind_direction_sin'
                ),

            'avg3_wind_direction_cos' =>
                $this->average(
                    $last3,
                    'wind_direction_cos'
                ),


            /*
            |--------------------------------------------------------------------------
            | Rolling 6-Month Average
            |--------------------------------------------------------------------------
            */

            'avg6_rainfall' =>
                $this->average(
                    $last6,
                    'rainfall'
                ),

            'avg6_tmax' =>
                $this->average(
                    $last6,
                    'tmax'
                ),

            'avg6_tmin' =>
                $this->average(
                    $last6,
                    'tmin'
                ),

            'avg6_temperature' =>
                $this->average(
                    $last6,
                    'temperature'
                ),

            'avg6_humidity' =>
                $this->average(
                    $last6,
                    'humidity'
                ),

            'avg6_wind_speed' =>
                $this->average(
                    $last6,
                    'wind_speed'
                ),


            /*
            |--------------------------------------------------------------------------
            | Same Month Last Year
            |--------------------------------------------------------------------------
            */

            'lag12_rainfall' =>
                (float)
                $sameMonthLastYear[
                    'rainfall'
                ],

            'lag12_tmax' =>
                (float)
                $sameMonthLastYear[
                    'tmax'
                ],

            'lag12_tmin' =>
                (float)
                $sameMonthLastYear[
                    'tmin'
                ],

            'lag12_temperature' =>
                (float)
                $sameMonthLastYear[
                    'temperature'
                ],

            'lag12_humidity' =>
                (float)
                $sameMonthLastYear[
                    'humidity'
                ],

            'lag12_wind_speed' =>
                (float)
                $sameMonthLastYear[
                    'wind_speed'
                ],

            'lag12_wind_direction_sin' =>
                (float)
                $sameMonthLastYear[
                    'wind_direction_sin'
                ],

            'lag12_wind_direction_cos' =>
                (float)
                $sameMonthLastYear[
                    'wind_direction_cos'
                ],


            /*
            |--------------------------------------------------------------------------
            | Same Month Two Years Ago
            |--------------------------------------------------------------------------
            */

            'lag24_rainfall' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'rainfall'
                ],

            'lag24_tmax' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'tmax'
                ],

            'lag24_tmin' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'tmin'
                ],

            'lag24_temperature' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'temperature'
                ],

            'lag24_humidity' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'humidity'
                ],

            'lag24_wind_speed' =>
                (float)
                $sameMonthTwoYearsAgo[
                    'wind_speed'
                ],


            /*
            |--------------------------------------------------------------------------
            | Historical Same-Month Climatology
            |--------------------------------------------------------------------------
            */

            'month_clim_rainfall' =>
                $this->average(
                    $sameMonthAverage,
                    'rainfall'
                ),

            'month_clim_tmax' =>
                $this->average(
                    $sameMonthAverage,
                    'tmax'
                ),

            'month_clim_tmin' =>
                $this->average(
                    $sameMonthAverage,
                    'tmin'
                ),

            'month_clim_temperature' =>
                $this->average(
                    $sameMonthAverage,
                    'temperature'
                ),

            'month_clim_humidity' =>
                $this->average(
                    $sameMonthAverage,
                    'humidity'
                ),

            'month_clim_wind_speed' =>
                $this->average(
                    $sameMonthAverage,
                    'wind_speed'
                ),

            'month_clim_wind_direction_sin' =>
                $this->average(
                    $sameMonthAverage,
                    'wind_direction_sin'
                ),

            'month_clim_wind_direction_cos' =>
                $this->average(
                    $sameMonthAverage,
                    'wind_direction_cos'
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Safe Numeric Average
    |--------------------------------------------------------------------------
    */

    private function average(
        Collection $rows,
        string $field
    ): float {
        if ($rows->isEmpty()) {
            return 0.0;
        }

        return (float)
            $rows->avg(
                $field
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Season
    |--------------------------------------------------------------------------
    */

    private function seasonForMonth(
        int $month
    ): string {
        return in_array(
            $month,
            self::WET_SEASON_MONTHS,
            true
        )
            ? 'Wet'
            : 'Dry';
    }

    /*
    |--------------------------------------------------------------------------
    | Circular Wind Direction
    |--------------------------------------------------------------------------
    */

    private function windDirectionFromComponents(
        float $sinValue,
        float $cosValue
    ): float {
        if (
            abs($sinValue)
                <
                0.0000001
            &&
            abs($cosValue)
                <
                0.0000001
        ) {
            return 0.0;
        }

        $degrees =
            rad2deg(
                atan2(
                    $sinValue,
                    $cosValue
                )
            );

        if ($degrees < 0) {
            $degrees += 360;
        }

        return fmod(
            $degrees,
            360.0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Target Stability
    |--------------------------------------------------------------------------
    */

    private function targetStability(
        array $samples,
        string $target
    ): array {
        $values =
            array_map(
                fn (
                    array $sample
                ): float =>
                    (float)
                    $sample[
                        $target
                    ],

                $samples
            );

        $mean =
            array_sum(
                $values
            )
            /
            max(
                count(
                    $values
                ),
                1
            );

        $variance =
            array_sum(
                array_map(
                    fn (
                        float $value
                    ): float =>
                        (
                            $value
                            -
                            $mean
                        ) ** 2,

                    $values
                )
            )
            /
            max(
                count(
                    $values
                ),
                1
            );

        $deviation =
            sqrt(
                $variance
            );

        if (
            in_array(
                $target,
                [
                    'wind_direction_sin',
                    'wind_direction_cos',
                ],
                true
            )
        ) {
            return [
                'mean' =>
                    round(
                        $mean,
                        4
                    ),

                'deviation' =>
                    round(
                        $deviation,
                        4
                    ),

                'label' =>
                    $deviation <= 0.25
                        ? 'Stable'
                        : (
                            $deviation <= 0.50
                                ? 'Variable'
                                : 'Highly variable'
                        ),
            ];
        }

        $ratio =
            abs($mean)
                >
                0.000001
                    ? $deviation
                        /
                        abs(
                            $mean
                        )
                    : 1;

        return [
            'mean' =>
                round(
                    $mean,
                    2
                ),

            'deviation' =>
                round(
                    $deviation,
                    2
                ),

            'label' =>
                $ratio <= 0.12
                    ? 'Stable'
                    : (
                        $ratio <= 0.24
                            ? 'Variable'
                            : 'Highly variable'
                    ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Confidence
    |--------------------------------------------------------------------------
    */

    private function confidence(
        int $monthsAvailable,
        array $training
    ): array {
        $score =
            min(
                92,
                48
                +
                (
                    $monthsAvailable
                    * 4
                )
            );

        foreach (
            $training
            as $metric
        ) {
            $label =
                $metric[
                    'stability'
                ][
                    'label'
                ]
                ??
                'Variable';

            if (
                $label
                ===
                'Highly variable'
            ) {
                $score -= 6;
            } elseif (
                $label
                ===
                'Variable'
            ) {
                $score -= 3;
            }
        }

        $score =
            max(
                45,
                min(
                    95,
                    $score
                )
            );

        return [
            'value' =>
                $score,

            'label' =>
                match (true) {
                    $score >= 80 =>
                        'High confidence',

                    $score >= 65 =>
                        'Moderate confidence',

                    default =>
                        'Low confidence',
                },

            'note' =>
                $monthsAvailable >= 24
                    ? 'The model uses multiple years of Ambulong monthly climate history.'
                    : 'Additional historical records may improve prediction reliability.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Agricultural Insights
    |--------------------------------------------------------------------------
    */

    private function insights(
        array $predictions,
        array $confidence
    ): array {
        $insights = [];

        if (
            (
                $predictions[
                    'rainfall'
                ]
                ?? 0
            ) > 300
        ) {
            $insights[] =
                'Heavy rain is likely. Check drainage and watch for flooding.';
        } elseif (
            (
                $predictions[
                    'rainfall'
                ]
                ?? 0
            ) < 100
        ) {
            $insights[] =
                'Rain may be low. Rainfed farms may need more water.';
        } else {
            $insights[] =
                'Rainfall looks normal for farm work.';
        }

        if (
            (
                $predictions[
                    'tmax'
                ]
                ?? 0
            ) >= 32
        ) {
            $insights[] =
                'Hot days may stress young or flowering rice.';
        }

        if (
            (
                $predictions[
                    'humidity'
                ]
                ?? 0
            ) >= 88
        ) {
            $insights[] =
                'Humid air may bring more pests and rice diseases.';
        }

        if (
            (
                $predictions[
                    'wind_speed'
                ]
                ?? 0
            ) >= 18
        ) {
            $insights[] =
                'Strong wind may knock down rice plants.';
        }

        if (
            isset(
                $confidence[
                    'note'
                ]
            )
        ) {
            $insights[] =
                $confidence[
                    'note'
                ];
        }

        return array_values(
            array_unique(
                $insights
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| Random Forest Regressor
|--------------------------------------------------------------------------
*/

class RandomForestRegressor
{
    /**
     * @var DecisionTreeRegressor[]
     */
    private array $trees = [];

    public function __construct(
        private readonly int $treeCount = 100,
        private readonly int $maxDepth = 8,
        private readonly int $minSamplesSplit = 3,
        private readonly int $seed = 1234,
    ) {
    }

    public function train(
        array $samples,
        string $target
    ): void {
        $this->trees = [];

        if ($samples === []) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Exclude ALL Target Variables from Features
        |--------------------------------------------------------------------------
        */

        $allTargets = [
            'rainfall',
            'tmax',
            'tmin',
            'humidity',
            'wind_speed',
            'wind_direction_sin',
            'wind_direction_cos',
        ];

        $features =
            array_values(
                array_filter(
                    array_keys(
                        $samples[
                            0
                        ]
                    ),

                    fn (
                        string $key
                    ): bool =>
                        ! in_array(
                            $key,
                            $allTargets,
                            true
                        )
                )
            );

        if ($features === []) {
            return;
        }

        for (
            $i = 0;
            $i < $this->treeCount;
            $i++
        ) {
            mt_srand(
                $this->seed
                +
                $i
            );

            $bootstrap =
                $this->bootstrap(
                    $samples
                );

            $featureSubsetSize =
                max(
                    3,
                    (int)
                    floor(
                        sqrt(
                            count(
                                $features
                            )
                        )
                    )
                );

            $tree =
                new DecisionTreeRegressor(
                    $this->maxDepth,
                    $this->minSamplesSplit,
                    $featureSubsetSize
                );

            $tree->train(
                $bootstrap,
                $features,
                $target
            );

            $this->trees[] =
                $tree;
        }
    }

    public function predict(
        array $features
    ): float {
        if ($this->trees === []) {
            return 0.0;
        }

        $values =
            array_map(
                fn (
                    DecisionTreeRegressor $tree
                ): float =>
                    $tree->predict(
                        $features
                    ),

                $this->trees
            );

        return array_sum(
            $values
        )
        /
        count(
            $values
        );
    }

    public function treeCount(): int
    {
        return count(
            $this->trees
        );
    }

    private function bootstrap(
        array $samples
    ): array {
        $result = [];

        $max =
            count(
                $samples
            )
            -
            1;

        for (
            $i = 0;
            $i < count(
                $samples
            );
            $i++
        ) {
            $result[] =
                $samples[
                    mt_rand(
                        0,
                        $max
                    )
                ];
        }

        return $result;
    }
}


/*
|--------------------------------------------------------------------------
| Decision Tree Regressor
|--------------------------------------------------------------------------
*/

class DecisionTreeRegressor
{
    private array $root = [];

    public function __construct(
        private readonly int $maxDepth,
        private readonly int $minSamplesSplit,
        private readonly int $featureSubsetSize,
    ) {
    }

    public function train(
        array $samples,
        array $features,
        string $target
    ): void {
        $this->root =
            $this->build(
                $samples,
                $features,
                $target,
                0
            );
    }

    public function predict(
        array $features
    ): float {
        $node =
            $this->root;

        while (
            (
                $node[
                    'type'
                ]
                ??
                'leaf'
            )
            ===
            'split'
        ) {
            $feature =
                $node[
                    'feature'
                ];

            $threshold =
                $node[
                    'threshold'
                ];

            $value =
                (float)
                (
                    $features[
                        $feature
                    ]
                    ??
                    0
                );

            $node =
                $value
                    <=
                    $threshold
                        ? $node[
                            'left'
                        ]
                        : $node[
                            'right'
                        ];
        }

        return (float)
            (
                $node[
                    'value'
                ]
                ??
                0
            );
    }

    private function build(
        array $samples,
        array $features,
        string $target,
        int $depth
    ): array {
        if (
            $samples === []
            ||
            $depth
                >=
                $this->maxDepth
            ||
            count(
                $samples
            )
                <
                $this->minSamplesSplit
            ||
            $this->variance(
                $samples,
                $target
            )
                <=
                0.000001
        ) {
            return [
                'type' =>
                    'leaf',

                'value' =>
                    $this->mean(
                        $samples,
                        $target
                    ),
            ];
        }

        $featureSubset =
            $this->featureSubset(
                $features
            );

        $split =
            $this->bestSplit(
                $samples,
                $featureSubset,
                $target
            );

        if ($split === null) {
            return [
                'type' =>
                    'leaf',

                'value' =>
                    $this->mean(
                        $samples,
                        $target
                    ),
            ];
        }

        return [
            'type' =>
                'split',

            'feature' =>
                $split[
                    'feature'
                ],

            'threshold' =>
                $split[
                    'threshold'
                ],

            'left' =>
                $this->build(
                    $split[
                        'left'
                    ],
                    $features,
                    $target,
                    $depth + 1
                ),

            'right' =>
                $this->build(
                    $split[
                        'right'
                    ],
                    $features,
                    $target,
                    $depth + 1
                ),
        ];
    }

    private function bestSplit(
        array $samples,
        array $features,
        string $target
    ): ?array {
        $best =
            null;

        $bestScore =
            INF;

        foreach (
            $features
            as $feature
        ) {
            $values =
                array_values(
                    array_unique(
                        array_map(
                            fn (
                                array $sample
                            ): float =>
                                (float)
                                $sample[
                                    $feature
                                ],

                            $samples
                        )
                    )
                );

            sort(
                $values
            );

            if (
                count(
                    $values
                )
                <
                2
            ) {
                continue;
            }

            $thresholds = [];

            for (
                $i = 1;
                $i < count(
                    $values
                );
                $i++
            ) {
                $thresholds[] =
                    (
                        $values[
                            $i - 1
                        ]
                        +
                        $values[
                            $i
                        ]
                    )
                    / 2;
            }

            foreach (
                $thresholds
                as $threshold
            ) {
                $left =
                    array_values(
                        array_filter(
                            $samples,

                            fn (
                                array $sample
                            ): bool =>
                                (float)
                                $sample[
                                    $feature
                                ]
                                <=
                                $threshold
                        )
                    );

                $right =
                    array_values(
                        array_filter(
                            $samples,

                            fn (
                                array $sample
                            ): bool =>
                                (float)
                                $sample[
                                    $feature
                                ]
                                >
                                $threshold
                        )
                    );

                if (
                    $left === []
                    ||
                    $right === []
                ) {
                    continue;
                }

                $score =
                    (
                        count(
                            $left
                        )
                        *
                        $this->variance(
                            $left,
                            $target
                        )
                        +
                        count(
                            $right
                        )
                        *
                        $this->variance(
                            $right,
                            $target
                        )
                    )
                    /
                    count(
                        $samples
                    );

                if (
                    $score
                    <
                    $bestScore
                ) {
                    $bestScore =
                        $score;

                    $best = [
                        'feature' =>
                            $feature,

                        'threshold' =>
                            $threshold,

                        'left' =>
                            $left,

                        'right' =>
                            $right,
                    ];
                }
            }
        }

        return $best;
    }

    private function featureSubset(
        array $features
    ): array {
        $copy =
            $features;

        shuffle(
            $copy
        );

        return array_slice(
            $copy,
            0,
            min(
                $this->featureSubsetSize,
                count(
                    $copy
                )
            )
        );
    }

    private function mean(
        array $samples,
        string $target
    ): float {
        if ($samples === []) {
            return 0.0;
        }

        return array_sum(
            array_map(
                fn (
                    array $sample
                ): float =>
                    (float)
                    $sample[
                        $target
                    ],

                $samples
            )
        )
        /
        count(
            $samples
        );
    }

    private function variance(
        array $samples,
        string $target
    ): float {
        if ($samples === []) {
            return 0.0;
        }

        $mean =
            $this->mean(
                $samples,
                $target
            );

        $sum =
            array_sum(
                array_map(
                    fn (
                        array $sample
                    ): float =>
                        (
                            (float)
                            $sample[
                                $target
                            ]
                            -
                            $mean
                        ) ** 2,

                    $samples
                )
            );

        return $sum
        /
        count(
            $samples
        );
    }
}