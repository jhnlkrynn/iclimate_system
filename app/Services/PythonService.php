<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * HTTP client for the iClimate ML service (python_scripts/main.py, FastAPI).
 * Method name kept as farmingAssistant() for compatibility with existing
 * callers/tests — it now talks to FastAPI's /predict route, not Flask.
 */
class PythonService
{
    /**
     * Rolling daily attempt/failure counters (Cache, not a table) feeding the
     * IT dashboard's "prediction-service success rate" pipeline-health
     * metric — this call has no other instrumentation anywhere.
     */
    public function farmingAssistant(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.farming_ai.url'), '/');
        $timeout = (int) config('services.farming_ai.timeout', 5);

        // Inside a chat request the wait is capped by what is left of the response budget.
        $timings = app(\App\Services\AI\AiTimings::class);
        if ($timings->isActive()) {
            $timeout = max(1, min($timeout, (int) floor($timings->remainingSeconds() - 1)));
        }

        self::recordAttempt();

        try {
            $response = Http::connectTimeout((int) config('services.farming_ai.connect_timeout', 3))
                ->timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/predict', $payload);
        } catch (Throwable $exception) {
            if ($this->isRiceYield($payload)) {
                try {
                    return $this->runLocalRiceYield($payload['features'] ?? [], $timeout, $exception);
                } catch (Throwable $localException) {
                    Log::warning('Local rice-yield inference failed after FastAPI connection failure.', [
                        'exception' => $localException::class,
                        'message' => $localException->getMessage(),
                    ]);
                    self::recordFailure();
                    throw new RuntimeException('The Farming AI API could not be reached; local rice-yield inference also failed.', previous: $localException);
                }
            }

            self::recordFailure();

            throw new RuntimeException('The Farming AI API could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            if ($this->isRiceYield($payload)) {
                try {
                    return $this->runLocalRiceYield($payload['features'] ?? [], $timeout);
                } catch (Throwable $localException) {
                    Log::warning('Local rice-yield inference failed after FastAPI error response.', [
                        'exception' => $localException::class,
                        'message' => $localException->getMessage(),
                    ]);
                    self::recordFailure();
                    throw new RuntimeException('The Farming AI API is unavailable; local rice-yield inference also failed.', previous: $localException);
                }
            }

            self::recordFailure();

            throw new RuntimeException('The Farming AI API is unavailable or returned an error.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            if ($this->isRiceYield($payload)) {
                try {
                    return $this->runLocalRiceYield($payload['features'] ?? [], $timeout);
                } catch (Throwable $localException) {
                    Log::warning('Local rice-yield inference failed after FastAPI invalid response.', [
                        'exception' => $localException::class,
                        'message' => $localException->getMessage(),
                    ]);
                    self::recordFailure();
                    throw new RuntimeException('The Farming AI API returned an invalid response; local rice-yield inference also failed.', previous: $localException);
                }
            }

            self::recordFailure();

            throw new RuntimeException('The Farming AI API returned an invalid response.');
        }

        return $data;
    }

    private function isRiceYield(array $payload): bool
    {
        return ($payload['prediction_type'] ?? null) === \App\Enums\PredictionType::RICE_YIELD->value;
    }

    /**
     * Local development/degraded-mode inference using the same deployed model artifact and feature contract as FastAPI.
     * This is inference only: predict.py loads rice_yield_model_final.pkl and never trains or changes it.
     *
     * @param  array<string, mixed>  $features
     * @return array<string, mixed>
     */
    private function runLocalRiceYield(array $features, int $timeout, ?Throwable $httpFailure = null): array
    {
        $python = \App\Support\PythonRuntime::executable();
        $script = base_path('python_scripts/predict.py');
        if ($python === null || ! is_file($python) || ! is_file($script)) {
            throw new RuntimeException('The local rice-yield runtime is not available.', previous: $httpFailure);
        }

        $active = rescue(fn () => app(\App\Services\MachineLearning\ModelRegistryService::class)->resolveActive(), null, false);
        $modelPath = $active?->file_path ?? (string) config('prediction.rice_yield.model_path', 'storage/models/rice_yield_model_final.pkl');
        $environment = \App\Support\PythonRuntime::environment($python);
        $environment['RICE_YIELD_MODEL_PATH'] = $modelPath;
        if ($active?->version) {
            $environment['RICE_YIELD_MODEL_VERSION'] = (string) $active->version;
        }

        $process = new Process([
            $python,
            $script,
            json_encode($features, JSON_THROW_ON_ERROR),
        ], base_path(), $environment);
        // Loading pandas/scikit-learn/joblib can exceed the short HTTP timeout
        // on a cold local process. This is still bounded and remains well
        // below the page's after-response/diagnostic request budget.
        $process->setTimeout(max(15, $timeout));
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'no output';
            throw new RuntimeException('Local rice-yield inference failed: '.$detail, previous: $httpFailure);
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', trim($process->getOutput()))), fn (string $line): bool => $line !== ''));
        $result = $lines !== [] ? json_decode($lines[array_key_last($lines)], true) : null;
        if (! is_array($result) || ! is_numeric($result['predicted_yield'] ?? null) || ! is_finite((float) $result['predicted_yield'])) {
            throw new RuntimeException('Local rice-yield inference returned no finite prediction.');
        }

        Log::warning('Using local rice-yield inference because the FastAPI service was unavailable.', [
            'model' => $modelPath,
            'prediction_type' => 'rice_yield',
        ]);

        return $result + [
            'unit' => 'tons/hectare',
            'fallback' => false,
            'source_type' => 'Trained Model',
            'source_name' => 'Local iClimate Random Forest rice yield model',
            'source_credit' => 'Output generated by the local trained iClimate Random Forest model.',
        ];
    }

    public static function attemptsToday(): int
    {
        return (int) Cache::get('metrics:python-service:attempts:'.\App\Support\BusinessClock::today()->toDateString(), 0);
    }

    public static function failuresToday(): int
    {
        return (int) Cache::get('metrics:python-service:failures:'.\App\Support\BusinessClock::today()->toDateString(), 0);
    }

    private static function recordAttempt(): void
    {
        self::bumpCounter('metrics:python-service:attempts:'.\App\Support\BusinessClock::today()->toDateString());
    }

    private static function recordFailure(): void
    {
        self::bumpCounter('metrics:python-service:failures:'.\App\Support\BusinessClock::today()->toDateString());
    }

    private static function bumpCounter(string $key): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addDay());
        }

        Cache::increment($key);
    }
}
