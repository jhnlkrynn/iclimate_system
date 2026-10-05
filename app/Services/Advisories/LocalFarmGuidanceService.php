<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Services\Weather\OpenMeteoService;
use App\Support\LianBarangays;
use App\Support\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Refresh bounded, traceable farm guidance from existing weather services. */
class LocalFarmGuidanceService
{
    public const STATUS_KEY = 'advisories:local-weather-status';

    private const BARANGAY_REFRESH_PREFIX = 'advisories:barangay-refresh:';

    public function __construct(
        private readonly OpenMeteoService $weather,
        private readonly AdvisoryRuleEngine $rules,
        private readonly LianBarangayWeatherService $barangayWeather,
        private readonly FarmerDashboardWeatherService $farmerWeather,
    ) {}

    public static function needsBarangayRefresh(?string $barangay): bool
    {
        $canonical = LianBarangays::canonicalName($barangay);

        return $canonical !== null && ! Cache::has(self::BARANGAY_REFRESH_PREFIX.$canonical);
    }

    public function refresh(?string $barangay = null): array
    {
        $status = Cache::get(self::STATUS_KEY);
        if (! is_array($status) || Carbon::parse($status['checked_at'])->lt(now()->subMinutes(10))) {
            $lock = Cache::lock('advisories:local-refresh-lock', 60);
            if ($lock->get()) {
                try {
                    $weather = $this->weather->fetchForecast(true);
                    // No historical day or stale snapshot may be used as today's forecast.
                    $records = $weather['records']->filter(fn ($row) => $row->forecast_date?->gte(now('Asia/Manila')->startOfDay())
                        && $row->fetched_at?->gte(now()->subHours(6))
                        && is_numeric($row->rainfall_mm) && is_numeric($row->temperature_max));
                    $generated = $records->isNotEmpty()
                        ? $this->rules->generate($records, ['weather_freshness' => $weather['freshness'], 'advisory_horizons' => ['daily'], 'refresh_existing' => true])
                        : ['advisories_created' => 0, 'errors' => ['Current forecast data is temporarily unavailable.']];
                    $status = [
                        'checked_at' => now()->toIso8601String(), 'available' => $records->isNotEmpty(),
                        'source_connected' => (bool) $weather['ok'], 'source' => 'Open-Meteo Forecast API',
                        'source_url' => 'https://open-meteo.com/', 'fetched_at' => $records->first()?->fetched_at?->toIso8601String(),
                        'created' => $generated['advisories_created'], 'updated' => $generated['advisories_updated'] ?? 0,
                        'errors' => array_merge($generated['errors'] ?? [], ! $weather['ok'] ? [$weather['message']] : []),
                    ];
                    Cache::forever(self::STATUS_KEY, $status);
                    PageCache::flushShared();
                    Log::info('Local farm guidance refreshed.', $status);
                } finally {
                    $lock->release();
                }
            }
        }
        $result = $status ?? ['checked_at' => now()->toIso8601String(), 'available' => false, 'refreshing' => true];
        if ($canonical = LianBarangays::canonicalName((string) $barangay)) {
            $result['barangay_weather'] = $this->refreshBarangay($canonical);
        }

        return $result;
    }

    private function refreshBarangay(string $barangay): ?array
    {
        $key = self::BARANGAY_REFRESH_PREFIX.$barangay;
        if (! Cache::add($key, true, now()->addMinutes(10))) {
            return $this->barangayWeather->cachedForBarangay($barangay);
        }
        $weather = $this->barangayWeather->latestForBarangay($barangay);
        if (($weather['is_fresh'] ?? false) !== true || ! is_numeric($weather['temperature_c'] ?? null)
            || ! is_numeric($weather['rainfall_now_mm'] ?? null) || empty($weather['observed_at'])
            || Carbon::parse($weather['observed_at'], 'Asia/Manila')->lt(now()->subHours(6))) {
            Log::warning('Barangay farm guidance unavailable.', ['barangay' => $barangay]);

            return $weather;
        }
        $guidance = $this->farmerWeather->guidance([
            'current' => ['temperature' => $weather['temperature_c'], 'humidity' => $weather['humidity_percent'], 'wind_speed' => $weather['wind_speed_kmh']],
            'today' => ['rainfall' => $weather['rainfall_24h_mm'], 'precipitation_probability' => $weather['precip_probability_percent']],
        ]);
        $topic = $guidance['topic'];
        $title = match ($topic) {
            'rain' => 'Rain Expected in the Next 24 Hours',
            'humidity' => 'High Humidity Crop Check',
            'wind' => 'Wind May Affect Field Spraying',
            'heat' => 'Hot and Dry Field Conditions',
            default => 'Latest Barangay Farm Conditions',
        };
        $severity = match (true) {
            str_contains(strtolower((string) $weather['condition']), 'thunder') => 'high',
            (float) ($weather['rainfall_24h_mm'] ?? 0) >= 25 || (float) ($weather['precip_probability_percent'] ?? 0) >= 80 => 'high',
            $topic !== 'normal' => 'moderate',
            default => 'low',
        };
        // Every number below is the normalized response for this barangay's own centroid.
        $summary = "Current conditions in {$barangay}: {$weather['temperature_c']} °C, {$weather['humidity_percent']}% humidity, "
            ."{$weather['rainfall_now_mm']} mm current precipitation, and {$weather['wind_speed_kmh']} km/h wind. "
            ."For the next 24 hours, expected rainfall is {$weather['rainfall_24h_mm']} mm with up to {$weather['precip_probability_percent']}% rain probability. "
            .$guidance['message'];
        $existing = PlantingAdvisory::query()->where('generation_key', 'local-current:'.$barangay)->first();
        $author = $existing?->posted_by ?? User::query()->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])->value('id') ?? User::query()->value('id');
        if (! $author) {
            return $weather;
        }
        PlantingAdvisory::query()->updateOrCreate(['generation_key' => 'local-current:'.$barangay], [
            'title' => $barangay.' Farm Weather Guidance: '.$title, 'content' => $summary, 'summary' => $summary, 'message' => $summary,
            'recommended_action' => $guidance['message'], 'type' => 'Climate', 'advisory_type' => 'climate',
            'severity' => $severity, 'priority' => ['low' => 25, 'moderate' => 55, 'high' => 80][$severity], 'target_barangay' => $barangay, 'target_scope' => 'barangay',
            'source' => 'Open-Meteo + iClimate Local Guidance', 'source_url' => $weather['source_url'],
            'generated_automatically' => true, 'requires_review' => false, 'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now(), 'valid_until' => now()->addHours(6),
            'published_at' => now(), 'posted_by' => $author,
            'metadata' => ['source_type' => 'iclimate', 'is_official' => false, 'scope_type' => 'barangay',
                'scope_name' => $barangay.', Lian', 'fetched_at' => now()->toIso8601String(), 'weather_basis' => $weather],
        ]);

        return $weather;
    }
}
