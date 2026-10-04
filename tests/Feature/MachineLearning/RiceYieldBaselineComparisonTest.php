<?php

namespace Tests\Feature\MachineLearning;

use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Concerns\RunsProjectPython;
use Tests\TestCase;

class RiceYieldBaselineComparisonTest extends TestCase
{
    use RunsProjectPython;

    public function test_candidate_chain_is_compared_against_simple_baselines(): void
    {
        // Runs the real candidate model through Python; skipped (with the reason) where no usable environment exists.
        $this->projectPythonOrSkip();

        /*
        |--------------------------------------------------------------------------
        | Purpose
        |--------------------------------------------------------------------------
        |
        | Compare the full chained candidate model against simple baselines:
        |
        | Baseline A:
        | Always predict the mean training yield.
        |
        | Baseline B:
        | Predict historical mean yield for the same calendar month.
        |
        | Candidate:
        | Weather RF -> Candidate Rice Yield RF
        |
        | Holdout:
        | 2025 actual rice yield.
        |
        */

        $trainingCutoff = CarbonImmutable::create(
            2024,
            12,
            31
        )->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Load Modeling Dataset
        |--------------------------------------------------------------------------
        */

        $dataset = $this->loadModelingDataset();

        $trainingRows = array_values(
            array_filter(
                $dataset,
                fn (array $row): bool =>
                    (int) $row['year'] <= 2024
            )
        );

        $holdoutRows = array_values(
            array_filter(
                $dataset,
                fn (array $row): bool =>
                    (int) $row['year'] === 2025
            )
        );

        $this->assertNotEmpty(
            $trainingRows,
            'No training rice-yield rows were found for years <= 2024.'
        );

        $this->assertNotEmpty(
            $holdoutRows,
            'No 2025 holdout rice-yield rows were found.'
        );

        /*
        |--------------------------------------------------------------------------
        | Collapse Holdout to One Actual Yield Per Month
        |--------------------------------------------------------------------------
        */

        $actual2025 = $this->averageYieldByMonth(
            $holdoutRows
        );

        /*
        |--------------------------------------------------------------------------
        | Baseline A:
        | Overall Mean Training Yield
        |--------------------------------------------------------------------------
        */

        $trainingYields = array_map(
            fn (array $row): float =>
                (float) $row['actual_yield'],
            $trainingRows
        );

        $overallMeanYield =
            array_sum(
                $trainingYields
            )
            /
            count(
                $trainingYields
            );

        /*
        |--------------------------------------------------------------------------
        | Baseline B:
        | Historical Same-Month Mean
        |--------------------------------------------------------------------------
        */

        $sameMonthMeans = [];

        for (
            $month = 1;
            $month <= 12;
            $month++
        ) {
            $sameMonthRows = array_values(
                array_filter(
                    $trainingRows,
                    fn (array $row): bool =>
                        (int) $row['month'] === $month
                )
            );

            if ($sameMonthRows === []) {
                $sameMonthMeans[$month] =
                    $overallMeanYield;

                continue;
            }

            $values = array_map(
                fn (array $row): float =>
                    (float) $row['actual_yield'],
                $sameMonthRows
            );

            $sameMonthMeans[$month] =
                array_sum(
                    $values
                )
                /
                count(
                    $values
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Candidate Chain
        |--------------------------------------------------------------------------
        */

        $weatherModel = app(
            MonthlyWeatherRandomForest::class
        );

        $results = [];

        foreach (
            $actual2025
            as $actualRow
        ) {
            $month =
                (int) $actualRow['month'];

            $targetMonth =
                CarbonImmutable::createFromDate(
                    2025,
                    $month,
                    1
                )->startOfMonth();

            /*
            |--------------------------------------------------------------------------
            | Weather Prediction with Strict <= 2024 Cutoff
            |--------------------------------------------------------------------------
            */

            $weatherPrediction =
                $weatherModel->predict(
                    $targetMonth,
                    $trainingCutoff
                );

            $this->assertTrue(
                $weatherPrediction['ready']
                    ?? false,
                $weatherPrediction['message']
                    ?? "Weather model failed for month {$month}."
            );

            $this->assertSame(
                '2024-12-31',
                $weatherPrediction[
                    'training_cutoff'
                ]
            );

            $this->assertSame(
                '2024-12',
                $weatherPrediction[
                    'training_period'
                ][
                    'to'
                ]
            );

            $weather =
                $weatherPrediction[
                    'predictions'
                ];

            /*
            |--------------------------------------------------------------------------
            | Candidate Yield Payload
            |--------------------------------------------------------------------------
            */

            $payload = [
                'month' =>
                    $month,

                'rainfall' =>
                    (float) $weather[
                        'rainfall'
                    ],

                'tmax' =>
                    (float) $weather[
                        'tmax'
                    ],

                'tmin' =>
                    (float) $weather[
                        'tmin'
                    ],

                'rh' =>
                    (float) $weather[
                        'humidity'
                    ],

                'wind_speed' =>
                    (float) $weather[
                        'wind_speed'
                    ],

                'wind_direction' =>
                    (float) $weather[
                        'wind_direction'
                    ],
            ];

            /*
            |--------------------------------------------------------------------------
            | Candidate Yield Prediction
            |--------------------------------------------------------------------------
            */

            $candidate =
                $this->runCandidateRiceYield(
                    $payload
                );

            $this->assertTrue(
                $candidate['success']
                    ?? false,
                $candidate['message']
                    ?? "Candidate model failed for month {$month}."
            );

            $actualYield =
                (float) $actualRow[
                    'actual_yield'
                ];

            $candidatePrediction =
                (float) $candidate[
                    'predicted_yield'
                ];

            $overallBaseline =
                (float) $overallMeanYield;

            $sameMonthBaseline =
                (float) (
                    $sameMonthMeans[$month]
                    ??
                    $overallMeanYield
                );

            $results[] = [
                'month' =>
                    $month,

                'month_name' =>
                    $targetMonth->format(
                        'F'
                    ),

                'actual' =>
                    $actualYield,

                'candidate' =>
                    $candidatePrediction,

                'overall_mean' =>
                    $overallBaseline,

                'same_month_mean' =>
                    $sameMonthBaseline,
            ];
        }

        usort(
            $results,
            fn (
                array $a,
                array $b
            ): int =>
                $a['month']
                <=>
                $b['month']
        );

        /*
        |--------------------------------------------------------------------------
        | Build Metric Arrays
        |--------------------------------------------------------------------------
        */

        $actual = array_column(
            $results,
            'actual'
        );

        $candidatePredictions = array_column(
            $results,
            'candidate'
        );

        $overallMeanPredictions = array_column(
            $results,
            'overall_mean'
        );

        $sameMonthPredictions = array_column(
            $results,
            'same_month_mean'
        );

        /*
        |--------------------------------------------------------------------------
        | Metrics
        |--------------------------------------------------------------------------
        */

        $candidateMetrics =
            $this->regressionMetrics(
                $actual,
                $candidatePredictions
            );

        $overallMeanMetrics =
            $this->regressionMetrics(
                $actual,
                $overallMeanPredictions
            );

        $sameMonthMetrics =
            $this->regressionMetrics(
                $actual,
                $sameMonthPredictions
            );

        /*
        |--------------------------------------------------------------------------
        | Print Detailed Monthly Comparison
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."================================================================================"
            .PHP_EOL
            ."2025 RICE YIELD BASELINE COMPARISON"
            .PHP_EOL
            ."TRAINING DATA : <= 2024"
            .PHP_EOL
            ."TEST DATA     : 2025"
            .PHP_EOL
            ."================================================================================"
            .PHP_EOL
        );

        foreach (
            $results
            as $row
        ) {
            fwrite(
                STDERR,
                sprintf(
                    "%-9s | "
                    ."Actual %.4f | "
                    ."Candidate %.4f | "
                    ."Overall Mean %.4f | "
                    ."Same-Month Mean %.4f"
                    .PHP_EOL,

                    $row[
                        'month_name'
                    ],

                    $row[
                        'actual'
                    ],

                    $row[
                        'candidate'
                    ],

                    $row[
                        'overall_mean'
                    ],

                    $row[
                        'same_month_mean'
                    ],
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Print Final Metric Comparison
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."METRIC COMPARISON"
            .PHP_EOL
            ."--------------------------------------------------------------------------------"
            .PHP_EOL
        );

        fwrite(
            STDERR,
            sprintf(
                "%-22s MAE: %.4f | RMSE: %.4f | R²: %.4f"
                .PHP_EOL,

                'Candidate Chain',

                $candidateMetrics[
                    'mae'
                ],

                $candidateMetrics[
                    'rmse'
                ],

                $candidateMetrics[
                    'r2'
                ]
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "%-22s MAE: %.4f | RMSE: %.4f | R²: %.4f"
                .PHP_EOL,

                'Overall Mean Baseline',

                $overallMeanMetrics[
                    'mae'
                ],

                $overallMeanMetrics[
                    'rmse'
                ],

                $overallMeanMetrics[
                    'r2'
                ]
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "%-22s MAE: %.4f | RMSE: %.4f | R²: %.4f"
                .PHP_EOL,

                'Same-Month Baseline',

                $sameMonthMetrics[
                    'mae'
                ],

                $sameMonthMetrics[
                    'rmse'
                ],

                $sameMonthMetrics[
                    'r2'
                ]
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Improvement / Difference
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."CANDIDATE DIFFERENCE VS BASELINES"
            .PHP_EOL
            ."--------------------------------------------------------------------------------"
            .PHP_EOL
        );

        fwrite(
            STDERR,
            sprintf(
                "vs Overall Mean | MAE: %+.4f | RMSE: %+.4f | R²: %+.4f"
                .PHP_EOL,

                $candidateMetrics[
                    'mae'
                ]
                -
                $overallMeanMetrics[
                    'mae'
                ],

                $candidateMetrics[
                    'rmse'
                ]
                -
                $overallMeanMetrics[
                    'rmse'
                ],

                $candidateMetrics[
                    'r2'
                ]
                -
                $overallMeanMetrics[
                    'r2'
                ]
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "vs Same-Month   | MAE: %+.4f | RMSE: %+.4f | R²: %+.4f"
                .PHP_EOL,

                $candidateMetrics[
                    'mae'
                ]
                -
                $sameMonthMetrics[
                    'mae'
                ],

                $candidateMetrics[
                    'rmse'
                ]
                -
                $sameMonthMetrics[
                    'rmse'
                ],

                $candidateMetrics[
                    'r2'
                ]
                -
                $sameMonthMetrics[
                    'r2'
                ]
            )
        );

        fwrite(
            STDERR,
            "================================================================================"
            .PHP_EOL
        );

        /*
        |--------------------------------------------------------------------------
        | Sanity Assertions
        |--------------------------------------------------------------------------
        |
        | No arbitrary performance thresholds yet.
        |
        */

        $this->assertIsFloat(
            $candidateMetrics[
                'mae'
            ]
        );

        $this->assertIsFloat(
            $candidateMetrics[
                'rmse'
            ]
        );

        $this->assertIsFloat(
            $candidateMetrics[
                'r2'
            ]
        );

        $this->assertIsFloat(
            $overallMeanMetrics[
                'mae'
            ]
        );

        $this->assertIsFloat(
            $sameMonthMetrics[
                'mae'
            ]
        );

        $this->assertGreaterThanOrEqual(
            0,
            $candidateMetrics[
                'mae'
            ]
        );

        $this->assertGreaterThanOrEqual(
            0,
            $candidateMetrics[
                'rmse'
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Modeling Dataset
    |--------------------------------------------------------------------------
    */

    private function loadModelingDataset(): array
    {
        $path = storage_path(
            'datasets/iClimate_Modeling_Ready_TimeAware.csv'
        );

        if (
            ! File::exists(
                $path
            )
        ) {
            throw new \RuntimeException(
                'Modeling dataset not found: '
                .$path
            );
        }

        $handle = fopen(
            $path,
            'r'
        );

        if (
            $handle === false
        ) {
            throw new \RuntimeException(
                'Unable to open modeling dataset: '
                .$path
            );
        }

        $headers = fgetcsv(
            $handle
        );

        if (
            ! is_array(
                $headers
            )
            ||
            $headers === []
        ) {
            fclose(
                $handle
            );

            throw new \RuntimeException(
                'Modeling dataset has no valid CSV header.'
            );
        }

        $headers = array_map(
            fn (
                $header
            ): string =>
                strtoupper(
                    trim(
                        (string) $header
                    )
                ),

            $headers
        );

        $required = [
            'YEAR',
            'MONTH',
            'AVERAGE_YIELD',
        ];

        foreach (
            $required
            as $column
        ) {
            if (
                ! in_array(
                    $column,
                    $headers,
                    true
                )
            ) {
                fclose(
                    $handle
                );

                throw new \RuntimeException(
                    "Required column {$column} is missing from modeling dataset."
                );
            }
        }

        $rows = [];

        while (
            (
                $data = fgetcsv(
                    $handle
                )
            )
            !== false
        ) {
            if (
                count(
                    $data
                )
                !==
                count(
                    $headers
                )
            ) {
                continue;
            }

            $row = array_combine(
                $headers,
                $data
            );

            if (
                ! is_array(
                    $row
                )
            ) {
                continue;
            }

            $year = trim(
                (string) (
                    $row[
                        'YEAR'
                    ]
                    ?? ''
                )
            );

            $month = trim(
                (string) (
                    $row[
                        'MONTH'
                    ]
                    ?? ''
                )
            );

            $yield = trim(
                (string) (
                    $row[
                        'AVERAGE_YIELD'
                    ]
                    ?? ''
                )
            );

            if (
                ! is_numeric(
                    $year
                )
                ||
                ! is_numeric(
                    $month
                )
                ||
                ! is_numeric(
                    $yield
                )
            ) {
                continue;
            }

            $monthNumber =
                (int) $month;

            if (
                $monthNumber < 1
                ||
                $monthNumber > 12
            ) {
                continue;
            }

            $rows[] = [
                'year' =>
                    (int) $year,

                'month' =>
                    $monthNumber,

                'actual_yield' =>
                    (float) $yield,
            ];
        }

        fclose(
            $handle
        );

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Average Actual Yield By Month
    |--------------------------------------------------------------------------
    */

    private function averageYieldByMonth(
        array $rows
    ): array {
        $grouped = [];

        foreach (
            $rows
            as $row
        ) {
            $month =
                (int) $row[
                    'month'
                ];

            $grouped[
                $month
            ] ??= [];

            $grouped[
                $month
            ][] =
                (float) $row[
                    'actual_yield'
                ];
        }

        $result = [];

        foreach (
            $grouped
            as $month => $values
        ) {
            $result[] = [
                'month' =>
                    (int) $month,

                'actual_yield' =>
                    array_sum(
                        $values
                    )
                    /
                    count(
                        $values
                    ),
            ];
        }

        usort(
            $result,
            fn (
                array $a,
                array $b
            ): int =>
                $a[
                    'month'
                ]
                <=>
                $b[
                    'month'
                ]
        );

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate Predictor
    |--------------------------------------------------------------------------
    */

    private function runCandidateRiceYield(
        array $payload
    ): array {
        $script = base_path(
            'python_scripts/predict_rice_yield_candidate.py'
        );

        if (
            ! File::exists(
                $script
            )
        ) {
            throw new \RuntimeException(
                'Candidate Python script was not found: '
                .$script
            );
        }

        $model = storage_path(
            'models/rice_yield_model_candidate.pkl'
        );

        if (
            ! File::exists(
                $model
            )
        ) {
            throw new \RuntimeException(
                'Candidate model was not found: '
                .$model
            );
        }

        $pythonBinary =
            $this->projectPythonOrSkip();

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $projectRoot =
            base_path();

        // Cross-platform interpreter and clean environment: tests/Concerns/RunsProjectPython.php
        $environment =
            $this->pythonEnvironment(
                $pythonBinary
            );

        $process = new Process(
            [
                $pythonBinary,
                $script,
                $json,
            ],
            $projectRoot,
            $environment
        );

        $process->setTimeout(
            60
        );

        $process->run();

        if (
            ! $process->isSuccessful()
        ) {
            $stderr =
                trim(
                    $process->getErrorOutput()
                );

            $stdout =
                trim(
                    $process->getOutput()
                );

            throw new \RuntimeException(
                'Candidate prediction failed: '
                .(
                    $stderr !== ''
                        ? $stderr
                        : $stdout
                )
            );
        }

        $output =
            trim(
                $process->getOutput()
            );

        if ($output === '') {
            throw new \RuntimeException(
                'Candidate predictor returned no output.'
            );
        }

        $lines = preg_split(
            '/\R/',
            $output
        );

        $lines = array_values(
            array_filter(
                array_map(
                    'trim',
                    $lines
                ),
                fn (
                    string $line
                ): bool =>
                    $line !== ''
            )
        );

        if ($lines === []) {
            throw new \RuntimeException(
                'Candidate predictor returned no usable output.'
            );
        }

        $jsonLine =
            $lines[
                count(
                    $lines
                )
                -
                1
            ];

        $result = json_decode(
            $jsonLine,
            true
        );

        if (
            ! is_array(
                $result
            )
        ) {
            throw new \RuntimeException(
                'Candidate predictor returned invalid JSON: '
                .$output
            );
        }

        if (
            ! (
                $result[
                    'success'
                ]
                ?? false
            )
        ) {
            throw new \RuntimeException(
                $result[
                    'message'
                ]
                ?? 'Candidate predictor reported failure.'
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Regression Metrics
    |--------------------------------------------------------------------------
    */

    private function regressionMetrics(
        array $actual,
        array $predicted
    ): array {
        $count =
            count(
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
                (float) $predicted[
                    $i
                ]
                -
                (float) $actual[
                    $i
                ];

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
                    (float) $actual[
                        $i
                    ]
                    -
                    (float) $predicted[
                        $i
                    ]
                ) ** 2;

            $ssTotal +=
                (
                    (float) $actual[
                        $i
                    ]
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
}