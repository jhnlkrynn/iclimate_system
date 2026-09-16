<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PythonAnalyticsClient
{
    public function run(string $type, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.farming_ai.url'), '/');
        $connectTimeout = (int) config('services.farming_ai.connect_timeout', 3);
        $timeout = (int) config('services.farming_ai.analytics_timeout', 60);

        try {
            $response = Http::connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/analytics/'.$type, $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('The Python analytics service could not be reached.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Python analytics failed with HTTP '.$response->status().': '.$response->body());
        }

        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('Python analytics returned invalid JSON.');
        }

        return $result;
    }
}
