<?php

namespace Tests\Feature\MachineLearning;

use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MonthlyWeatherRandomForestHoldoutTest extends TestCase
{
    public function test_2025_is_a_true_unseen_holdout(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Load Real Ambulong Dataset
        |--------------------------------------------------------------------------
        */

        $datasetService = app(
            AmbulongHistoricalClimateDataset::class
        );

        $dataset = $datasetService->load();

        $this->assertTrue(
            $dataset['available'],
            $dataset['message']
                ?? 'Ambulong dataset unavailable.'
        );

        $observations =
            $dataset['observations'];

        /*
        |--------------------------------------------------------------------------
        | Actual 2025 Weather
        |--------------------------------------------------------------------------
        */

        $actual2025 = $observations
            ->filter(
                fn (array $row): bool =>
                    (int) $row['year'] === 2025
            )
            ->values();

        $this->assertNotEmpty(
            $actual2025,
            'No 2025 Ambulong observations were found.'
        );

        /*
        |--------------------------------------------------------------------------
        | Strict Training Cutoff
        |--------------------------------------------------------------------------
        |
        | Model may use:
        | 2015 through December 2024
        |
        | Model may NOT use:
        | January through December 2025
        |
        */

        $trainingCutoff = CarbonImmutable::create(
            2024,
            12,
            31
        )->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Model
        |--------------------------------------------------------------------------
        */

        $model = app(
            MonthlyWeatherRandomForest::class
        );

        /*
        |--------------------------------------------------------------------------
        | Generate 2025 Predictions
        |--------------------------------------------------------------------------
        */

        $results = [];

        foreach (
            $actual2025
            as $actual
        ) {
            $targetMonth =
                CarbonImmutable::createFromDate(
                    2025,
                    (int) $actual['month'],
                    1
                )->startOfMonth();

            $prediction =
                $model->predict(
                    $targetMonth,
                    $trainingCutoff
                );

            $this->assertTrue(
                $prediction['ready'],
                $prediction['message']
                    ?? 'Weather model was not ready.'
            );

            /*
            |--------------------------------------------------------------------------
            | Verify No Leakage
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                '2024-12-31',
                $prediction[
                    'training_cutoff'
                ]
            );

            $this->assertSame(
                '2024-12',
                $prediction[
                    'training_period'
                ][
                    'to'
                ]
            );

            $predicted =
                $prediction[
                    'predictions'
                ];

            $results[] = [
                'month' =>
                    (int) $actual['month'],

                'actual_rainfall' =>
                    (float) $actual[
                        'rainfall'
                    ],

                'predicted_rainfall' =>
                    (float) $predicted[
                        'rainfall'
                    ],

                'actual_tmax' =>
                    (float) $actual[
                        'tmax'
                    ],

                'predicted_tmax' =>
                    (float) $predicted[
                        'tmax'
                    ],

                'actual_tmin' =>
                    (float) $actual[
                        'tmin'
                    ],

                'predicted_tmin' =>
                    (float) $predicted[
                        'tmin'
                    ],

                'actual_rh' =>
                    (float) $actual[
                        'humidity'
                    ],

                'predicted_rh' =>
                    (float) $predicted[
                        'humidity'
                    ],

                'actual_wind_speed' =>
                    (float) $actual[
                        'wind_speed'
                    ],

                'predicted_wind_speed' =>
                    (float) $predicted[
                        'wind_speed'
                    ],

                'actual_wind_direction' =>
                    (float) $actual[
                        'wind_direction'
                    ],

                'predicted_wind_direction' =>
                    (float) $predicted[
                        'wind_direction'
                    ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Display Monthly Comparison
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."=========================================================================="
            .PHP_EOL
            ."TRUE HOLDOUT VALIDATION — AMBULONG WEATHER RANDOM FOREST"
            .PHP_EOL
            ."TRAIN: <= DECEMBER 2024"
            .PHP_EOL
            ."TEST : JANUARY–DECEMBER 2025"
            .PHP_EOL
            ."=========================================================================="
            .PHP_EOL
        );

        foreach (
            $results
            as $row
        ) {
            fwrite(
                STDERR,
                sprintf(
                    "Month %02d | "
                    ."Rain %.2f -> %.2f | "
                    ."TMAX %.2f -> %.2f | "
                    ."TMIN %.2f -> %.2f | "
                    ."RH %.2f -> %.2f | "
                    ."Wind %.2f -> %.2f | "
                    ."Dir %.2f -> %.2f"
                    .PHP_EOL,

                    $row['month'],

                    $row[
                        'actual_rainfall'
                    ],
                    $row[
                        'predicted_rainfall'
                    ],

                    $row[
                        'actual_tmax'
                    ],
                    $row[
                        'predicted_tmax'
                    ],

                    $row[
                        'actual_tmin'
                    ],
                    $row[
                        'predicted_tmin'
                    ],

                    $row[
                        'actual_rh'
                    ],
                    $row[
                        'predicted_rh'
                    ],

                    $row[
                        'actual_wind_speed'
                    ],
                    $row[
                        'predicted_wind_speed'
                    ],

                    $row[
                        'actual_wind_direction'
                    ],
                    $row[
                        'predicted_wind_direction'
                    ],
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Regression Metrics
        |--------------------------------------------------------------------------
        */

        $metrics = [
            'rainfall' =>
                $this->regressionMetrics(
                    array_column(
                        $results,
                        'actual_rainfall'
                    ),
                    array_column(
                        $results,
                        'predicted_rainfall'
                    )
                ),

            'tmax' =>
                $this->regressionMetrics(
                    array_column(
                        $results,
                        'actual_tmax'
                    ),
                    array_column(
                        $results,
                        'predicted_tmax'
                    )
                ),

            'tmin' =>
                $this->regressionMetrics(
                    array_column(
                        $results,
                        'actual_tmin'
                    ),
                    array_column(
                        $results,
                        'predicted_tmin'
                    )
                ),

            'rh' =>
                $this->regressionMetrics(
                    array_column(
                        $results,
                        'actual_rh'
                    ),
                    array_column(
                        $results,
                        'predicted_rh'
                    )
                ),

            'wind_speed' =>
                $this->regressionMetrics(
                    array_column(
                        $results,
                        'actual_wind_speed'
                    ),
                    array_column(
                        $results,
                        'predicted_wind_speed'
                    )
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Wind Direction Angular Error
        |--------------------------------------------------------------------------
        */

        $windDirectionError =
            $this->meanAngularError(
                array_column(
                    $results,
                    'actual_wind_direction'
                ),
                array_column(
                    $results,
                    'predicted_wind_direction'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Print Final Metrics
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."TRUE HOLDOUT METRICS"
            .PHP_EOL
            ."--------------------------------------------------------------------------"
            .PHP_EOL
        );

        foreach (
            $metrics
            as $name => $metric
        ) {
            fwrite(
                STDERR,
                sprintf(
                    "%-12s MAE: %.4f | RMSE: %.4f | R²: %.4f"
                    .PHP_EOL,

                    strtoupper(
                        $name
                    ),

                    $metric[
                        'mae'
                    ],

                    $metric[
                        'rmse'
                    ],

                    $metric[
                        'r2'
                    ]
                )
            );
        }

        fwrite(
            STDERR,
            sprintf(
                "WIND DIR     Mean Angular Error: %.4f degrees"
                .PHP_EOL,
                $windDirectionError
            )
        );

        fwrite(
            STDERR,
            "=========================================================================="
            .PHP_EOL
        );

        /*
        |--------------------------------------------------------------------------
        | Sanity Assertions
        |--------------------------------------------------------------------------
        */

        foreach (
            $metrics
            as $metric
        ) {
            $this->assertIsFloat(
                $metric['mae']
            );

            $this->assertIsFloat(
                $metric['rmse']
            );

            $this->assertIsFloat(
                $metric['r2']
            );

            $this->assertGreaterThanOrEqual(
                0,
                $metric['mae']
            );

            $this->assertGreaterThanOrEqual(
                0,
                $metric['rmse']
            );
        }

        $this->assertGreaterThanOrEqual(
            0,
            $windDirectionError
        );

        $this->assertLessThanOrEqual(
            180,
            $windDirectionError
        );
    }

    private function regressionMetrics(
        array $actual,
        array $predicted
    ): array {
        $count = count(
            $actual
        );

        if (
            $count === 0
            ||
            $count !== count(
                $predicted
            )
        ) {
            throw new \RuntimeException(
                'Metric arrays must have equal non-zero length.'
            );
        }

        $absoluteErrors = [];

        $squaredErrors = [];

        for (
            $i = 0;
            $i < $count;
            $i++
        ) {
            $error =
                (float) $predicted[$i]
                -
                (float) $actual[$i];

            $absoluteErrors[] =
                abs(
                    $error
                );

            $squaredErrors[] =
                $error ** 2;
        }

        $mae =
            array_sum(
                $absoluteErrors
            )
            /
            $count;

        $rmse =
            sqrt(
                array_sum(
                    $squaredErrors
                )
                /
                $count
            );

        $meanActual =
            array_sum(
                $actual
            )
            /
            $count;

        $ssResidual =
            0.0;

        $ssTotal =
            0.0;

        for (
            $i = 0;
            $i < $count;
            $i++
        ) {
            $ssResidual +=
                (
                    (float) $actual[$i]
                    -
                    (float) $predicted[$i]
                ) ** 2;

            $ssTotal +=
                (
                    (float) $actual[$i]
                    -
                    $meanActual
                ) ** 2;
        }

        $r2 =
            $ssTotal > 0
                ? 1
                    -
                    (
                        $ssResidual
                        /
                        $ssTotal
                    )
                : 0.0;

        return [
            'mae' =>
                (float) $mae,

            'rmse' =>
                (float) $rmse,

            'r2' =>
                (float) $r2,
        ];
    }

    private function meanAngularError(
        array $actual,
        array $predicted
    ): float {
        $count = count(
            $actual
        );

        if (
            $count === 0
            ||
            $count !== count(
                $predicted
            )
        ) {
            throw new \RuntimeException(
                'Angular-error arrays must have equal non-zero length.'
            );
        }

        $errors = [];

        for (
            $i = 0;
            $i < $count;
            $i++
        ) {
            $difference =
                abs(
                    (float) $actual[$i]
                    -
                    (float) $predicted[$i]
                );

            $difference =
                fmod(
                    $difference,
                    360.0
                );

            $errors[] =
                min(
                    $difference,
                    360.0
                    -
                    $difference
                );
        }

        return array_sum(
            $errors
        )
        /
        $count;
    }
}