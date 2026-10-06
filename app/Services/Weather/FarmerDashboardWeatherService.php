<?php

namespace App\Services\Weather;

use App\Models\ExternalWeatherData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FarmerDashboardWeatherService
{
    private const OPEN_METEO_CURRENT = [
        'temperature_2m',
        'relative_humidity_2m',
        'apparent_temperature',
        'precipitation',
        'rain',
        'showers',
        'weather_code',
        'cloud_cover',
        'pressure_msl',
        'surface_pressure',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'is_day',
    ];

    private const OPEN_METEO_HOURLY = [
        'temperature_2m',
        'relative_humidity_2m',
        'precipitation_probability',
        'precipitation',
        'rain',
        'weather_code',
    ];

    /** Days asked for in the same request, kept as 'forecast_extended' for the Predictions page (the provider's maximum). */
    public const EXTENDED_FORECAST_DAYS = 16;

    private const OPEN_METEO_DAILY = [
        'weather_code',
        'temperature_2m_max',
        'temperature_2m_min',
        'apparent_temperature_max',
        'apparent_temperature_min',
        'sunrise',
        'sunset',
        'precipitation_sum',
        'rain_sum',
        'showers_sum',
        'precipitation_probability_max',
        'wind_speed_10m_max',
        'wind_gusts_10m_max',
    ];

    public function __construct(private readonly WeatherIconMapper $icons) {}

    /** Per-provider limit (seconds) for the current call; null means the normal, patient settings. */
    private ?float $budget = null;

    /**
     * @param  float|null  $budgetSeconds  interactive callers (the chatbot) pass a short limit: each
     *                                     provider then gets at most that long and no retries. With a
     *                                     warm cache the budget is never used.
     */
    public function current(bool $force = false, ?float $budgetSeconds = null): array
    {
        // A page or API request never calls the provider unless it asked for a refresh (a Refresh button): it shows the
        // stored reading, whatever its age, marked as delayed when older than the dashboard's stale limit.
        // iclimate:refresh-weather-cache keeps it current every 3 minutes. The chatbot passes a budget and keeps its own rule.
        if (! $force && $budgetSeconds === null && \App\Support\InteractiveRequest::isPageRequest()) {
            return $this->storedReading();
        }

        // Who is waiting decides how patient this call may be (see InteractiveRequest). An explicit
        // budget (the chatbot) is always strict and may use a recent stored reading.
        $mode = $budgetSeconds !== null
            ? ['budget' => $budgetSeconds, 'stale_ok' => true]
            : \App\Support\InteractiveRequest::weatherMode();
        $budgetSeconds = $mode['budget'];
        $this->budget = $budgetSeconds;
        $cacheKey = $this->cacheKey();
        $lastSuccessfulKey = $cacheKey.'.last_successful';
        // Never cached longer than the dashboard's own limit, whatever WEATHER_CACHE_MINUTES says.
        $ttl = now($this->timezone())->addMinutes(max(1, min((int) config('services.weather.cache_minutes', 5), (int) config('farmer_dashboard.weather.cache_max_minutes', 30))));

        // One cache read (not has() then get()): on a database cache that is one query instead of two.
        $cached = $force ? null : Cache::get($cacheKey);

        if (is_array($cached)) {
            $cached['cached'] = true;

            return $cached;
        }

        // An interactive caller (the chatbot) is happy with a snapshot a few minutes old: the
        // dashboard's 1-minute cache would otherwise send most chat questions out to the provider.
        if (! $force && $mode['stale_ok']) {
            $recent = Cache::get($lastSuccessfulKey);

            if (is_array($recent) && $this->youngerThan($recent, (int) config('ai.weather_max_age_minutes', 10))) {
                $recent['cached'] = true;

                return $recent;
            }
        }

        try {
            $weather = $this->fetchNormalizedWeather();

            Cache::put($cacheKey, $weather, $ttl);
            Cache::forever($lastSuccessfulKey, $weather);
            $this->storeSnapshot($weather['raw_response'] ?? [], $weather);

            return $weather;
        } catch (Throwable $exception) {
            Log::warning('Farmer dashboard weather fetch failed.', [
                'message' => $exception->getMessage(),
            ]);

            $fallback = Cache::get($lastSuccessfulKey) ?? $this->latestStoredSnapshot();

            if (is_array($fallback)) {
                $fallback['cached'] = true;
                $fallback['stale'] = true;
                $fallback['success'] = true;
                $fallback['status'] = 'stale';
                $fallback['message'] = 'Weather update is delayed. Showing the last available weather.';

                return $fallback;
            }

            return $this->unavailable();
        }
    }

    /** The stored reading for a page request: the cached one, else the last good one (marked delayed when old), else unavailable. */
    private function storedReading(): array
    {
        $cacheKey = $this->cacheKey();
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $cached['cached'] = true;

            return $cached;
        }

        $last = Cache::get($cacheKey.'.last_successful') ?? $this->latestStoredSnapshot();

        if (! is_array($last)) {
            return $this->unavailable();
        }

        $last['cached'] = true;
        if (! $this->youngerThan($last, (int) config('farmer_dashboard.weather.stale_after_minutes', 60))) {
            $last['stale'] = true;
            $last['status'] = 'stale';
            $last['message'] = 'Weather update is delayed. Showing the last available weather.';
        }

        return $last;
    }

    /**
     * The last stored reading (fresh or not), or null if none exists. Never calls a provider; used where a
     * page must render without any outside call, such as the login screen.
     *
     * @return array<string, mixed>|null
     */
    public function stored(): ?array
    {
        $key = $this->cacheKey();
        $snapshot = Cache::get($key) ?? Cache::get($key.'.last_successful');

        return is_array($snapshot) ? $snapshot : null;
    }

    /** Whether a stored snapshot was fetched by this server within the last $minutes minutes. */
    private function youngerThan(array $snapshot, int $minutes): bool
    {
        $fetched = data_get($snapshot, 'timestamps.server_fetched_at') ?? data_get($snapshot, 'server_fetched_at');

        if ($fetched === null || ($snapshot['success'] ?? true) === false) {
            return false;
        }

        try {
            return \Illuminate\Support\Carbon::parse($fetched)->gt(now()->subMinutes(max(1, $minutes)));
        } catch (Throwable) {
            return false;
        }
    }

    public function guidance(array $weather): array
    {
        $current = $weather['current'] ?? [];
        $today = $weather['today'] ?? [];

        if (($current['humidity'] ?? 0) >= 85) {
            return [
                'title' => 'iClimate Weather Guidance',
                'topic' => 'humidity',
                'message' => 'Humid air may bring rice diseases. Check your rice plants for signs.',
            ];
        }

        if (($today['precipitation_probability'] ?? 0) >= 70 || ($today['rainfall'] ?? 0) >= 10) {
            return [
                'title' => 'iClimate Weather Guidance',
                'topic' => 'rain',
                'message' => 'Rain is likely today. Consider delaying fertilizer or pesticide application when possible.',
            ];
        }

        if (($current['wind_speed'] ?? 0) >= 20) {
            return [
                'title' => 'iClimate Weather Guidance',
                'topic' => 'wind',
                'message' => 'Wind conditions may reduce spraying effectiveness. Consider delaying chemical spraying.',
            ];
        }

        // Both readings must exist: a missing rainfall is not "0 mm, dry".
        if (is_numeric($current['temperature'] ?? null) && is_numeric($today['rainfall'] ?? null)
            && (float) $current['temperature'] >= 34 && (float) $today['rainfall'] < 2) {
            return [
                'title' => 'iClimate Weather Guidance',
                'topic' => 'heat',
                'message' => 'Hot and dry conditions may increase water demand. Check soil moisture before irrigation.',
            ];
        }

        return [
            'title' => 'iClimate Weather Guidance',
            'topic' => 'normal',
            'message' => 'Weather looks normal. Check advisories before field work.',
        ];
    }

    public function responsePayload(array $weather): array
    {
        $checkedAt = now($this->timezone());

        return [
            ...$weather,
            'fetched_at' => $this->serializeTime($weather['fetched_at'] ?? null),
            'checked_at' => $checkedAt->toIso8601String(),
            'checked_at_label' => $this->displayTime($checkedAt),
            'dashboard_checked_at' => $checkedAt->toIso8601String(),
            'guidance' => $this->guidance($weather),
        ];
    }

    /**
     * Aligns a municipal Lian forecast payload (the WeatherApiService::forecast()
     * shape used by the MAO dashboard and the weather calendar) with the one
     * shared Lian reading from current(), so every role and page shows the
     * same current temperature, condition and daily forecast values instead of
     * separately fetched and separately cached copies. Barangay-specific
     * forecasts must not be passed through here.
     */
    public function alignForecast(?array $forecast, bool $force = false): ?array
    {
        $shared = $this->current($force);

        if ($forecast === null || ($shared['success'] ?? false) !== true) {
            return $forecast;
        }

        $current = $shared['current'] ?? [];
        $fetchedAt = $shared['fetched_at'] ?? null;

        $forecast = [
            ...$forecast,
            'source' => $shared['provider'] ?? $forecast['source'] ?? 'Open-Meteo',
            'source_name' => ($shared['provider'] ?? 'Weather').' (shared iClimate Lian reading)',
            'current_time' => $fetchedAt instanceof Carbon ? $fetchedAt->toDateTimeString() : ($forecast['current_time'] ?? null),
            'current_condition' => $current['condition'] ?? $forecast['current_condition'] ?? null,
            'current_icon' => $current['icon'] ?? $forecast['current_icon'] ?? null,
            'weather_code' => $current['weather_code'] ?? $forecast['weather_code'] ?? null,
            'current_rainfall_mm' => $current['precipitation'] ?? null,
            'current_temperature_c' => $current['temperature'] ?? null,
            'humidity_percent' => $current['humidity'] ?? null,
            'wind_speed_kmh' => $current['wind_speed'] ?? null,
            'fetched_at' => $this->serializeTime($fetchedAt) ?? ($forecast['fetched_at'] ?? null),
            'freshness' => ($shared['stale'] ?? false) ? 'latest cached update' : 'fresh',
            'is_cached_fallback' => (bool) ($shared['stale'] ?? false),
        ];

        $series = $forecast['daily_series'] ?? null;
        $sharedDays = collect($shared['forecast'] ?? [])->keyBy('date');

        if (! is_array($series) || $sharedDays->isEmpty()) {
            return $forecast;
        }

        foreach (($series['labels'] ?? []) as $index => $date) {
            $day = $sharedDays->get((string) $date);

            if (! is_array($day)) {
                continue;
            }

            $max = $day['temperature_max'] ?? null;
            $min = $day['temperature_min'] ?? null;

            if (is_numeric($max) && is_numeric($min)) {
                $series['temperatureMax'][$index] = (float) $max;
                $series['temperatureMin'][$index] = (float) $min;
                $series['temperature'][$index] = round(((float) $max + (float) $min) / 2, 2);
            }

            if (is_numeric($day['rainfall'] ?? null)) {
                $series['rainfall'][$index] = (float) $day['rainfall'];
            }

            if (is_numeric($day['precipitation_probability'] ?? null)) {
                $series['precipitationProbability'][$index] = (float) $day['precipitation_probability'];
            }

            if (($day['condition'] ?? null) !== null) {
                $series['conditions'][$index] = $day['condition'];
                $series['icons'][$index] = $day['icon'] ?? ($series['icons'][$index] ?? null);
            }
        }

        $days = max(count($series['labels'] ?? []), 1);
        $rainfall = array_map('floatval', $series['rainfall'] ?? []);

        $forecast['daily_series'] = $series;
        $forecast['daily_rainfall_mm'] = round(array_sum($rainfall) / $days, 2);
        $forecast['monthly_rainfall_estimate_mm'] = round((array_sum($rainfall) / $days) * 30, 2);
        $forecast['temperature_c'] = round(array_sum(array_map('floatval', $series['temperature'] ?? [])) / $days, 2);
        $forecast['precip_probability_percent'] = round(array_sum(array_map('floatval', $series['precipitationProbability'] ?? [])) / $days, 2);

        return $forecast;
    }

    private function fetchNormalizedWeather(): array
    {
        $errors = [];

        // WeatherAPI is only tried when it can work: without a key every fetch used to fail first, log a
        // warning, and only then use Open-Meteo.
        if ((string) config('services.weather.provider', 'weatherapi') === 'weatherapi' && (string) config('services.weatherapi.key') !== '') {
            try {
                return $this->normalizeWeatherApi($this->requestWeatherApi());
            } catch (Throwable $exception) {
                $errors[] = 'WeatherAPI: '.$exception->getMessage();
                Log::warning('WeatherAPI fetch failed; trying Open-Meteo fallback.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            return $this->normalizeOpenMeteo($this->requestOpenMeteo(), fallback: $errors !== []);
        } catch (Throwable $exception) {
            $errors[] = 'Open-Meteo: '.$exception->getMessage();
            throw new RuntimeException('All weather providers failed. '.implode(' | ', $errors));
        }
    }

    /**
     * The HTTP client for a provider call. Normally patient (configured timeout,
     * a couple of retries). With a budget it is strict: at most that many seconds
     * and no retries, because a retry would double the wait the person sees.
     */
    private function http(float $timeout, int $retries, int $delayMs): \Illuminate\Http\Client\PendingRequest
    {
        if ($this->budget !== null) {
            return Http::timeout(max(1.0, min($timeout, $this->budget)))->acceptJson();
        }

        return Http::timeout($timeout)->retry($retries, $delayMs)->acceptJson();
    }

    private function requestWeatherApi(): array
    {
        $key = (string) config('services.weatherapi.key');

        if ($key === '') {
            throw new RuntimeException('WeatherAPI key is not configured.');
        }

        $response = $this->http((float) config('services.weatherapi.timeout', 8), 2, 300)
            ->get(rtrim((string) config('services.weatherapi.base_url'), '/').'/forecast.json', [
                'key' => $key,
                'q' => $this->coordinates(),
                'days' => (int) config('services.weather.forecast_days', 7),
                'aqi' => 'no',
                'alerts' => 'no',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('WeatherAPI returned HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || isset($payload['error']) || ! is_array($payload['current'] ?? null)) {
            throw new RuntimeException('WeatherAPI response is missing current weather data.');
        }

        return $payload;
    }

    private function requestOpenMeteo(): array
    {
        $response = $this->http((float) config('services.open_meteo.timeout', 10), 2, 250)
            ->get(rtrim((string) config('services.open_meteo.base_url'), '/').'/forecast', [
                'latitude' => config('services.weather.latitude'),
                'longitude' => config('services.weather.longitude'),
                'current' => implode(',', self::OPEN_METEO_CURRENT),
                'hourly' => implode(',', self::OPEN_METEO_HOURLY),
                'daily' => implode(',', self::OPEN_METEO_DAILY),
                'timezone' => $this->timezone(),
                'forecast_days' => max(self::EXTENDED_FORECAST_DAYS, (int) config('services.weather.forecast_days', 7)),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo returned HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['current'] ?? null) || ! is_array($payload['daily'] ?? null)) {
            throw new RuntimeException('Open-Meteo response is missing current or daily weather data.');
        }

        return $payload;
    }

    private function normalizeWeatherApi(array $payload): array
    {
        $current = $payload['current'] ?? [];
        $forecastDays = $payload['forecast']['forecastday'] ?? [];
        $today = $forecastDays[0]['day'] ?? [];
        $condition = (string) data_get($current, 'condition.text', 'Weather Temporarily Unavailable');
        $code = $this->number(data_get($current, 'condition.code'));
        $mapped = $this->mapWeatherApiCondition($condition, $code !== null ? (int) $code : null, (bool) ($current['is_day'] ?? true));
        $providerUpdatedAt = $this->parseProviderTime($current['last_updated_epoch'] ?? null, $current['last_updated'] ?? null);
        $serverFetchedAt = now($this->timezone());

        $weather = [
            'success' => true,
            'status' => 'success',
            'provider' => 'WeatherAPI',
            'source' => 'WeatherAPI',
            'provider_details' => ['name' => 'WeatherAPI', 'fallback' => false],
            'cached' => false,
            'stale' => false,
            'location' => $this->location(),
            'current' => [
                'temperature' => $this->number($current['temp_c'] ?? null),
                'temperature_c' => $this->number($current['temp_c'] ?? null),
                'feels_like' => $this->number($current['feelslike_c'] ?? null),
                'feels_like_c' => $this->number($current['feelslike_c'] ?? null),
                'humidity' => $this->number($current['humidity'] ?? null),
                'humidity_percent' => $this->number($current['humidity'] ?? null),
                'precipitation' => $this->number($current['precip_mm'] ?? null),
                'precipitation_mm' => $this->number($current['precip_mm'] ?? null),
                'rain' => $this->number($current['precip_mm'] ?? null),
                'weather_code' => $code,
                'condition' => $mapped['condition'],
                'provider_condition' => $condition,
                'is_day' => isset($current['is_day']) ? (bool) $current['is_day'] : null,
                'cloud_cover' => $this->number($current['cloud'] ?? null),
                'cloud_percent' => $this->number($current['cloud'] ?? null),
                'pressure_msl' => $this->number($current['pressure_mb'] ?? null),
                'pressure_mb' => $this->number($current['pressure_mb'] ?? null),
                'visibility_km' => $this->number($current['vis_km'] ?? null),
                'uv' => $this->number($current['uv'] ?? null),
                'wind_speed' => $this->number($current['wind_kph'] ?? null),
                'wind_kph' => $this->number($current['wind_kph'] ?? null),
                'wind_direction' => $current['wind_dir'] ?? null,
                'wind_degree' => $this->number($current['wind_degree'] ?? null),
                'wind_gusts' => $this->number($current['gust_kph'] ?? null),
                'wind_gust_kph' => $this->number($current['gust_kph'] ?? null),
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => $this->number($today['maxtemp_c'] ?? null),
                'max_temperature_c' => $this->number($today['maxtemp_c'] ?? null),
                'temperature_min' => $this->number($today['mintemp_c'] ?? null),
                'min_temperature_c' => $this->number($today['mintemp_c'] ?? null),
                'rainfall' => $this->number($today['totalprecip_mm'] ?? null),
                'rainfall_mm' => $this->number($today['totalprecip_mm'] ?? null),
                'precipitation_probability' => $this->number($today['daily_chance_of_rain'] ?? null),
                'chance_of_rain_percent' => $this->number($today['daily_chance_of_rain'] ?? null),
                'wind_speed_max' => $this->number($today['maxwind_kph'] ?? null),
                'humidity_avg' => $this->number($today['avghumidity'] ?? null),
            ],
            'forecast' => $this->weatherApiForecast($forecastDays),
            'icon' => $mapped['icon'],
            'fetched_at' => $providerUpdatedAt,
            'fetched_at_label' => $providerUpdatedAt ? $this->displayTime($providerUpdatedAt) : 'Update time not available',
            'server_fetched_at' => $serverFetchedAt,
            'server_fetched_at_label' => $this->displayTime($serverFetchedAt),
            'timestamps' => [
                'provider_updated_at' => $providerUpdatedAt?->toIso8601String(),
                'server_fetched_at' => $serverFetchedAt->toIso8601String(),
            ],
            'message' => 'Weather data fetched from WeatherAPI for Lian, Batangas.',
            'raw_response' => $payload,
        ];

        $this->validateNormalized($weather, 'WeatherAPI');

        return $weather;
    }

    private function normalizeOpenMeteo(array $payload, bool $fallback = false): array
    {
        $current = $payload['current'] ?? [];
        $daily = $payload['daily'] ?? [];
        $weatherCode = $this->number($current['weather_code'] ?? $daily['weather_code'][0] ?? null);
        $isDay = isset($current['is_day']) ? (bool) $current['is_day'] : null;
        $mapped = $this->icons->map($weatherCode !== null ? (int) $weatherCode : null, $isDay);
        $providerUpdatedAt = $this->parseProviderTime(null, $current['time'] ?? null);
        $serverFetchedAt = now($this->timezone());

        $weather = [
            'success' => true,
            'status' => 'success',
            'provider' => 'Open-Meteo',
            'source' => 'Open-Meteo',
            'provider_details' => ['name' => 'Open-Meteo', 'fallback' => $fallback],
            'cached' => false,
            'stale' => false,
            'location' => $this->location(),
            'current' => [
                'temperature' => $this->number($current['temperature_2m'] ?? null),
                'temperature_c' => $this->number($current['temperature_2m'] ?? null),
                'feels_like' => $this->number($current['apparent_temperature'] ?? null),
                'feels_like_c' => $this->number($current['apparent_temperature'] ?? null),
                'humidity' => $this->number($current['relative_humidity_2m'] ?? null),
                'humidity_percent' => $this->number($current['relative_humidity_2m'] ?? null),
                'precipitation' => $this->number($current['precipitation'] ?? null),
                'precipitation_mm' => $this->number($current['precipitation'] ?? null),
                'rain' => $this->number($current['rain'] ?? null),
                'showers' => $this->number($current['showers'] ?? null),
                'weather_code' => $weatherCode,
                'condition' => $mapped['condition'],
                'is_day' => $isDay,
                'cloud_cover' => $this->number($current['cloud_cover'] ?? null),
                'cloud_percent' => $this->number($current['cloud_cover'] ?? null),
                'pressure_msl' => $this->number($current['pressure_msl'] ?? null),
                'surface_pressure' => $this->number($current['surface_pressure'] ?? null),
                'pressure_mb' => $this->number($current['pressure_msl'] ?? $current['surface_pressure'] ?? null),
                'wind_speed' => $this->number($current['wind_speed_10m'] ?? null),
                'wind_kph' => $this->number($current['wind_speed_10m'] ?? null),
                'wind_direction' => $this->number($current['wind_direction_10m'] ?? null),
                'wind_degree' => $this->number($current['wind_direction_10m'] ?? null),
                'wind_gusts' => $this->number($current['wind_gusts_10m'] ?? null),
                'wind_gust_kph' => $this->number($current['wind_gusts_10m'] ?? null),
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => $this->number($daily['temperature_2m_max'][0] ?? null),
                'max_temperature_c' => $this->number($daily['temperature_2m_max'][0] ?? null),
                'temperature_min' => $this->number($daily['temperature_2m_min'][0] ?? null),
                'min_temperature_c' => $this->number($daily['temperature_2m_min'][0] ?? null),
                'feels_like_max' => $this->number($daily['apparent_temperature_max'][0] ?? null),
                'feels_like_min' => $this->number($daily['apparent_temperature_min'][0] ?? null),
                'rainfall' => $this->number($daily['precipitation_sum'][0] ?? $daily['rain_sum'][0] ?? null),
                'rainfall_mm' => $this->number($daily['precipitation_sum'][0] ?? $daily['rain_sum'][0] ?? null),
                'rain_sum' => $this->number($daily['rain_sum'][0] ?? null),
                'precipitation_sum' => $this->number($daily['precipitation_sum'][0] ?? null),
                'showers_sum' => $this->number($daily['showers_sum'][0] ?? null),
                'precipitation_probability' => $this->number($daily['precipitation_probability_max'][0] ?? null),
                'chance_of_rain_percent' => $this->number($daily['precipitation_probability_max'][0] ?? null),
                'wind_speed_max' => $this->number($daily['wind_speed_10m_max'][0] ?? null),
                'wind_gusts_max' => $this->number($daily['wind_gusts_10m_max'][0] ?? null),
                'sunrise' => $daily['sunrise'][0] ?? null,
                'sunset' => $daily['sunset'][0] ?? null,
            ],
            // The usual short list (dashboards, chatbot) and the longer one for the Predictions page.
            'forecast' => $this->forecast($daily, (int) config('services.weather.forecast_days', 7)),
            'forecast_extended' => $this->forecast($daily, self::EXTENDED_FORECAST_DAYS),
            'icon' => $mapped['icon'],
            'fetched_at' => $providerUpdatedAt,
            'fetched_at_label' => $providerUpdatedAt ? $this->displayTime($providerUpdatedAt) : 'Update time not available',
            'server_fetched_at' => $serverFetchedAt,
            'server_fetched_at_label' => $this->displayTime($serverFetchedAt),
            'timestamps' => [
                'provider_updated_at' => $providerUpdatedAt?->toIso8601String(),
                'server_fetched_at' => $serverFetchedAt->toIso8601String(),
            ],
            'message' => 'Weather data fetched from Open-Meteo for Lian, Batangas.',
            'raw_response' => $payload,
        ];

        $this->validateNormalized($weather, 'Open-Meteo');

        return $weather;
    }

    private function weatherApiForecast(array $forecastDays): array
    {
        $days = [];

        foreach (array_slice($forecastDays, 0, 7) as $forecastDay) {
            $date = (string) ($forecastDay['date'] ?? '');
            if ($date === '') {
                continue;
            }

            $dayPayload = $forecastDay['day'] ?? [];
            $condition = (string) data_get($dayPayload, 'condition.text', 'Forecast');
            $code = $this->number(data_get($dayPayload, 'condition.code'));
            $mapped = $this->mapWeatherApiCondition($condition, $code !== null ? (int) $code : null, true);
            $day = Carbon::parse($date, $this->timezone());

            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->format('D'),
                'condition' => $mapped['condition'],
                'icon' => $mapped['icon'],
                'temperature_max' => $this->number($dayPayload['maxtemp_c'] ?? null),
                'temperature_min' => $this->number($dayPayload['mintemp_c'] ?? null),
                'rainfall' => $this->number($dayPayload['totalprecip_mm'] ?? null),
                'precipitation_probability' => $this->number($dayPayload['daily_chance_of_rain'] ?? null),
                'wind_speed_max' => $this->number($dayPayload['maxwind_kph'] ?? null),
                'humidity_avg' => $this->number($dayPayload['avghumidity'] ?? null),
            ];
        }

        return $days;
    }

    private function forecast(array $daily, int $limit = 7): array
    {
        $days = [];
        $times = array_slice($daily['time'] ?? [], 0, max(1, $limit));

        foreach ($times as $index => $date) {
            $code = $this->number($daily['weather_code'][$index] ?? null);
            $mapped = $this->icons->map($code !== null ? (int) $code : null, true);
            $day = Carbon::parse((string) $date, $this->timezone());

            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->format('D'),
                'condition' => $mapped['condition'],
                'icon' => $mapped['icon'],
                'temperature_max' => $this->number($daily['temperature_2m_max'][$index] ?? null),
                'temperature_min' => $this->number($daily['temperature_2m_min'][$index] ?? null),
                'rainfall' => $this->number($daily['precipitation_sum'][$index] ?? $daily['rain_sum'][$index] ?? null),
                'precipitation_probability' => $this->number($daily['precipitation_probability_max'][$index] ?? null),
                'wind_speed_max' => $this->number($daily['wind_speed_10m_max'][$index] ?? null),
                'feels_like_max' => $this->number($daily['apparent_temperature_max'][$index] ?? null),
            ];
        }

        return $days;
    }

    private function storeSnapshot(array $payload, array $weather): void
    {
        if (($weather['success'] ?? false) !== true) {
            return;
        }

        ExternalWeatherData::query()->updateOrCreate(
            [
                'source' => $weather['provider'] ?? 'Weather',
                'forecast_date' => now($this->timezone())->toDateString(),
                'forecast_time' => null,
                'barangay_id' => null,
            ],
            [
                'location_name' => 'Lian, Batangas',
                'latitude' => (float) config('services.weather.latitude', \App\Support\LianLocation::LATITUDE),
                'longitude' => (float) config('services.weather.longitude', \App\Support\LianLocation::LONGITUDE),
                'weather_code' => $weather['current']['weather_code'] ?? null,
                'temperature' => $weather['current']['temperature'] ?? null,
                'temperature_max' => $weather['today']['temperature_max'] ?? null,
                'temperature_min' => $weather['today']['temperature_min'] ?? null,
                'humidity' => $weather['current']['humidity'] ?? null,
                'rainfall_mm' => $weather['today']['rainfall'] ?? null,
                'precipitation_probability' => $weather['today']['precipitation_probability'] ?? null,
                'wind_speed' => $weather['current']['wind_speed'] ?? null,
                'raw_response' => $payload,
                // Stored timestamps are UTC; a Manila-zoned value would be saved 8 hours ahead and look "in the future".
                'fetched_at' => Carbon::parse($weather['fetched_at'] ?? now())->utc(),
            ],
        );
    }

    private function latestStoredSnapshot(): ?array
    {
        $record = ExternalWeatherData::query()
            ->whereIn('source', ['WeatherAPI', 'Open-Meteo'])
            ->latest('fetched_at')
            ->first();

        if (! $record) {
            return null;
        }

        $mapped = $this->icons->map($record->weather_code, null);
        $fetchedAt = $record->fetched_at?->timezone($this->timezone()) ?? now($this->timezone());

        return [
            'success' => true,
            'status' => 'stale',
            'provider' => $record->source,
            'source' => $record->source,
            'provider_details' => ['name' => $record->source, 'fallback' => $record->source === 'Open-Meteo'],
            'cached' => true,
            'stale' => true,
            'location' => $this->location(),
            'current' => [
                'temperature' => $this->number($record->temperature),
                'temperature_c' => $this->number($record->temperature),
                'feels_like' => null,
                'feels_like_c' => null,
                'humidity' => $this->number($record->humidity),
                'humidity_percent' => $this->number($record->humidity),
                'precipitation' => null,
                'precipitation_mm' => null,
                'rain' => null,
                'weather_code' => $record->weather_code,
                'condition' => $mapped['condition'],
                'wind_speed' => $this->number($record->wind_speed),
                'wind_kph' => $this->number($record->wind_speed),
                'wind_direction' => null,
                'cloud_cover' => null,
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => $this->number($record->temperature_max),
                'max_temperature_c' => $this->number($record->temperature_max),
                'temperature_min' => $this->number($record->temperature_min),
                'min_temperature_c' => $this->number($record->temperature_min),
                'rainfall' => $this->number($record->rainfall_mm),
                'rainfall_mm' => $this->number($record->rainfall_mm),
                'precipitation_probability' => $this->number($record->precipitation_probability),
                'chance_of_rain_percent' => $this->number($record->precipitation_probability),
            ],
            'forecast' => [],
            'icon' => $mapped['icon'],
            'fetched_at' => $fetchedAt,
            'fetched_at_label' => $this->displayTime($fetchedAt),
            'server_fetched_at' => null,
            'server_fetched_at_label' => null,
            'timestamps' => [
                'provider_updated_at' => $fetchedAt->toIso8601String(),
                'server_fetched_at' => null,
            ],
        ];
    }

    private function unavailable(): array
    {
        $mapped = $this->icons->map(null);

        return [
            'success' => false,
            'status' => 'unavailable',
            'provider' => 'Unavailable',
            'source' => 'Unavailable',
            'provider_details' => ['name' => 'Unavailable', 'fallback' => false],
            'cached' => false,
            'stale' => true,
            'location' => $this->location(),
            'current' => [
                'temperature' => null,
                'temperature_c' => null,
                'feels_like' => null,
                'feels_like_c' => null,
                'humidity' => null,
                'humidity_percent' => null,
                'precipitation' => null,
                'precipitation_mm' => null,
                'rain' => null,
                'weather_code' => null,
                'condition' => $mapped['condition'],
                'wind_speed' => null,
                'wind_kph' => null,
                'wind_direction' => null,
                'cloud_cover' => null,
                'icon' => $mapped['icon'],
            ],
            'today' => [
                'temperature_max' => null,
                'max_temperature_c' => null,
                'temperature_min' => null,
                'min_temperature_c' => null,
                'rainfall' => null,
                'rainfall_mm' => null,
                'precipitation_probability' => null,
                'chance_of_rain_percent' => null,
            ],
            'forecast' => [],
            'icon' => $mapped['icon'],
            'fetched_at' => null,
            'fetched_at_label' => 'Weather temporarily unavailable',
            'server_fetched_at' => null,
            'server_fetched_at_label' => null,
            'timestamps' => [
                'provider_updated_at' => null,
                'server_fetched_at' => null,
            ],
            'message' => 'Latest weather is temporarily unavailable.',
        ];
    }

    private function mapWeatherApiCondition(string $condition, ?int $code, bool $isDay): array
    {
        // Provider wording such as "Moderate Drizzle" is normalised to rain wording (keeping intensity) before matching.
        $text = strtolower(\App\Support\WeatherConditionLabel::display($condition));

        return match (true) {
            str_contains($text, 'thunder') => ['condition' => 'Thunderstorm', 'icon' => '/images/weather/thunderstorm.svg'],
            str_contains($text, 'heavy rain') || str_contains($text, 'torrential') => ['condition' => 'Heavy Rain', 'icon' => '/images/weather/heavy-rain.svg'],
            str_contains($text, 'moderate rain') => ['condition' => 'Moderate Rain', 'icon' => '/images/weather/rain.svg'],
            str_contains($text, 'light rain') || str_contains($text, 'patchy rain') || str_contains($text, 'drizzle') => ['condition' => 'Light Rain', 'icon' => '/images/weather/light-rain.svg'],
            str_contains($text, 'fog') || str_contains($text, 'mist') => ['condition' => 'Foggy', 'icon' => '/images/weather/fog.svg'],
            str_contains($text, 'overcast') => ['condition' => 'Overcast', 'icon' => '/images/weather/overcast.svg'],
            str_contains($text, 'cloudy') => ['condition' => str_contains($text, 'partly') ? 'Partly Cloudy' : 'Cloudy', 'icon' => str_contains($text, 'partly') ? '/images/weather/partly-cloudy-day.svg' : '/images/weather/overcast.svg'],
            str_contains($text, 'sunny') || str_contains($text, 'clear') => ['condition' => $isDay ? 'Clear' : 'Clear Night', 'icon' => $isDay ? '/images/weather/clear-day.svg' : '/images/weather/clear-night.svg'],
            default => $this->icons->map($code, $isDay),
        };
    }

    private function validateNormalized(array $weather, string $provider): void
    {
        $current = $weather['current'] ?? [];
        $location = $weather['location'] ?? [];

        if (! is_numeric($current['temperature'] ?? null)) {
            throw new RuntimeException($provider.' response has no valid temperature.');
        }

        $humidity = $current['humidity'] ?? null;
        if (! is_numeric($humidity) || $humidity < 0 || $humidity > 100) {
            throw new RuntimeException($provider.' response has invalid humidity.');
        }

        if (($current['condition'] ?? '') === '') {
            throw new RuntimeException($provider.' response has no condition text.');
        }

        if (! is_numeric($location['latitude'] ?? null) || ! is_numeric($location['longitude'] ?? null)) {
            throw new RuntimeException($provider.' response has invalid coordinates.');
        }
    }

    private function parseProviderTime(mixed $epoch, mixed $dateTime): ?Carbon
    {
        if (is_numeric($epoch)) {
            return Carbon::createFromTimestamp((int) $epoch, $this->timezone());
        }

        if (is_string($dateTime) && $dateTime !== '') {
            return Carbon::parse($dateTime, $this->timezone())->timezone($this->timezone());
        }

        return null;
    }

    private function cacheKey(): string
    {
        return 'weather.current.lian.shared';
    }

    private function coordinates(): string
    {
        return config('services.weather.latitude').','.config('services.weather.longitude');
    }

    private function location(): array
    {
        return [
            'name' => 'Lian',
            'province' => 'Batangas',
            'country' => 'Philippines',
            'latitude' => (float) config('services.weather.latitude', \App\Support\LianLocation::LATITUDE),
            'longitude' => (float) config('services.weather.longitude', \App\Support\LianLocation::LONGITUDE),
            'timezone' => $this->timezone(),
        ];
    }

    private function timezone(): string
    {
        return (string) config('services.weather.timezone', config('services.open_meteo.timezone', 'Asia/Manila'));
    }

    private function displayTime(Carbon $time): string
    {
        return $time->timezone($this->timezone())->format('M j, Y').' - '.$time->timezone($this->timezone())->format('g:i A');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function serializeTime(mixed $time): ?string
    {
        if ($time instanceof Carbon) {
            return $time->timezone($this->timezone())->toIso8601String();
        }

        return $time ? Carbon::parse($time, $this->timezone())->toIso8601String() : null;
    }
}
