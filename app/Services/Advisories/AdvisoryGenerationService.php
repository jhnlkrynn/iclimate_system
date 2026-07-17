<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Support\Facades\Log;

class AdvisoryGenerationService
{
    public function __construct(
        private readonly OpenMeteoService $weather,
        private readonly AdvisoryRuleEngine $rules,
    ) {
    }

    public function generate(bool $fetchWeather = false, bool $forceWeather = false): array
    {
        $errors = [];
        $weatherResult = $fetchWeather
            ? $this->weather->fetchForecast($forceWeather)
            : [
                'records' => $this->weather->latestStoredForecast(),
                'records_saved' => 0,
                'freshness' => $this->weather->latestStoredForecast()->first()?->freshnessLabel() ?? 'unavailable',
                'ok' => true,
            ];

        if (! ($weatherResult['ok'] ?? false) && empty($weatherResult['records'])) {
            $errors[] = $weatherResult['message'] ?? 'Unable to retrieve weather data.';
        }

        $expired = $this->expireOutdated();
        $ruleSummary = $this->rules->generate($weatherResult['records'], [
            'weather_freshness' => $weatherResult['freshness'] ?? 'fresh',
            'harvest_ready' => false,
        ]);

        $summary = [
            'weather_records_saved' => $weatherResult['records_saved'] ?? 0,
            'advisories_created' => $ruleSummary['advisories_created'] ?? 0,
            'advisories_skipped_as_duplicates' => $ruleSummary['advisories_skipped_as_duplicates'] ?? 0,
            'advisories_expired' => $expired,
            'errors' => array_values(array_filter(array_merge($errors, $ruleSummary['errors'] ?? []))),
            'weather_freshness' => $weatherResult['freshness'] ?? 'unavailable',
            'last_weather_update' => $weatherResult['fetched_at'] ?? $weatherResult['records']->first()?->fetched_at,
        ];

        Log::info('Agricultural advisory generation completed.', $summary);

        return $summary;
    }

    public function expireOutdated(): int
    {
        return PlantingAdvisory::query()
            ->whereIn('status', [PlantingAdvisory::STATUS_PUBLISHED, PlantingAdvisory::STATUS_PENDING_REVIEW])
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->update(['status' => PlantingAdvisory::STATUS_EXPIRED]);
    }
}
