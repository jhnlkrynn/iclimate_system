<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Weather\FarmerDashboardWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FarmerDashboardWeatherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.weather.latitude' => 14.033,
            'services.weather.longitude' => 120.650,
            'services.weather.timezone' => 'Asia/Manila',
            'services.weather.cache_minutes' => 5,
            'services.weather.forecast_days' => 7,
            'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
        ]);
    }

    public function test_service_normalizes_open_meteo_payload_and_uses_cache(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $service = app(FarmerDashboardWeatherService::class);
        $weather = $service->current();
        $cachedWeather = $service->current();

        $this->assertSame('Open-Meteo', $weather['provider']);
        $this->assertSame(25.5, $weather['current']['temperature']);
        $this->assertSame(95.0, $weather['current']['humidity']);
        $this->assertSame(6.2, $weather['today']['rainfall']);
        $this->assertSame('Light Rain', $weather['current']['condition']);
        $this->assertFalse($weather['cached']);
        $this->assertTrue($cachedWeather['cached']);
        Http::assertSentCount(1);
    }

    public function test_service_returns_latest_successful_weather_when_provider_fails(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push($this->openMeteoPayload(), 200)
                ->push(['reason' => 'overloaded'], 503),
        ]);

        $service = app(FarmerDashboardWeatherService::class);
        $service->current();

        $fallback = $service->current(force: true);

        $this->assertTrue($fallback['cached']);
        $this->assertTrue($fallback['stale']);
        $this->assertSame(25.5, $fallback['current']['temperature']);
    }

    public function test_farmer_dashboard_renders_live_weather_and_real_counts(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);
        // Pages never call the provider; the scheduled refresh stores the reading they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $user = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $user->id,
            'type' => 'Warning',
            'category' => Notification::CATEGORY_WEATHER,
            'is_read' => false,
            'title' => 'Heavy rain warning',
        ]);
        Notification::factory()->create([
            'user_id' => $user->id,
            'type' => 'Announcement',
            'is_read' => false,
            'title' => 'Training schedule',
        ]);

        $response = $this->actingAs($user)->get(route('farmer.dashboard'));

        $response->assertOk();
        $response->assertSee('Lian, Batangas');
        $response->assertSee('Light Rain');
        $response->assertSee('Rain Today');
        $response->assertSeeText('25.5°C');
        $response->assertDontSee('&deg;C', false);
        $response->assertDontSee('Â°C', false);
        $response->assertSee('6.2 mm');
        $response->assertSee("Today's Highlights");
        // Live weather is labelled as Lian's current reading with its update time, and refreshes on its own.
        $response->assertSee('Updated');
        $response->assertSee('Weather for Lian');
        $response->assertSee('data-mfd-refresh', false);
        $response->assertSee('data-mfd-clock', false);
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));
        $this->assertStringContainsString('const REFRESH_EVERY_MS = 300000;', $js);
        $this->assertStringContainsString("if (document.visibilityState === 'visible') refresh(); }, REFRESH_EVERY_MS);", $js);
        $response->assertDontSee('Live as of', false);
        $response->assertDontSee('Observation updated', false);
        $response->assertDontSee('Dashboard checked', false);
    }

    public function test_weather_endpoint_returns_normalized_json_for_farmer(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);
        // Pages never call the provider; the scheduled refresh stores the reading they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('farmer.dashboard.weather'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'Open-Meteo')
            ->assertJsonPath('current.temperature', 25.5)
            ->assertJsonPath('current.condition', 'Light Rain')
            ->assertJsonPath('today.rainfall', 6.2)
            ->assertJsonStructure(['fetched_at', 'checked_at', 'guidance']);
    }

    public function test_weatherapi_is_primary_provider_and_uses_configured_lian_coordinates(): void
    {
        config([
            'services.weather.provider' => 'weatherapi',
            'services.weatherapi.key' => 'test-weatherapi-key',
            'services.weatherapi.base_url' => 'https://api.weatherapi.com/v1',
        ]);

        Http::fake([
            'api.weatherapi.com/*' => Http::response($this->weatherApiPayload(), 200),
        ]);

        $weather = app(FarmerDashboardWeatherService::class)->current(force: true);

        $this->assertSame('WeatherAPI', $weather['provider']);
        $this->assertSame(27.9, $weather['current']['temperature']);
        $this->assertSame(33.7, $weather['today']['rainfall']);
        $this->assertSame(0.4, $weather['current']['precipitation']);
        $this->assertSame('Overcast', $weather['current']['condition']);
        $this->assertSame('2026-08-14T11:34:00+08:00', $weather['timestamps']['provider_updated_at']);

        Http::assertSent(function ($request) {
            $url = urldecode((string) $request->url());

            return str_contains($url, 'forecast.json')
                && str_contains($url, 'q=14.033,120.65')
                && str_contains($url, 'days=7');
        });
    }

    public function test_weatherapi_failure_falls_back_to_open_meteo(): void
    {
        config([
            'services.weather.provider' => 'weatherapi',
            'services.weatherapi.key' => 'test-weatherapi-key',
            'services.weatherapi.base_url' => 'https://api.weatherapi.com/v1',
        ]);

        Http::fake([
            'api.weatherapi.com/*' => Http::response(['error' => ['message' => 'maintenance']], 503),
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $weather = app(FarmerDashboardWeatherService::class)->current(force: true);

        $this->assertSame('Open-Meteo', $weather['provider']);
        $this->assertTrue($weather['provider_details']['fallback']);
        $this->assertSame(25.5, $weather['current']['temperature']);
    }

    public function test_failed_refresh_returns_last_successful_weather_without_zero_placeholders(): void
    {
        config([
            'services.weather.provider' => 'weatherapi',
            'services.weatherapi.key' => 'test-weatherapi-key',
            'services.weatherapi.base_url' => 'https://api.weatherapi.com/v1',
        ]);

        Http::fake([
            'api.weatherapi.com/*' => Http::sequence()
                ->push($this->weatherApiPayload(), 200)
                ->push(['error' => ['message' => 'down']], 503)
                ->push(['error' => ['message' => 'down']], 503)
                ->push(['error' => ['message' => 'down']], 503),
            'api.open-meteo.com/*' => Http::response(['reason' => 'down'], 503),
        ]);

        $service = app(FarmerDashboardWeatherService::class);
        $service->current(force: true);
        $fallback = $service->current(force: true);

        $this->assertTrue($fallback['cached']);
        $this->assertTrue($fallback['stale']);
        $this->assertSame(27.9, $fallback['current']['temperature']);
        $this->assertNotSame(0.0, $fallback['today']['rainfall']);
    }

    public function test_weatherapi_dashboard_cache_prevents_external_calls_on_each_poll(): void
    {
        config([
            'services.weather.provider' => 'weatherapi',
            'services.weatherapi.key' => 'test-weatherapi-key',
            'services.weatherapi.base_url' => 'https://api.weatherapi.com/v1',
        ]);

        Http::fake([
            'api.weatherapi.com/*' => Http::response($this->weatherApiPayload(), 200),
        ]);

        $service = app(FarmerDashboardWeatherService::class);
        $first = $service->current();
        $second = $service->current();

        $this->assertFalse($first['cached']);
        $this->assertTrue($second['cached']);
        Http::assertSentCount(1);
    }

    public function test_live_weather_endpoint_wraps_payload_for_frontend_clients(): void
    {
        config([
            'services.weather.provider' => 'weatherapi',
            'services.weatherapi.key' => 'test-weatherapi-key',
            'services.weatherapi.base_url' => 'https://api.weatherapi.com/v1',
        ]);

        Http::fake([
            'api.weatherapi.com/*' => Http::response($this->weatherApiPayload(), 200),
        ]);
        // Pages never call the provider; the scheduled refresh stores the reading they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.weather.live'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'WeatherAPI')
            ->assertJsonPath('data.current.temperature', 27.9)
            ->assertJsonStructure(['data' => ['fetched_at', 'checked_at', 'timestamps', 'guidance']]);
    }

    public function test_mao_dashboard_has_realtime_open_meteo_weather_endpoint_and_polling(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);
        // Pages never call the provider; the scheduled refresh stores the reading they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $user = User::factory()->maoPersonnel()->create();

        $this->actingAs($user)
            ->get(route('mao.operations'))
            ->assertOk()
            ->assertSee('data-mao-weather-root', false)
            ->assertSee('MAO_WEATHER_POLL_INTERVAL = 60000', false)
            ->assertSee('refreshMaoWeather', false)
            ->assertSee('Live weather for Lian, Batangas');

        $this->actingAs($user)
            ->getJson(route('mao.dashboard.weather'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('weather.source', 'Open-Meteo')
            ->assertJsonPath('weather.current_temperature_c', 25.5)
            ->assertJsonPath('source_label', 'Open-Meteo live weather for Lian, Batangas');
    }

    public function test_mao_dashboard_chart_labels_render_horizontally_and_wrap(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);
        // Pages never call the provider; the scheduled refresh stores the reading they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $user = User::factory()->maoPersonnel()->create();
        $response = $this->actingAs($user)->get(route('mao.operations'));
        $content = $response->getContent();

        // The page's styles live in their own file since 2026-10-06 (they used to be a <style> block in the view).
        $css = (string) file_get_contents(resource_path('css/pages/mao-operations.css'));
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
        $this->assertMatchesRegularExpression('/<link[^>]+mao-operations[^">]*\.css/', $content);

        $response->assertOk()
            ->assertSee('horizontalCategoryTicks', false)
            ->assertSee('iClimateVisualsReady', false)
            ->assertSee('Loading real weather data...', false)
            ->assertSee('No weather records found for this chart.', false)
            ->assertSee('context.parsed.y', false)
            ->assertSee('display: false', false)
            ->assertSee('maxRotation: 0', false)
            ->assertSee('minRotation: 0', false)
            ->assertDontSee("indexAxis: 'y'", false)
            ->assertDontSee('maxRotation: 45', false)
            ->assertDontSee('minRotation: 45', false);

        $this->assertSame(1, substr_count($content, "rainfall: { id: 'rainfallChart'"));
        $this->assertSame(1, substr_count($content, "temperature: { id: 'temperatureChart'"));
        $this->assertSame(1, substr_count($content, "humidity: { id: 'humidityChart'"));
        $this->assertSame(1, substr_count($content, "windSpeed: { id: 'windSpeedChart'"));
    }

    /**
     * @return array<string, mixed>
     */
    private function weatherApiPayload(): array
    {
        return [
            'location' => [
                'name' => 'Lian',
                'region' => 'Batangas',
                'country' => 'Philippines',
                'lat' => 14.033,
                'lon' => 120.65,
                'tz_id' => 'Asia/Manila',
            ],
            'current' => [
                'last_updated_epoch' => 1786678440,
                'last_updated' => '2026-08-14 11:34',
                'temp_c' => 27.9,
                'feelslike_c' => 33.0,
                'humidity' => 85,
                'precip_mm' => 0.4,
                'condition' => ['text' => 'Overcast', 'code' => 1009],
                'wind_kph' => 11.3,
                'wind_degree' => 210,
                'wind_dir' => 'SSW',
                'gust_kph' => 18.5,
                'pressure_mb' => 1008,
                'cloud' => 100,
                'vis_km' => 10,
                'uv' => 4,
                'is_day' => 1,
            ],
            'forecast' => [
                'forecastday' => [
                    [
                        'date' => '2026-08-14',
                        'day' => [
                            'maxtemp_c' => 30.2,
                            'mintemp_c' => 24.9,
                            'totalprecip_mm' => 33.7,
                            'daily_chance_of_rain' => 96,
                            'condition' => ['text' => 'Overcast', 'code' => 1009],
                        ],
                    ],
                    [
                        'date' => '2026-08-15',
                        'day' => [
                            'maxtemp_c' => 29.0,
                            'mintemp_c' => 25.0,
                            'totalprecip_mm' => 8.0,
                            'daily_chance_of_rain' => 84,
                            'condition' => ['text' => 'Patchy rain nearby', 'code' => 1063],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openMeteoPayload(): array
    {
        return [
            'latitude' => 14.033,
            'longitude' => 120.650,
            'timezone' => 'Asia/Manila',
            'current' => [
                'time' => '2026-08-09T23:15',
                'temperature_2m' => 25.5,
                'relative_humidity_2m' => 95,
                'apparent_temperature' => 27.1,
                'precipitation' => 0.1,
                'rain' => 0.1,
                'showers' => 0,
                'weather_code' => 61,
                'cloud_cover' => 80,
                'pressure_msl' => 1008,
                'surface_pressure' => 1005,
                'wind_speed_10m' => 8.4,
                'wind_direction_10m' => 180,
                'wind_gusts_10m' => 15.2,
                'is_day' => 0,
            ],
            'daily' => [
                'time' => ['2026-08-09', '2026-08-10'],
                'weather_code' => [61, 2],
                'temperature_2m_max' => [30.2, 31.1],
                'temperature_2m_min' => [24.1, 24.4],
                'apparent_temperature_max' => [33.0, 34.0],
                'apparent_temperature_min' => [26.0, 26.4],
                'sunrise' => ['2026-08-09T05:42', '2026-08-10T05:42'],
                'sunset' => ['2026-08-09T18:20', '2026-08-10T18:19'],
                'precipitation_sum' => [6.2, 2.0],
                'rain_sum' => [5.7, 1.4],
                'showers_sum' => [0.5, 0.2],
                'precipitation_probability_max' => [80, 45],
                'wind_speed_10m_max' => [12.5, 10.0],
                'wind_gusts_10m_max' => [20.0, 18.0],
            ],
            'hourly' => [
                'time' => ['2026-08-09T23:00'],
                'temperature_2m' => [25.5],
                'relative_humidity_2m' => [95],
                'precipitation_probability' => [80],
                'precipitation' => [0.1],
                'rain' => [0.1],
                'weather_code' => [61],
            ],
        ];
    }
}
