<?php

namespace App\Services\MachineLearning;

use App\Models\RiceYieldModelComparison;
use App\Services\Prediction\PredictionOrchestrator;
use App\Support\PythonRuntime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs the experimental candidate rice-yield pipeline (Ambulong Monthly
 * Weather Random Forest -> Candidate Rice Yield Random Forest) for a given
 * month and records it next to an already-completed production prediction,
 * purely for offline testing/monitoring. It never changes, blocks, or feeds
 * back into the production AnalyticsRun that triggered it — see
 * RunRiceYieldShadowComparison, which calls this only after the production
 * result is already saved.
 */
class RiceYieldShadowComparisonService
{
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        private readonly PredictionOrchestrator $predictionOrchestrator,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Record One Shadow Comparison
    |--------------------------------------------------------------------------
    */

    public function record(
        int $year,
        int $month,
        float $productionPrediction,
        ?int $userId,
        array $metadata = [],
    ): RiceYieldModelComparison {
        $targetMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();

        // Reads the same centralized/persisted monthly weather forecast the
        // calendar page and barangay risk map use — keeping the shadow
        // candidate's weather inputs consistent with the rest of the system
        // instead of independently retraining MonthlyWeatherRandomForest.
        $weatherPrediction = $this->predictionOrchestrator->monthlyWeatherForecast($targetMonth);

        $weather = [];
        $candidatePrediction = null;
        $candidateSuccess = false;
        $candidateError = null;

        try {
            $weather = $this->resolveWeatherInputs($weatherPrediction);

            $candidateResult = $this->runCandidateRiceYield([
                'month' => $month,
                'rainfall' => $weather['rainfall'],
                'tmax' => $weather['tmax'],
                'tmin' => $weather['tmin'],
                'rh' => $weather['humidity'],
                'wind_speed' => $weather['wind_speed'],
                'wind_direction' => $weather['wind_direction'],
            ]);

            $candidatePrediction = (float) $candidateResult['predicted_yield'];
            $candidateSuccess = true;
        } catch (Throwable $exception) {
            $candidateError = $exception->getMessage();
        }

        $difference = $candidatePrediction !== null
            ? round($candidatePrediction - $productionPrediction, 4)
            : null;

        $absoluteDifference = $difference !== null
            ? abs($difference)
            : null;

        return RiceYieldModelComparison::query()->create([
            'user_id' => $userId,
            'prediction_year' => $year,
            'prediction_month' => $month,
            'production_prediction' => $productionPrediction,
            'production_model' => $metadata['production_result_source'] ?? 'Current Production Model',
            'candidate_prediction' => $candidatePrediction,
            'candidate_model' => 'iClimate Candidate Random Forest',
            'candidate_success' => $candidateSuccess,
            'candidate_status' => 'Testing Only',
            'difference' => $difference,
            'absolute_difference' => $absoluteDifference,
            'rainfall' => $weather['rainfall'] ?? null,
            'tmax' => $weather['tmax'] ?? null,
            'tmin' => $weather['tmin'] ?? null,
            'humidity' => $weather['humidity'] ?? null,
            'wind_speed' => $weather['wind_speed'] ?? null,
            'wind_direction' => $weather['wind_direction'] ?? null,
            'weather_basis' => 'Ambulong',
            'weather_model' => $weatherPrediction['source_name'] ?? 'iClimate Ambulong Monthly Random Forest',
            'weather_training_from' => data_get($weatherPrediction, 'training_period.from'),
            'weather_training_to' => data_get($weatherPrediction, 'training_period.to'),
            'candidate_error' => $candidateError,
            'source' => 'Shadow Comparison',
            'metadata' => $metadata,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate + Extract Weather Inputs
    |--------------------------------------------------------------------------
    */

    private function resolveWeatherInputs(array $weatherPrediction): array
    {
        if (! ($weatherPrediction['ready'] ?? false)) {
            throw new RuntimeException(
                $weatherPrediction['message'] ?? 'Monthly weather prediction is not available.'
            );
        }

        $weather = (array) ($weatherPrediction['predictions'] ?? []);

        $required = ['rainfall', 'tmax', 'tmin', 'humidity', 'wind_speed', 'wind_direction'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $weather) || ! is_numeric($weather[$field])) {
                throw new RuntimeException("Weather model did not return a valid {$field} value.");
            }
        }

        return [
            'rainfall' => (float) $weather['rainfall'],
            'tmax' => (float) $weather['tmax'],
            'tmin' => (float) $weather['tmin'],
            'humidity' => (float) $weather['humidity'],
            'wind_speed' => (float) $weather['wind_speed'],
            'wind_direction' => (float) $weather['wind_direction'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Run Candidate Python Model
    |--------------------------------------------------------------------------
    |
    | The candidate .pkl model is only reachable via this direct script
    | invocation, not through the production Python analytics HTTP service
    | (PythonAnalyticsClient), which has no knowledge of the candidate model.
    */

    private function runCandidateRiceYield(array $payload): array
    {
        $script = base_path('python_scripts/predict_rice_yield_candidate.py');

        if (! File::exists($script)) {
            throw new RuntimeException('Candidate rice-yield Python script was not found: '.$script);
        }

        $model = storage_path('models/rice_yield_model_candidate.pkl');

        if (! File::exists($model)) {
            throw new RuntimeException('Candidate rice-yield model was not found: '.$model);
        }

        $pythonBinary = PythonRuntime::executable();

        if ($pythonBinary === null || ! File::exists($pythonBinary)) {
            throw new RuntimeException('Python executable was not found: '.$pythonBinary);
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $process = new Process([$pythonBinary, $script, $json], base_path());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            throw new RuntimeException(
                'Candidate rice-yield prediction failed. '.($stderr !== '' ? $stderr : $stdout)
            );
        }

        $rawOutput = trim($process->getOutput());

        if ($rawOutput === '') {
            throw new RuntimeException('Candidate rice-yield predictor returned no output.');
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $rawOutput)),
            fn (string $line): bool => $line !== ''
        ));

        if ($lines === []) {
            throw new RuntimeException('Candidate predictor returned no usable output.');
        }

        $result = json_decode($lines[count($lines) - 1], true);

        if (! is_array($result)) {
            throw new RuntimeException('Candidate model returned invalid JSON: '.$rawOutput);
        }

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Candidate model prediction failed.');
        }

        if (! isset($result['predicted_yield']) || ! is_numeric($result['predicted_yield'])) {
            throw new RuntimeException('Candidate model did not return a valid predicted_yield.');
        }

        return $result;
    }
}
