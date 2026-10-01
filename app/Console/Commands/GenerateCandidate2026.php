<?php

namespace App\Console\Commands;

use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class GenerateCandidate2026 extends Command
{
    /*
    |--------------------------------------------------------------------------
    | Command
    |--------------------------------------------------------------------------
    */

    protected $signature =
        'iclimate:generate-candidate-2026';

    protected $description =
        'Generate January to December 2026 Ambulong weather predictions and candidate rice-yield predictions.';

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    private const TARGET_YEAR = 2026;

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    */

    public function handle(
        MonthlyWeatherRandomForest $weatherModel
    ): int {
        $year =
            self::TARGET_YEAR;

        $this->newLine();

        $this->info(
            '============================================================'
        );

        $this->info(
            'iCLIMATE 2026 CANDIDATE FORECAST GENERATOR'
        );

        $this->info(
            '============================================================'
        );

        $this->line(
            'Weather Model : Ambulong Monthly Random Forest'
        );

        $this->line(
            'Yield Model   : Candidate Random Forest'
        );

        $this->line(
            'Target Year   : '.$year
        );

        $this->line(
            'Status        : TESTING ONLY'
        );

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Verify Required Files
        |--------------------------------------------------------------------------
        */

        try {
            $this->verifyRequiredFiles();
        } catch (\Throwable $exception) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Prediction Rows
        |--------------------------------------------------------------------------
        */

        $weatherRows = [];

        $yieldRows = [];

        /*
        |--------------------------------------------------------------------------
        | January to December
        |--------------------------------------------------------------------------
        */

        for (
            $month = 1;
            $month <= 12;
            $month++
        ) {
            $targetMonth =
                CarbonImmutable::createFromDate(
                    $year,
                    $month,
                    1
                )->startOfMonth();

            $monthName =
                $targetMonth->format(
                    'F'
                );

            $this->line(
                "Generating {$monthName} {$year}..."
            );

            try {
                /*
                |--------------------------------------------------------------------------
                | STEP 1
                | Weather Random Forest
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
                    throw new \RuntimeException(
                        $weatherPrediction[
                            'message'
                        ]
                        ??
                        "Weather prediction unavailable for {$monthName} {$year}."
                    );
                }

                $weather =
                    $weatherPrediction[
                        'predictions'
                    ];

                $this->validateWeather(
                    $weather,
                    $monthName
                );

                /*
                |--------------------------------------------------------------------------
                | STEP 2
                | Build Candidate Rice Yield Input
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
                | STEP 3
                | Candidate Rice Yield Random Forest
                |--------------------------------------------------------------------------
                */

                $yieldPrediction =
                    $this->runCandidateRiceYield(
                        $payload
                    );

                if (
                    ! (
                        $yieldPrediction[
                            'success'
                        ]
                        ?? false
                    )
                ) {
                    throw new \RuntimeException(
                        $yieldPrediction[
                            'message'
                        ]
                        ??
                        "Candidate yield prediction failed for {$monthName} {$year}."
                    );
                }

                if (
                    ! isset(
                        $yieldPrediction[
                            'predicted_yield'
                        ]
                    )
                    ||
                    ! is_numeric(
                        $yieldPrediction[
                            'predicted_yield'
                        ]
                    )
                ) {
                    throw new \RuntimeException(
                        "Candidate model returned an invalid yield for {$monthName} {$year}."
                    );
                }

                $predictedYield =
                    (float)
                    $yieldPrediction[
                        'predicted_yield'
                    ];

                /*
                |--------------------------------------------------------------------------
                | Weather CSV Row
                |--------------------------------------------------------------------------
                */

                $weatherRows[] = [
                    'YEAR' =>
                        $year,

                    'MONTH' =>
                        $month,

                    'MONTH_NAME' =>
                        $monthName,

                    'RAINFALL' =>
                        (float)
                        $weather[
                            'rainfall'
                        ],

                    'TMAX' =>
                        (float)
                        $weather[
                            'tmax'
                        ],

                    'TMIN' =>
                        (float)
                        $weather[
                            'tmin'
                        ],

                    'RH' =>
                        (float)
                        $weather[
                            'humidity'
                        ],

                    'WIND_SPEED' =>
                        (float)
                        $weather[
                            'wind_speed'
                        ],

                    'WIND_DIRECTION' =>
                        (float)
                        $weather[
                            'wind_direction'
                        ],

                    'TEMPERATURE' =>
                        (float)
                        (
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
                            )
                        ),

                    'SEASON' =>
                        $weather[
                            'season'
                        ]
                        ??
                        '',

                    'TRAINING_PERIOD_FROM' =>
                        $weatherPrediction[
                            'training_period'
                        ][
                            'from'
                        ]
                        ??
                        '',

                    'TRAINING_PERIOD_TO' =>
                        $weatherPrediction[
                            'training_period'
                        ][
                            'to'
                        ]
                        ??
                        '',

                    'WEATHER_SOURCE' =>
                        $weatherPrediction[
                            'source_name'
                        ]
                        ??
                        'iClimate Ambulong Monthly Random Forest',

                    'TYPE' =>
                        'Model Prediction',
                ];

                /*
                |--------------------------------------------------------------------------
                | Yield CSV Row
                |--------------------------------------------------------------------------
                */

                $yieldRows[] = [
                    'YEAR' =>
                        $year,

                    'MONTH' =>
                        $month,

                    'MONTH_NAME' =>
                        $monthName,

                    'RAINFALL' =>
                        (float)
                        $weather[
                            'rainfall'
                        ],

                    'TMAX' =>
                        (float)
                        $weather[
                            'tmax'
                        ],

                    'TMIN' =>
                        (float)
                        $weather[
                            'tmin'
                        ],

                    'RH' =>
                        (float)
                        $weather[
                            'humidity'
                        ],

                    'WIND_SPEED' =>
                        (float)
                        $weather[
                            'wind_speed'
                        ],

                    'WIND_DIRECTION' =>
                        (float)
                        $weather[
                            'wind_direction'
                        ],

                    'SEASON' =>
                        $weather[
                            'season'
                        ]
                        ??
                        '',

                    'PREDICTED_YIELD' =>
                        round(
                            $predictedYield,
                            4
                        ),

                    'UNIT' =>
                        $yieldPrediction[
                            'unit'
                        ]
                        ??
                        't/ha',

                    'WEATHER_BASIS' =>
                        'Ambulong',

                    'WEATHER_MODEL' =>
                        $weatherPrediction[
                            'source_name'
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

                /*
                |--------------------------------------------------------------------------
                | Console Result
                |--------------------------------------------------------------------------
                */

                $this->info(
                    sprintf(
                        '  ✓ %-9s | Rain %7.2f mm | TMAX %5.2f | TMIN %5.2f | Yield %.4f t/ha',
                        $monthName,
                        (float)
                        $weather[
                            'rainfall'
                        ],
                        (float)
                        $weather[
                            'tmax'
                        ],
                        (float)
                        $weather[
                            'tmin'
                        ],
                        $predictedYield
                    )
                );
            } catch (\Throwable $exception) {
                $this->newLine();

                $this->error(
                    "Prediction failed for {$monthName} {$year}."
                );

                $this->error(
                    $exception->getMessage()
                );

                $this->newLine();

                $this->warn(
                    'No fabricated values were written.'
                );

                return self::FAILURE;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Require Complete 12-Month Output
        |--------------------------------------------------------------------------
        */

        if (
            count(
                $weatherRows
            )
            !== 12
            ||
            count(
                $yieldRows
            )
            !== 12
        ) {
            $this->error(
                'Generation stopped because a complete 12-month prediction set was not produced.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Save CSV Files
        |--------------------------------------------------------------------------
        */

        try {
            $paths =
                $this->saveReports(
                    $year,
                    $weatherRows,
                    $yieldRows
                );
        } catch (\Throwable $exception) {
            $this->error(
                'Predictions were generated but CSV writing failed.'
            );

            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Summary Table
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            '2026 MONTHLY CANDIDATE RICE YIELD FORECAST'
        );

        $this->newLine();

        $tableRows = [];

        foreach (
            $yieldRows
            as $row
        ) {
            $tableRows[] = [
                $row[
                    'MONTH_NAME'
                ],

                number_format(
                    (float)
                    $row[
                        'RAINFALL'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'TMAX'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'TMIN'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'RH'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'WIND_SPEED'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'WIND_DIRECTION'
                    ],
                    2
                ),

                number_format(
                    (float)
                    $row[
                        'PREDICTED_YIELD'
                    ],
                    4
                ),
            ];
        }

        $this->table(
            [
                'Month',
                'Rainfall',
                'TMAX',
                'TMIN',
                'RH',
                'Wind',
                'Direction',
                'Yield t/ha',
            ],
            $tableRows
        );

        /*
        |--------------------------------------------------------------------------
        | Final Information
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            'Generation completed successfully.'
        );

        $this->newLine();

        $this->line(
            'Weather CSV:'
        );

        $this->line(
            $paths[
                'weather'
            ]
        );

        $this->newLine();

        $this->line(
            'Rice Yield CSV:'
        );

        $this->line(
            $paths[
                'yield'
            ]
        );

        $this->newLine();

        $this->warn(
            'IMPORTANT: These are candidate model estimates for testing and evaluation.'
        );

        $this->warn(
            'They are not observed 2026 weather or actual 2026 rice yield.'
        );

        $this->newLine();

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Required Files
    |--------------------------------------------------------------------------
    */

    private function verifyRequiredFiles(): void
    {
        $pythonBinary =
            $this->pythonBinary();

        $script =
            base_path(
                'python_scripts/predict_rice_yield_candidate.py'
            );

        $model =
            storage_path(
                'models/rice_yield_model_candidate.pkl'
            );

        if (
            ! File::exists(
                $pythonBinary
            )
        ) {
            throw new \RuntimeException(
                'Python virtual environment was not found: '
                .$pythonBinary
            );
        }

        if (
            ! File::exists(
                $script
            )
        ) {
            throw new \RuntimeException(
                'Candidate predictor script was not found: '
                .$script
            );
        }

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
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Weather Prediction
    |--------------------------------------------------------------------------
    */

    private function validateWeather(
        array $weather,
        string $monthName
    ): void {
        $required = [
            'rainfall',
            'tmax',
            'tmin',
            'humidity',
            'wind_speed',
            'wind_direction',
        ];

        foreach (
            $required
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
                    "Invalid {$field} prediction for {$monthName}."
                );
            }
        }

        if (
            (float)
            $weather[
                'rainfall'
            ]
            <
            0
        ) {
            throw new \RuntimeException(
                "Negative rainfall prediction for {$monthName}."
            );
        }

        if (
            (float)
            $weather[
                'tmax'
            ]
            <
            (float)
            $weather[
                'tmin'
            ]
        ) {
            throw new \RuntimeException(
                "TMAX is below TMIN for {$monthName}."
            );
        }

        if (
            (float)
            $weather[
                'humidity'
            ]
            <
            0
            ||
            (float)
            $weather[
                'humidity'
            ]
            >
            100
        ) {
            throw new \RuntimeException(
                "Humidity is outside 0–100% for {$monthName}."
            );
        }

        if (
            (float)
            $weather[
                'wind_speed'
            ]
            <
            0
        ) {
            throw new \RuntimeException(
                "Negative wind speed prediction for {$monthName}."
            );
        }

        if (
            (float)
            $weather[
                'wind_direction'
            ]
            <
            0
            ||
            (float)
            $weather[
                'wind_direction'
            ]
            >
            360
        ) {
            throw new \RuntimeException(
                "Wind direction is outside 0–360 degrees for {$monthName}."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Run Candidate Rice Yield Model
    |--------------------------------------------------------------------------
    */

    private function runCandidateRiceYield(
        array $payload
    ): array {
        $pythonBinary =
            $this->pythonBinary();

        $script =
            base_path(
                'python_scripts/predict_rice_yield_candidate.py'
            );

        $json =
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            );

        /*
        |--------------------------------------------------------------------------
        | Clean Python Environment
        |--------------------------------------------------------------------------
        |
        | This matches the working Laravel integration and avoids the
        | WinError 10106 issue caused by inherited XAMPP environment paths.
        |
        */

        $projectRoot =
            base_path();

        $venvRoot =
            $projectRoot
            .DIRECTORY_SEPARATOR
            .'.venv';

        $venvScripts =
            $venvRoot
            .DIRECTORY_SEPARATOR
            .'Scripts';

        $pythonBase =
            'C:\Users\Luke\AppData\Local\Programs\Python\Python312';

        $pythonScripts =
            $pythonBase
            .DIRECTORY_SEPARATOR
            .'Scripts';

        $windowsRoot =
            getenv(
                'SystemRoot'
            )
            ?: 'C:\Windows';

        $system32 =
            $windowsRoot
            .DIRECTORY_SEPARATOR
            .'System32';

        $cleanPath =
            implode(
                PATH_SEPARATOR,
                [
                    $venvScripts,
                    $pythonBase,
                    $pythonScripts,
                    $system32,
                    $windowsRoot,
                ]
            );

        $environment = [
            'PATH' =>
                $cleanPath,

            'Path' =>
                $cleanPath,

            'SYSTEMROOT' =>
                $windowsRoot,

            'SystemRoot' =>
                $windowsRoot,

            'WINDIR' =>
                $windowsRoot,

            'VIRTUAL_ENV' =>
                $venvRoot,

            /*
            |--------------------------------------------------------------------------
            | Prevent System Python from Polluting the Virtual Environment
            |--------------------------------------------------------------------------
            */

            'PYTHONHOME' =>
                false,

            'PYTHONPATH' =>
                false,

            'PYTHONNOUSERSITE' =>
                '1',

            'PYTHONUTF8' =>
                '1',

            'PYTHONIOENCODING' =>
                'utf-8',

            /*
            |--------------------------------------------------------------------------
            | Keep Candidate Prediction Single Process
            |--------------------------------------------------------------------------
            */

            'JOBLIB_MULTIPROCESSING' =>
                '0',

            'LOKY_MAX_CPU_COUNT' =>
                '1',

            'OMP_NUM_THREADS' =>
                '1',

            'OPENBLAS_NUM_THREADS' =>
                '1',

            'MKL_NUM_THREADS' =>
                '1',

            'NUMEXPR_NUM_THREADS' =>
                '1',

            /*
            |--------------------------------------------------------------------------
            | Windows User Paths
            |--------------------------------------------------------------------------
            */

            'USERPROFILE' =>
                getenv(
                    'USERPROFILE'
                )
                ?: 'C:\Users\Luke',

            'LOCALAPPDATA' =>
                getenv(
                    'LOCALAPPDATA'
                )
                ?: 'C:\Users\Luke\AppData\Local',

            'APPDATA' =>
                getenv(
                    'APPDATA'
                )
                ?: 'C:\Users\Luke\AppData\Roaming',

            'TEMP' =>
                getenv(
                    'TEMP'
                )
                ?: sys_get_temp_dir(),

            'TMP' =>
                getenv(
                    'TMP'
                )
                ?: sys_get_temp_dir(),
        ];

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

        /*
        |--------------------------------------------------------------------------
        | Handle Process Failure
        |--------------------------------------------------------------------------
        */

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
                'Candidate rice-yield prediction failed: '
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

        $output =
            trim(
                $process->getOutput()
            );

        if (
            $output === ''
        ) {
            throw new \RuntimeException(
                'Candidate rice-yield predictor returned no output.'
            );
        }

        $lines =
            preg_split(
                '/\R/',
                $output
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

        /*
        |--------------------------------------------------------------------------
        | Last Non-Empty Line = JSON
        |--------------------------------------------------------------------------
        */

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
                ??
                'Candidate predictor reported failure.'
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Python Binary
    |--------------------------------------------------------------------------
    */

    private function pythonBinary(): string
    {
        /*
        |--------------------------------------------------------------------------
        | Prefer .env
        |--------------------------------------------------------------------------
        */

        $configured =
            config('iclimate.python_binary');

        if (
            is_string(
                $configured
            )
            &&
            trim(
                $configured
            )
            !== ''
        ) {
            return trim(
                $configured,
                " \t\n\r\0\x0B\"'"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Default Project Virtual Environment
        |--------------------------------------------------------------------------
        */

        return base_path(
            '.venv/Scripts/python.exe'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Save Reports
    |--------------------------------------------------------------------------
    */

    private function saveReports(
        int $year,
        array $weatherRows,
        array $yieldRows
    ): array {
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

        $weatherPath =
            $directory
            .DIRECTORY_SEPARATOR
            ."weather_predictions_{$year}.csv";

        $yieldPath =
            $directory
            .DIRECTORY_SEPARATOR
            ."rice_yield_predictions_{$year}.csv";

        /*
        |--------------------------------------------------------------------------
        | Write Complete Fresh Files
        |--------------------------------------------------------------------------
        |
        | Generation is atomic at the command level:
        | all 12 months must succeed before these files are replaced.
        |
        */

        $this->writeCsv(
            $weatherPath,
            $weatherRows
        );

        $this->writeCsv(
            $yieldPath,
            $yieldRows
        );

        return [
            'weather' =>
                $weatherPath,

            'yield' =>
                $yieldPath,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Write CSV
    |--------------------------------------------------------------------------
    */

    private function writeCsv(
        string $path,
        array $rows
    ): void {
        if (
            $rows === []
        ) {
            throw new \RuntimeException(
                'Cannot write an empty CSV file.'
            );
        }

        $temporaryPath =
            $path
            .'.tmp';

        $handle =
            fopen(
                $temporaryPath,
                'w'
            );

        if (
            $handle === false
        ) {
            throw new \RuntimeException(
                'Unable to create temporary CSV file: '
                .$temporaryPath
            );
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | Header
            |--------------------------------------------------------------------------
            */

            $headers =
                array_keys(
                    $rows[
                        0
                    ]
                );

            fputcsv(
                $handle,
                $headers
            );

            /*
            |--------------------------------------------------------------------------
            | Rows
            |--------------------------------------------------------------------------
            */

            foreach (
                $rows
                as $row
            ) {
                $ordered = [];

                foreach (
                    $headers
                    as $header
                ) {
                    $ordered[] =
                        $row[
                            $header
                        ]
                        ?? '';
                }

                fputcsv(
                    $handle,
                    $ordered
                );
            }
        } finally {
            fclose(
                $handle
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Replace Existing Generated File
        |--------------------------------------------------------------------------
        */

        if (
            File::exists(
                $path
            )
        ) {
            File::delete(
                $path
            );
        }

        if (
            ! rename(
                $temporaryPath,
                $path
            )
        ) {
            throw new \RuntimeException(
                'Unable to finalize generated CSV file: '
                .$path
            );
        }
    }
}