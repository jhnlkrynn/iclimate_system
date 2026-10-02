<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

        self::recordAttempt();

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/predict', $payload);
        } catch (Throwable $exception) {
            self::recordFailure();

            throw new RuntimeException('The Farming AI API could not be reached.');
        }

        if (! $response->successful()) {
            self::recordFailure();

            throw new RuntimeException('The Farming AI API is unavailable or returned an error.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            self::recordFailure();

            throw new RuntimeException('The Farming AI API returned an invalid response.');
        }

        return $data;
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
