<?php

namespace Tests\Feature\MachineLearning;

use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EndToEndRiceYieldHoldoutTest extends TestCase
{
    public function test_2025_end_to_end_weather_to_rice_yield_holdout(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Purpose
        |--------------------------------------------------------------------------
        |
        | TRUE deployment-like validation:
        |
        | Ambulong weather data <= December 2024
        |              ↓
        | Weather Random Forest
        |              ↓
        | Predicted 2025 weather
        |              ↓
        | Candidate Rice Yield Random Forest
        |              ↓
        | Compare with actual 2025 rice yield
        |
        | IMPORTANT:
        | The weather model is NOT allowed to see 2025 weather.
        |
        */

        $trainingCutoff = CarbonImmutable::create(
            2024,
            12,
            31
        )->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Load Actual 2025 Rice Yield
        |--------------------------------------------------------------------------
        */

        $actualYieldRows = $this->loadActual2025Yield();

        $this->assertNotEmpty(
            $actualYieldRows,
            'No usable 2025 actual rice-yield rows were found.'
        );

        /*
        |--------------------------------------------------------------------------
        | Weather Model
        |--------------------------------------------------------------------------
        */

        $weatherModel = app(
            MonthlyWeatherRandomForest::class
        );

        $results = [];

        foreach ($actualYieldRows as $actualRow) {
            $month = (int) $actualRow['month'];

            $targetMonth = CarbonImmutable::createFromDate(
                2025,
                $month,
                1
            )->startOfMonth();

            /*
            |--------------------------------------------------------------------------
            | STEP 1
            | Predict 2025 weather using ONLY <= December 2024 training data
            |--------------------------------------------------------------------------
            */

            $weatherPrediction = $weatherModel->predict(
                $targetMonth,
                $trainingCutoff
            );

            $this->assertTrue(
                $weatherPrediction['ready'] ?? false,
                $weatherPrediction['message']
                    ?? "Weather model was not ready for month {$month}."
            );

            /*
            |--------------------------------------------------------------------------
            | Verify Strict No-Leakage Cutoff
            |--------------------------------------------------------------------------
            */

            $this->assertSame(
                '2024-12-31',
                $weatherPrediction['training_cutoff']
            );

            $this->assertSame(
                '2024-12',
                $weatherPrediction['training_period']['to']
            );

            $weather = $weatherPrediction['predictions'];

            $this->assertWeatherPredictionIsValid(
                $weather,
                $month
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
                    (float) $weather['rainfall'],

                'tmax' =>
                    (float) $weather['tmax'],

                'tmin' =>
                    (float) $weather['tmin'],

                'rh' =>
                    (float) $weather['humidity'],

                'wind_speed' =>
                    (float) $weather['wind_speed'],

                'wind_direction' =>
                    (float) $weather['wind_direction'],
            ];

            /*
            |--------------------------------------------------------------------------
            | STEP 3
            | Run Candidate Rice Yield Random Forest
            |--------------------------------------------------------------------------
            */

            $yieldPrediction = $this->runCandidateRiceYield(
                $payload
            );

            $this->assertTrue(
                $yieldPrediction['success'] ?? false,
                $yieldPrediction['message']
                    ?? "Candidate rice-yield model failed for month {$month}."
            );

            $this->assertArrayHasKey(
                'predicted_yield',
                $yieldPrediction
            );

            $actualYield =
                (float) $actualRow['actual_yield'];

            $predictedYield =
                (float) $yieldPrediction['predicted_yield'];

            $signedError =
                $predictedYield
                -
                $actualYield;

            $absoluteError =
                abs(
                    $signedError
                );

            $results[] = [
                'month' =>
                    $month,

                'month_name' =>
                    $targetMonth->format('F'),

                /*
                |--------------------------------------------------------------------------
                | Predicted Weather
                |--------------------------------------------------------------------------
                */

                'rainfall' =>
                    (float) $weather['rainfall'],

                'tmax' =>
                    (float) $weather['tmax'],

                'tmin' =>
                    (float) $weather['tmin'],

                'rh' =>
                    (float) $weather['humidity'],

                'wind_speed' =>
                    (float) $weather['wind_speed'],

                'wind_direction' =>
                    (float) $weather['wind_direction'],

                /*
                |--------------------------------------------------------------------------
                | Yield
                |--------------------------------------------------------------------------
                */

                'actual_yield' =>
                    $actualYield,

                'predicted_yield' =>
                    $predictedYield,

                'signed_error' =>
                    $signedError,

                'absolute_error' =>
                    $absoluteError,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Sort by Month
        |--------------------------------------------------------------------------
        */

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
        | Print Per-Month Results
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."================================================================================"
            .PHP_EOL
            ."TRUE END-TO-END 2025 HOLDOUT"
            .PHP_EOL
            ."WEATHER TRAINING : <= DECEMBER 2024"
            .PHP_EOL
            ."WEATHER TESTING  : 2025"
            .PHP_EOL
            ."YIELD MODEL      : CANDIDATE RANDOM FOREST"
            .PHP_EOL
            ."WEATHER INPUTS   : MODEL-PREDICTED, NOT ACTUAL 2025 WEATHER"
            .PHP_EOL
            ."================================================================================"
            .PHP_EOL
        );

        foreach ($results as $row) {
            fwrite(
                STDERR,
                sprintf(
                    "%-9s | "
                    ."Rain %7.2f | "
                    ."TMAX %5.2f | "
                    ."TMIN %5.2f | "
                    ."RH %5.2f | "
                    ."Wind %4.2f | "
                    ."Dir %6.2f | "
                    ."Actual Yield %.4f -> Predicted %.4f | "
                    ."Abs Error %.4f"
                    .PHP_EOL,

                    $row['month_name'],

                    $row['rainfall'],

                    $row['tmax'],

                    $row['tmin'],

                    $row['rh'],

                    $row['wind_speed'],

                    $row['wind_direction'],

                    $row['actual_yield'],

                    $row['predicted_yield'],

                    $row['absolute_error'],
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Final Yield Metrics
        |--------------------------------------------------------------------------
        */

        $actual = array_column(
            $results,
            'actual_yield'
        );

        $predicted = array_column(
            $results,
            'predicted_yield'
        );

        $metrics = $this->regressionMetrics(
            $actual,
            $predicted
        );

        /*
        |--------------------------------------------------------------------------
        | Print Final Metrics
        |--------------------------------------------------------------------------
        */

        fwrite(
            STDERR,
            PHP_EOL
            ."END-TO-END RICE YIELD METRICS"
            .PHP_EOL
            ."--------------------------------------------------------------------------------"
            .PHP_EOL
        );

        fwrite(
            STDERR,
            sprintf(
                "MAE  : %.4f t/ha"
                .PHP_EOL,
                $metrics['mae']
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "RMSE : %.4f t/ha"
                .PHP_EOL,
                $metrics['rmse']
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "R²   : %.4f"
                .PHP_EOL,
                $metrics['r2']
            )
        );

        fwrite(
            STDERR,
            PHP_EOL
            ."REFERENCE — CANDIDATE MODEL USING ACTUAL 2025 WEATHER"
            .PHP_EOL
            ."--------------------------------------------------------------------------------"
            .PHP_EOL
            ."MAE  : 0.3944 t/ha"
            .PHP_EOL
            ."RMSE : 0.4690 t/ha"
            .PHP_EOL
            ."R²   : -1.1043"
            .PHP_EOL
        );

        /*
        |--------------------------------------------------------------------------
        | Difference Against Previous Candidate Validation
        |--------------------------------------------------------------------------
        */

        $referenceMae =
            0.3944;

        $referenceRmse =
            0.4690;

        $referenceR2 =
            -1.1043;

        fwrite(
            STDERR,
            PHP_EOL
            ."CHANGE WHEN USING PREDICTED WEATHER"
            .PHP_EOL
            ."--------------------------------------------------------------------------------"
            .PHP_EOL
        );

        fwrite(
            STDERR,
            sprintf(
                "MAE change  : %+.4f"
                .PHP_EOL,
                $metrics['mae']
                -
                $referenceMae
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "RMSE change : %+.4f"
                .PHP_EOL,
                $metrics['rmse']
                -
                $referenceRmse
            )
        );

        fwrite(
            STDERR,
            sprintf(
                "R² change   : %+.4f"
                .PHP_EOL,
                $metrics['r2']
                -
                $referenceR2
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
        | We intentionally do NOT set arbitrary accuracy thresholds yet.
        |
        | First, we measure the real chained performance.
        |
        */

        $this->assertGreaterThan(
            0,
            count($results)
        );

        $this->assertIsFloat(
            $metrics['mae']
        );

        $this->assertIsFloat(
            $metrics['rmse']
        );

        $this->assertIsFloat(
            $metrics['r2']
        );

        $this->assertGreaterThanOrEqual(
            0,
            $metrics['mae']
        );

        $this->assertGreaterThanOrEqual(
            0,
            $metrics['rmse']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Actual 2025 Yield
    |--------------------------------------------------------------------------
    |
    | Source:
    |
    | storage/datasets/iClimate_Modeling_Ready_TimeAware.csv
    |
    | Required:
    | YEAR
    | MONTH
    | AVERAGE_YIELD
    |
    */

    private function loadActual2025Yield(): array
    {
        $path = storage_path(
            'datasets/iClimate_Modeling_Ready_TimeAware.csv'
        );

        if (! File::exists($path)) {
            throw new \RuntimeException(
                'Modeling dataset was not found: '
                .$path
            );
        }

        $handle = fopen(
            $path,
            'r'
        );

        if ($handle === false) {
            throw new \RuntimeException(
                'Unable to open modeling dataset: '
                .$path
            );
        }

        $headers = fgetcsv(
            $handle
        );

        if (
            ! is_array($headers)
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
            fn ($header) =>
                strtoupper(
                    trim(
                        (string) $header
                    )
                ),
            $headers
        );

        $requiredHeaders = [
            'YEAR',
            'MONTH',
            'AVERAGE_YIELD',
        ];

        foreach ($requiredHeaders as $required) {
            if (
                ! in_array(
                    $required,
                    $headers,
                    true
                )
            ) {
                fclose(
                    $handle
                );

                throw new \RuntimeException(
                    "Required modeling column {$required} was not found."
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
                count($data)
                !==
                count($headers)
            ) {
                continue;
            }

            $row = array_combine(
                $headers,
                $data
            );

            if (! is_array($row)) {
                continue;
            }

            $year =
                trim(
                    (string) (
                        $row['YEAR']
                        ?? ''
                    )
                );

            $month =
                trim(
                    (string) (
                        $row['MONTH']
                        ?? ''
                    )
                );

            $yield =
                trim(
                    (string) (
                        $row['AVERAGE_YIELD']
                        ?? ''
                    )
                );

            if (
                $year === ''
                ||
                $month === ''
                ||
                $yield === ''
            ) {
                continue;
            }

            if (
                ! is_numeric($year)
                ||
                ! is_numeric($month)
                ||
                ! is_numeric($yield)
            ) {
                continue;
            }

            if ((int) $year !== 2025) {
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
                    2025,

                'month' =>
                    $monthNumber,

                'actual_yield' =>
                    (float) $yield,
            ];
        }

        fclose(
            $handle
        );

        /*
        |--------------------------------------------------------------------------
        | If Dataset Has Duplicate Month Rows
        |--------------------------------------------------------------------------
        |
        | Average the actual yield per month.
        |
        */

        $grouped = [];

        foreach ($rows as $row) {
            $month =
                $row['month'];

            $grouped[$month] ??= [];

            $grouped[$month][] =
                $row['actual_yield'];
        }

        $final = [];

        foreach ($grouped as $month => $values) {
            $final[] = [
                'year' =>
                    2025,

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
            $final,
            fn (
                array $a,
                array $b
            ): int =>
                $a['month']
                <=>
                $b['month']
        );

        return $final;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Weather Prediction
    |--------------------------------------------------------------------------
    */

    private function assertWeatherPredictionIsValid(
        array $weather,
        int $month
    ): void {
        $required = [
            'rainfall',
            'tmax',
            'tmin',
            'humidity',
            'wind_speed',
            'wind_direction',
        ];

        foreach ($required as $field) {
            $this->assertArrayHasKey(
                $field,
                $weather,
                "Weather prediction for month {$month} is missing {$field}."
            );

            $this->assertTrue(
                is_numeric(
                    $weather[$field]
                ),
                "Weather prediction {$field} for month {$month} is not numeric."
            );
        }

        $this->assertGreaterThanOrEqual(
            0,
            (float) $weather['rainfall']
        );

        $this->assertGreaterThanOrEqual(
            (float) $weather['tmin'],
            (float) $weather['tmax']
        );

        $this->assertGreaterThanOrEqual(
            0,
            (float) $weather['humidity']
        );

        $this->assertLessThanOrEqual(
            100,
            (float) $weather['humidity']
        );

        $this->assertGreaterThanOrEqual(
            0,
            (float) $weather['wind_speed']
        );

        $this->assertGreaterThanOrEqual(
            0,
            (float) $weather['wind_direction']
        );

        $this->assertLessThanOrEqual(
            360,
            (float) $weather['wind_direction']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Run Candidate Rice Yield Python Predictor
    |--------------------------------------------------------------------------
    */

    private function runCandidateRiceYield(
        array $payload
    ): array {
        $script = base_path(
            'python_scripts/predict_rice_yield_candidate.py'
        );

        if (! File::exists($script)) {
            throw new \RuntimeException(
                'Candidate predictor script was not found: '
                .$script
            );
        }

        $model = storage_path(
            'models/rice_yield_model_candidate.pkl'
        );

        if (! File::exists($model)) {
            throw new \RuntimeException(
                'Candidate model was not found: '
                .$model
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Use Project Python 3.12 Virtual Environment
        |--------------------------------------------------------------------------
        */

        $pythonBinary = base_path(
            '.venv/Scripts/python.exe'
        );

        if (! File::exists($pythonBinary)) {
            throw new \RuntimeException(
                'Python virtual-environment executable was not found: '
                .$pythonBinary
            );
        }

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        /*
        |--------------------------------------------------------------------------
        | Clean Python Environment
        |--------------------------------------------------------------------------
        |
        | Same protection used by the candidate controller to avoid
        | Windows/XAMPP DLL and WinError 10106 issues.
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

        $cleanPath = implode(
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
            | Keep Prediction Single-Process
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
            | User Environment
            |--------------------------------------------------------------------------
            */

            'USERPROFILE' =>
                getenv('USERPROFILE')
                ?: 'C:\Users\Luke',

            'LOCALAPPDATA' =>
                getenv('LOCALAPPDATA')
                ?: 'C:\Users\Luke\AppData\Local',

            'APPDATA' =>
                getenv('APPDATA')
                ?: 'C:\Users\Luke\AppData\Roaming',

            'TEMP' =>
                getenv('TEMP')
                ?: sys_get_temp_dir(),

            'TMP' =>
                getenv('TMP')
                ?: sys_get_temp_dir(),
        ];

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

        if (! $process->isSuccessful()) {
            $stderr = trim(
                $process->getErrorOutput()
            );

            $stdout = trim(
                $process->getOutput()
            );

            throw new \RuntimeException(
                'Candidate rice-yield Python prediction failed: '
                .(
                    $stderr !== ''
                        ? $stderr
                        : $stdout
                )
            );
        }

        $output = trim(
            $process->getOutput()
        );

        if ($output === '') {
            throw new \RuntimeException(
                'Candidate predictor returned no output.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Last Non-Empty Output Line Should Be JSON
        |--------------------------------------------------------------------------
        */

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
                fn ($line) =>
                    $line !== ''
            )
        );

        if ($lines === []) {
            throw new \RuntimeException(
                'Candidate predictor returned no usable output.'
            );
        }

        $jsonLine = $lines[
            count($lines)
            -
            1
        ];

        $result = json_decode(
            $jsonLine,
            true
        );

        if (! is_array($result)) {
            throw new \RuntimeException(
                'Candidate predictor returned invalid JSON: '
                .$output
            );
        }

        if (
            ! (
                $result['success']
                ?? false
            )
        ) {
            throw new \RuntimeException(
                $result['message']
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
                'Actual and predicted arrays must have the same non-zero length.'
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
}