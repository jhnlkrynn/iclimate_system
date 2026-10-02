<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rain for each of the last days in Lian, in mm, from Open-Meteo's daily record (past_days). It is the only daily
 * rain history the system has: the Ambulong station data is monthly.
 *
 * Returns only the days the provider actually gave a number for; a failed call returns an empty list (and is logged
 * with the source name), never zeros. A failure is not cached, so the next page load tries again.
 */
class RecentRainService
{
    public const SOURCE = 'Open-Meteo';

    /**
     * @return array{daily: array<string, float>, fetched_at: ?\Carbon\CarbonImmutable}
     */
    public function history(bool $force = false): array
    {
        $key = 'weather.recent-rain.lian';
        if ($force) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['daily'])) {
            return ['daily' => $cached['daily'], 'fetched_at' => $cached['fetched_at'] ?? null];
        }

        $fetched = $this->fetch();
        if ($fetched === null) {
            return ['daily' => [], 'fetched_at' => null];
        }

        $result = ['daily' => $fetched, 'fetched_at' => now()->toImmutable()];
        Cache::put($key, $result, now()->addMinutes((int) config('farmer_dashboard.rain_history.cache_minutes', 180)));

        return $result;
    }

    /** @return array<string, float>|null */
    private function fetch(): ?array
    {
        $timezone = (string) config('services.weather.timezone', 'Asia/Manila');

        try {
            $response = Http::timeout((float) config('services.open_meteo.timeout', 10))->acceptJson()
                ->get(rtrim((string) config('services.open_meteo.base_url'), '/').'/forecast', [
                    'latitude' => config('services.weather.latitude'),
                    'longitude' => config('services.weather.longitude'),
                    'daily' => 'precipitation_sum',
                    'past_days' => (int) config('farmer_dashboard.rain_history.days', 35),
                    'forecast_days' => 1,
                    'timezone' => $timezone,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }

            $dates = (array) $response->json('daily.time', []);
            $amounts = (array) $response->json('daily.precipitation_sum', []);
            $daily = [];
            foreach ($dates as $i => $date) {
                if (is_string($date) && is_numeric($amounts[$i] ?? null)) {
                    $daily[$date] = round((float) $amounts[$i], 1);
                }
            }

            if ($daily === []) {
                throw new \RuntimeException('no daily rain in the response');
            }

            return $daily;
        } catch (Throwable $e) {
            Log::warning('Farmer dashboard rain history failed.', ['source' => self::SOURCE.' past_days', 'message' => $e->getMessage()]);

            return null;
        }
    }
}
