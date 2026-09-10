<?php

namespace App\Services;

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
    public function farmingAssistant(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.farming_ai.url'), '/');
        $timeout = (int) config('services.farming_ai.timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/predict', $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('The Farming AI API could not be reached.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('The Farming AI API is unavailable or returned an error.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('The Farming AI API returned an invalid response.');
        }

        return $data;
    }
}
