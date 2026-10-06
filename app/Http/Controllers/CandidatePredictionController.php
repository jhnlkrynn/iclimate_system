<?php

namespace App\Http\Controllers;

use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Support\PythonRuntime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class CandidatePredictionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Candidate Prediction Page
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $year = (int) $request->query(
            'year',
            2026
        );

        $month = (int) $request->query(
            'month',
            \App\Support\BusinessClock::month()
        );

        $year = $this->normalizeYear(
            $year
        );

        $month = $this->normalizeMonth(
            $month
        );

        /*
        |--------------------------------------------------------------------------
        | Load Existing Full-Year Generated Report
        |--------------------------------------------------------------------------
        */

        $fullYearPredictions =
            $this->loadFullYearPredictions(
                $year
            );

        $reportAvailable =
            $fullYearPredictions !== [];

        return view(
            'testing.candidate-predictions',
            [
                'year' =>
                    $year,

                'month' =>
                    $month,

                'availableYears' =>
                    $this->availableYears(),

                'weatherPrediction' =>
                    null,

                'yieldPrediction' =>
                    null,

                'fullYearPredictions' =>
                    $fullYearPredictions,

                'reportAvailable' =>
                    $reportAvailable,

                'error' =>
                    null,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Single Month Prediction
    |--------------------------------------------------------------------------
    */

    public function predict(
        Request $request,
        MonthlyWeatherRandomForest $weatherModel
    ) {
        $validated = $request->validate([
            'year' => [
                'required',
                'integer',
                'min:2025',
                'max:2027',
            ],

            'month' => [
                'required',
                'integer',
                'between:1,12',
            ],
        ]);

        $year =
            (int) $validated['year'];

        $month =
            (int) $validated['month'];

        $targetMonth =
            CarbonImmutable::createFromDate(
                $year,
                $month,
                1
            )->startOfMonth();

        $weatherPrediction = null;

        try {
            /*
            |--------------------------------------------------------------------------
            | Step 1: Weather Random Forest
            |--------------------------------------------------------------------------
            */

            $weatherPrediction =
                $weatherModel->predict(
                    $targetMonth
                );

            if (
                ! (
                    $weatherPrediction[
                        'ready'
                    ]
                    ?? false
                )
            ) {
                return view(
                    'testing.candidate-predictions',
                    [
                        'year' =>
                            $year,

                        'month' =>
                            $month,

                        'availableYears' =>
                            $this->availableYears(),

                        'weatherPrediction' =>
                            $weatherPrediction,

                        'yieldPrediction' =>
                            null,

                        'fullYearPredictions' =>
                            $this->loadFullYearPredictions(
                                $year
                            ),

                        'reportAvailable' =>
                            $this->hasFullYearReport(
                                $year
                            ),

                        'error' =>
                            $weatherPrediction[
                                'message'
                            ]
                            ??
                            'Monthly weather prediction is not available.',
                    ]
                );
            }

            $weather =
                $weatherPrediction[
                    'predictions'
                ];

            /*
            |--------------------------------------------------------------------------
            | Validate Weather
            |--------------------------------------------------------------------------
            */

            $requiredWeather = [
                'rainfall',
                'tmax',
                'tmin',
                'humidity',
                'wind_speed',
                'wind_direction',
            ];

            foreach (
                $requiredWeather
                as $field
            ) {
                if (
                    ! array_key_exists(
                        $field,
                        $weather
                    )
                    ||
                    ! is_numeric(
                        $weather[
                            $field
                        ]
                    )
                ) {
                    throw new \RuntimeException(
                        "Weather model did not return a valid {$field} value."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Step 2: Candidate Yield Input
            |--------------------------------------------------------------------------
            */

            $payload = [
                'month' =>
                    $month,

                'rainfall' =>
                    (float)
                    $weather[
                        'rainfall'
                    ],

                'tmax' =>
                    (float)
                    $weather[
                        'tmax'
                    ],

                'tmin' =>
                    (float)
                    $weather[
                        'tmin'
                    ],

                'rh' =>
                    (float)
                    $weather[
                        'humidity'
                    ],

                'wind_speed' =>
                    (float)
                    $weather[
                        'wind_speed'
                    ],

                'wind_direction' =>
                    (float)
                    $weather[
                        'wind_direction'
                    ],
            ];

            /*
            |--------------------------------------------------------------------------
            | Step 3: Candidate Rice Yield Model
            |--------------------------------------------------------------------------
            */

            $yieldPrediction =
                $this->runCandidateRiceYield(
                    $payload
                );

            $yieldPrediction[
                'year'
            ] = $year;

            $yieldPrediction[
                'month'
            ] = $month;

            $yieldPrediction[
                'month_name'
            ] = $targetMonth->format(
                'F'
            );

            $yieldPrediction[
                'weather_basis'
            ] = 'Ambulong';

            $yieldPrediction[
                'weather_source'
            ] =
                $weatherPrediction[
                    'source_name'
                ]
                ??
                'iClimate Ambulong Monthly Random Forest';

            /*
            |--------------------------------------------------------------------------
            | Save Month to Generated CSVs
            |--------------------------------------------------------------------------
            */

            $this->saveWeatherPrediction(
                $year,
                $month,
                $weatherPrediction
            );

            $this->saveYieldPrediction(
                $year,
                $month,
                $payload,
                $yieldPrediction
            );

            /*
            |--------------------------------------------------------------------------
            | Reload Full-Year Table After Saving
            |--------------------------------------------------------------------------
            */

            $fullYearPredictions =
                $this->loadFullYearPredictions(
                    $year
                );

            return view(
                'testing.candidate-predictions',
                [
                    'year' =>
                        $year,

                    'month' =>
                        $month,

                    'availableYears' =>
                        $this->availableYears(),

                    'weatherPrediction' =>
                        $weatherPrediction,

                    'yieldPrediction' =>
                        $yieldPrediction,

                    'fullYearPredictions' =>
                        $fullYearPredictions,

                    'reportAvailable' =>
                        $fullYearPredictions !== [],

                    'error' =>
                        null,
                ]
            );
        } catch (\Throwable $exception) {
            report(
                $exception
            );

            $fullYearPredictions =
                $this->loadFullYearPredictions(
                    $year
                );

            return view(
                'testing.candidate-predictions',
                [
                    'year' =>
                        $year,

                    'month' =>
                        $month,

                    'availableYears' =>
                        $this->availableYears(),

                    'weatherPrediction' =>
                        $weatherPrediction,

                    'yieldPrediction' =>
                        null,

                    'fullYearPredictions' =>
                        $fullYearPredictions,

                    'reportAvailable' =>
                        $fullYearPredictions !== [],

                    'error' =>
                        $exception->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Load Full-Year Candidate Prediction Report
    |--------------------------------------------------------------------------
    */

    private function loadFullYearPredictions(
        int $year
    ): array {
        $path =
            storage_path(
                "datasets/generated/rice_yield_predictions_{$year}.csv"
            );

        if (
            ! File::exists(
                $path
            )
        ) {
            return [];
        }

        $handle =
            fopen(
                $path,
                'r'
            );

        if (
            $handle === false
        ) {
            return [];
        }

        $headers =
            fgetcsv(
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

            return [];
        }

        $headers =
            array_map(
                fn (
                    $header
                ): string =>
                    strtoupper(
                        trim(
                            (string)
                            $header
                        )
                    ),

                $headers
            );

        $rows = [];

        while (
            (
                $data =
                    fgetcsv(
                        $handle
                    )
            )
            !== false
        ) {
            if (
                count(
                    $headers
                )
                !==
                count(
                    $data
                )
            ) {
                continue;
            }

            $row =
                array_combine(
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

            $month =
                (int)
                (
                    $row[
                        'MONTH'
                    ]
                    ?? 0
                );

            if (
                $month < 1
                ||
                $month > 12
            ) {
                continue;
            }

            $rows[] = [
                'year' =>
                    (int)
                    (
                        $row[
                            'YEAR'
                        ]
                        ?? $year
                    ),

                'month' =>
                    $month,

                'month_name' =>
                    $row[
                        'MONTH_NAME'
                    ]
                    ??
                    CarbonImmutable::createFromDate(
                        $year,
                        $month,
                        1
                    )->format(
                        'F'
                    ),

                'rainfall' =>
                    $this->numericOrNull(
                        $row[
                            'RAINFALL'
                        ]
                        ?? null
                    ),

                'tmax' =>
                    $this->numericOrNull(
                        $row[
                            'TMAX'
                        ]
                        ?? null
                    ),

                'tmin' =>
                    $this->numericOrNull(
                        $row[
                            'TMIN'
                        ]
                        ?? null
                    ),

                'rh' =>
                    $this->numericOrNull(
                        $row[
                            'RH'
                        ]
                        ?? null
                    ),

                'wind_speed' =>
                    $this->numericOrNull(
                        $row[
                            'WIND_SPEED'
                        ]
                        ?? null
                    ),

                'wind_direction' =>
                    $this->numericOrNull(
                        $row[
                            'WIND_DIRECTION'
                        ]
                        ?? null
                    ),

                'season' =>
                    $row[
                        'SEASON'
                    ]
                    ?? '',

                'predicted_yield' =>
                    $this->numericOrNull(
                        $row[
                            'PREDICTED_YIELD'
                        ]
                        ?? null
                    ),

                'unit' =>
                    $row[
                        'UNIT'
                    ]
                    ?? 't/ha',

                'weather_basis' =>
                    $row[
                        'WEATHER_BASIS'
                    ]
                    ?? 'Ambulong',

                'weather_model' =>
                    $row[
                        'WEATHER_MODEL'
                    ]
                    ?? '',

                'yield_model' =>
                    $row[
                        'YIELD_MODEL'
                    ]
                    ?? '',

                'status' =>
                    $row[
                        'STATUS'
                    ]
                    ?? 'Testing Only',
            ];
        }

        fclose(
            $handle
        );

        usort(
            $rows,
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

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Report Exists
    |--------------------------------------------------------------------------
    */

    private function hasFullYearReport(
        int $year
    ): bool {
        return File::exists(
            storage_path(
                "datasets/generated/rice_yield_predictions_{$year}.csv"
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Convert Numeric CSV Value
    |--------------------------------------------------------------------------
    */

    private function numericOrNull(
        mixed $value
    ): ?float {
        if (
            $value === null
            ||
            $value === ''
            ||
            ! is_numeric(
                $value
            )
        ) {
            return null;
        }

        return (float)
            $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate Python Model
    |--------------------------------------------------------------------------
    */

    private function runCandidateRiceYield(
        array $payload
    ): array {
        $script =
            base_path(
                'python_scripts/predict_rice_yield_candidate.py'
            );

        if (
            ! File::exists(
                $script
            )
        ) {
            throw new \RuntimeException(
                'Candidate rice-yield Python script was not found: '
                .$script
            );
        }

        $model =
            storage_path(
                'models/rice_yield_model_candidate.pkl'
            );

        if (
            ! File::exists(
                $model
            )
        ) {
            throw new \RuntimeException(
                'Candidate rice-yield model was not found: '
                .$model
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Python Binary
        |--------------------------------------------------------------------------
        */

        $pythonBinary = PythonRuntime::executable();

        if (
            $pythonBinary === null || ! File::exists(
                $pythonBinary
            )
        ) {
            throw new \RuntimeException(
                'Python executable was not found: '
                .$pythonBinary
            );
        }

        $json =
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            );

        /*
        |--------------------------------------------------------------------------
        | Python Environment
        |--------------------------------------------------------------------------
        |
        | One cross-platform environment for every ML script (Windows, macOS,
        | Linux VPS): App\Support\PythonRuntime::environment().
        |
        */

        $projectRoot =
            base_path();

        $environment =
            PythonRuntime::environment(
                $pythonBinary
            );

        /*
        |--------------------------------------------------------------------------
        | Process
        |--------------------------------------------------------------------------
        */

        $process =
            new Process(
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

        $process->setIdleTimeout(
            null
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
                'Candidate rice-yield prediction failed. '
                .(
                    $stderr !== ''
                        ? $stderr
                        : $stdout
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Read Output
        |--------------------------------------------------------------------------
        */

        $rawOutput =
            trim(
                $process->getOutput()
            );

        if (
            $rawOutput === ''
        ) {
            throw new \RuntimeException(
                'Candidate rice-yield predictor returned no output.'
            );
        }

        $lines =
            preg_split(
                '/\R/',
                $rawOutput
            );

        $lines =
            array_values(
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

        if (
            $lines === []
        ) {
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

        $result =
            json_decode(
                $jsonLine,
                true
            );

        if (
            ! is_array(
                $result
            )
        ) {
            throw new \RuntimeException(
                'Candidate model returned invalid JSON: '
                .$rawOutput
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
                ??
                'Candidate model prediction failed.'
            );
        }

        if (
            ! isset(
                $result[
                    'predicted_yield'
                ]
            )
            ||
            ! is_numeric(
                $result[
                    'predicted_yield'
                ]
            )
        ) {
            throw new \RuntimeException(
                'Candidate model did not return a valid predicted_yield.'
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Save Weather Prediction
    |--------------------------------------------------------------------------
    */

    private function saveWeatherPrediction(
        int $year,
        int $month,
        array $weatherPrediction
    ): void {
        $directory =
            storage_path(
                'datasets/generated'
            );

        if (
            ! File::isDirectory(
                $directory
            )
        ) {
            File::makeDirectory(
                $directory,
                0755,
                true
            );
        }

        $path =
            $directory
            .DIRECTORY_SEPARATOR
            ."weather_predictions_{$year}.csv";

        $weather =
            $weatherPrediction[
                'predictions'
            ];

        $monthName =
            CarbonImmutable::createFromDate(
                $year,
                $month,
                1
            )->format(
                'F'
            );

        $row = [
            'YEAR' =>
                $year,

            'MONTH' =>
                $month,

            'MONTH_NAME' =>
                $monthName,

            'RAINFALL' =>
                $weather[
                    'rainfall'
                ],

            'TMAX' =>
                $weather[
                    'tmax'
                ],

            'TMIN' =>
                $weather[
                    'tmin'
                ],

            'RH' =>
                $weather[
                    'humidity'
                ],

            'WIND_SPEED' =>
                $weather[
                    'wind_speed'
                ],

            'WIND_DIRECTION' =>
                $weather[
                    'wind_direction'
                ],

            'TEMPERATURE' =>
                $weather[
                    'temperature'
                ]
                ??
                (
                    (
                        $weather[
                            'tmax'
                        ]
                        +
                        $weather[
                            'tmin'
                        ]
                    )
                    /
                    2
                ),

            'SEASON' =>
                $weather[
                    'season'
                ]
                ?? '',

            'TRAINING_PERIOD_FROM' =>
                $weatherPrediction[
                    'training_period'
                ][
                    'from'
                ]
                ?? '',

            'TRAINING_PERIOD_TO' =>
                $weatherPrediction[
                    'training_period'
                ][
                    'to'
                ]
                ?? '',

            'WEATHER_SOURCE' =>
                $weatherPrediction[
                    'source_name'
                ]
                ??
                'iClimate Ambulong Monthly Random Forest',

            'TYPE' =>
                'Model Prediction',
        ];

        $this->upsertCsvRow(
            $path,
            $row,
            [
                'YEAR',
                'MONTH',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Save Yield Prediction
    |--------------------------------------------------------------------------
    */

    private function saveYieldPrediction(
        int $year,
        int $month,
        array $weatherPayload,
        array $yieldPrediction
    ): void {
        $directory =
            storage_path(
                'datasets/generated'
            );

        if (
            ! File::isDirectory(
                $directory
            )
        ) {
            File::makeDirectory(
                $directory,
                0755,
                true
            );
        }

        $path =
            $directory
            .DIRECTORY_SEPARATOR
            ."rice_yield_predictions_{$year}.csv";

        $monthName =
            CarbonImmutable::createFromDate(
                $year,
                $month,
                1
            )->format(
                'F'
            );

        // Encoded the way the candidate model's training data was (see App\Support\RiceSeason).
        $season = \App\Support\RiceSeason::forTrainedYieldModel($month);

        $row = [
            'YEAR' =>
                $year,

            'MONTH' =>
                $month,

            'MONTH_NAME' =>
                $monthName,

            'RAINFALL' =>
                $weatherPayload[
                    'rainfall'
                ],

            'TMAX' =>
                $weatherPayload[
                    'tmax'
                ],

            'TMIN' =>
                $weatherPayload[
                    'tmin'
                ],

            'RH' =>
                $weatherPayload[
                    'rh'
                ],

            'WIND_SPEED' =>
                $weatherPayload[
                    'wind_speed'
                ],

            'WIND_DIRECTION' =>
                $weatherPayload[
                    'wind_direction'
                ],

            'SEASON' =>
                $season,

            'PREDICTED_YIELD' =>
                $yieldPrediction[
                    'predicted_yield'
                ],

            'UNIT' =>
                $yieldPrediction[
                    'unit'
                ]
                ?? 't/ha',

            'WEATHER_BASIS' =>
                'Ambulong',

            'WEATHER_MODEL' =>
                $yieldPrediction[
                    'weather_source'
                ]
                ??
                'iClimate Ambulong Monthly Random Forest',

            'YIELD_MODEL' =>
                $yieldPrediction[
                    'source_name'
                ]
                ??
                'iClimate Candidate Random Forest',

            'STATUS' =>
                $yieldPrediction[
                    'status'
                ]
                ??
                'Testing Only',
        ];

        $this->upsertCsvRow(
            $path,
            $row,
            [
                'YEAR',
                'MONTH',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generic CSV Upsert
    |--------------------------------------------------------------------------
    */

    private function upsertCsvRow(
        string $path,
        array $row,
        array $keys
    ): void {
        $rows = [];

        if (
            File::exists(
                $path
            )
        ) {
            $handle =
                fopen(
                    $path,
                    'r'
                );

            if (
                $handle !== false
            ) {
                $headers =
                    fgetcsv(
                        $handle
                    );

                if (
                    is_array(
                        $headers
                    )
                    &&
                    $headers !== []
                ) {
                    while (
                        (
                            $data =
                                fgetcsv(
                                    $handle
                                )
                        )
                        !== false
                    ) {
                        if (
                            count(
                                $headers
                            )
                            !==
                            count(
                                $data
                            )
                        ) {
                            continue;
                        }

                        $combined =
                            array_combine(
                                $headers,
                                $data
                            );

                        if (
                            is_array(
                                $combined
                            )
                        ) {
                            $rows[] =
                                $combined;
                        }
                    }
                }

                fclose(
                    $handle
                );
            }
        }

        $updated =
            false;

        foreach (
            $rows
            as $index => $existing
        ) {
            $match =
                true;

            foreach (
                $keys
                as $key
            ) {
                if (
                    (string)
                    (
                        $existing[
                            $key
                        ]
                        ?? ''
                    )
                    !==
                    (string)
                    (
                        $row[
                            $key
                        ]
                        ?? ''
                    )
                ) {
                    $match =
                        false;

                    break;
                }
            }

            if (
                $match
            ) {
                $rows[
                    $index
                ] = $row;

                $updated =
                    true;

                break;
            }
        }

        if (
            ! $updated
        ) {
            $rows[] =
                $row;
        }

        usort(
            $rows,
            function (
                array $a,
                array $b
            ): int {
                $yearCompare =
                    (
                        (int)
                        (
                            $a[
                                'YEAR'
                            ]
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int)
                        (
                            $b[
                                'YEAR'
                            ]
                            ?? 0
                        )
                    );

                if (
                    $yearCompare !== 0
                ) {
                    return $yearCompare;
                }

                return
                    (
                        (int)
                        (
                            $a[
                                'MONTH'
                            ]
                            ?? 0
                        )
                    )
                    <=>
                    (
                        (int)
                        (
                            $b[
                                'MONTH'
                            ]
                            ?? 0
                        )
                    );
            }
        );

        $handle =
            fopen(
                $path,
                'w'
            );

        if (
            $handle === false
        ) {
            throw new \RuntimeException(
                'Unable to write generated CSV: '
                .$path
            );
        }

        $headers =
            array_keys(
                $row
            );

        fputcsv(
            $handle,
            $headers
        );

        foreach (
            $rows
            as $csvRow
        ) {
            $orderedRow = [];

            foreach (
                $headers
                as $header
            ) {
                $orderedRow[] =
                    $csvRow[
                        $header
                    ]
                    ?? '';
            }

            fputcsv(
                $handle,
                $orderedRow
            );
        }

        fclose(
            $handle
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Available Years
    |--------------------------------------------------------------------------
    */

    private function availableYears(): array
    {
        return [
            2025,
            2026,
            2027,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Year
    |--------------------------------------------------------------------------
    */

    private function normalizeYear(
        int $year
    ): int {
        $available =
            $this->availableYears();

        return in_array(
            $year,
            $available,
            true
        )
            ? $year
            : 2026;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Month
    |--------------------------------------------------------------------------
    */

    private function normalizeMonth(
        int $month
    ): int {
        return (
            $month >= 1
            &&
            $month <= 12
        )
            ? $month
            : \App\Support\BusinessClock::month();
    }
}
