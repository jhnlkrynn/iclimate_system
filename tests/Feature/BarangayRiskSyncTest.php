<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesBarangayWeather;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

/**
 * Current risk follows current weather: the shared barangay weather → the existing risk engine → a verified rating
 * for each of the 19 barangays, with its own calculation time, never replaced by No Data while a verified rating
 * exists, and never made up when a required input is missing.
 */
class BarangayRiskSyncTest extends TestCase
{
    use FakesBarangayWeather;
    use PreparesPredictions;
    use RefreshDatabase;

    private bool $providerUp = true;

    private array $failing = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 19:30', 'Asia/Manila'));
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/*' => function ($request) {
            return $this->openMeteoAnswer($request, fn (float $lat, float $lon) => $this->payloadFor($lat, $lon));
        }]);
        $this->prepareMonthlyRainfall();
    }

    public function test_the_weather_command_rates_all_19_barangays_from_that_weather(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])
            ->expectsOutputToContain('Latest weather for 19 of 19 barangays')
            ->expectsOutputToContain('Risk rated from this weather')
            ->assertSuccessful();

        $map = Cache::get(BarangayRiskMapService::LATEST_KEY);
        $levels = collect($map['barangays'])->pluck('risk_level');
        $this->assertCount(19, $levels);
        $this->assertEmpty($levels->diff(['Low', 'Moderate', 'High', 'Severe']), 'Every barangay has a verified rating.');
        $this->assertSame(app(LianBarangayWeatherService::class)->summary()['last_success_at'], $map['weather_generation']);

        // The same 19 ratings are what the database, and so the dashboards and Climora, read.
        $stored = HeatmapArea::query()->computed()->get()->keyBy(fn (HeatmapArea $a) => LianBarangays::canonicalName($a->barangay));
        foreach ($map['barangays'] as $entry) {
            $this->assertSame($entry['risk_level'], $stored[$entry['barangay']]->risk_level);
            $this->assertSame($map['generated_at'], $stored[$entry['barangay']]->risk_computed_at->toIso8601String());
        }

        // One provider request for the weather; rating it made none of its own.
        $this->assertCount(1, Http::recorded());
    }

    public function test_the_same_weather_is_rated_once_and_new_weather_is_rated_again(): void
    {
        $risk = app(BarangayRiskMapService::class);
        app(LianBarangayWeatherService::class)->refreshAll();
        $first = $risk->refreshForCurrentWeather();
        $this->assertNotNull($first);

        $this->assertNull($risk->refreshForCurrentWeather(), 'Already rated from these readings.');

        $this->travel(11)->minutes();
        app(LianBarangayWeatherService::class)->refreshAll();
        $second = $risk->refreshForCurrentWeather();

        $this->assertNotNull($second);
        $this->assertTrue(Carbon::parse($second['generated_at'])->gt(Carbon::parse($first['generated_at'])));
    }

    public function test_an_unchanged_rating_gets_a_new_calculation_time(): void
    {
        app(LianBarangayWeatherService::class)->refreshAll();
        app(BarangayRiskMapService::class)->refreshForCurrentWeather();
        $before = HeatmapArea::query()->where('barangay', 'Cumba')->firstOrFail();

        $this->travel(11)->minutes();
        app(LianBarangayWeatherService::class)->refreshAll();
        app(BarangayRiskMapService::class)->refreshForCurrentWeather();
        $after = $before->fresh();

        $this->assertSame($before->risk_level, $after->risk_level);
        $this->assertTrue($after->risk_computed_at->gt($before->risk_computed_at), 'Re-verified, so re-stamped.');
    }

    public function test_a_failed_rating_keeps_the_last_verified_one_with_its_own_time(): void
    {
        app(LianBarangayWeatherService::class)->refreshAll();
        $first = app(BarangayRiskMapService::class)->refreshForCurrentWeather();
        $before = collect($first['barangays'])->firstWhere('barangay', 'San Diego');

        // New weather arrives, but a required input (the monthly rainfall) is no longer available.
        $this->travel(11)->minutes();
        $this->makeMonthlyRainfallUnavailable();
        Cache::forever(BarangayRiskMapService::LATEST_KEY, $first);
        app(LianBarangayWeatherService::class)->refreshAll();
        app(BarangayRiskMapService::class)->refreshForCurrentWeather();

        $entry = $this->apiEntry('San Diego');
        $this->assertSame($before['risk_level'], $entry['risk_level']);
        $this->assertSame($before['updated_at'], $entry['risk_calculated_at'], 'Kept rating keeps its own calculation time.');
        $this->assertSame('fresh', $entry['weather']['status']);
    }

    public function test_a_cleared_or_no_data_cached_map_shows_the_verified_database_ratings(): void
    {
        app(LianBarangayWeatherService::class)->refreshAll();
        app(BarangayRiskMapService::class)->refreshForCurrentWeather();
        $verified = HeatmapArea::query()->where('barangay', 'Bagong Pook')->firstOrFail();

        // What a cache built while nothing was rated looks like (e.g. right after a cache clear).
        Cache::forget(BarangayRiskMapService::CACHE_KEY);
        Cache::forever(BarangayRiskMapService::LATEST_KEY, [
            'generated_at' => now()->toIso8601String(),
            'source_note' => 'test',
            'barangays' => collect(LianBarangays::all())->map(fn (string $b): array => [
                'barangay' => $b, 'psgc_10d' => null, 'risk_level' => 'No Data', 'risk_score' => null, 'color' => 'grey',
                'status' => 'no_data', 'updated_at' => null, 'weather' => ['status' => 'unavailable'], 'last_recorded_yield' => null,
            ])->all(),
        ]);

        $entry = $this->apiEntry('Bagong Pook');
        $this->assertSame($verified->risk_level, $entry['risk_level']);
        $this->assertSame($verified->risk_computed_at->toIso8601String(), $entry['risk_calculated_at']);
        $this->assertTrue($entry['status'] === 'stale' || $entry['status'] === 'ok');
    }

    public function test_install_placeholders_are_never_shown_as_ratings(): void
    {
        HeatmapArea::query()->delete();
        foreach (LianBarangays::all() as $barangay) {
            HeatmapArea::create(['barangay' => $barangay, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.5,
                'rainfall_status' => HeatmapArea::SEED_RAINFALL_STATUS, 'description' => HeatmapArea::SEED_DESCRIPTION]);
        }
        $this->providerUp = false;

        $this->assertSame(['No Data'], collect($this->getJson('/api/lian/risk-map')->assertOk()->json('barangays'))->pluck('risk_level')->unique()->values()->all());
        $this->assertCount(0, app(DashboardData::class)->currentRiskRatings());
    }

    public function test_a_missing_required_input_is_no_data_never_low(): void
    {
        HeatmapArea::query()->delete();
        $this->makeMonthlyRainfallUnavailable();
        app(LianBarangayWeatherService::class)->refreshAll();

        $map = app(BarangayRiskMapService::class)->refreshForCurrentWeather(force: true);

        $this->assertSame(['No Data'], collect($map['barangays'])->pluck('risk_level')->unique()->values()->all());
        $this->assertSame(0, HeatmapArea::query()->count(), 'Nothing is persisted without a real rating.');
    }

    public function test_one_failed_barangay_does_not_stop_the_other_18_from_being_rated(): void
    {
        HeatmapArea::query()->delete();
        $this->failing = ['Luyahan'];
        app(LianBarangayWeatherService::class)->refreshAll();

        $map = app(BarangayRiskMapService::class)->refreshForCurrentWeather(force: true);
        $byName = collect($map['barangays'])->keyBy('barangay');

        $this->assertSame('No Data', $byName['Luyahan']['risk_level']);
        $this->assertCount(18, $byName->reject(fn (array $e) => $e['risk_level'] === 'No Data'));
    }

    public function test_refresh_weather_and_risk_rates_the_new_weather_after_the_response(): void
    {
        $response = $this->getJson('/api/lian/risk-map?refresh=1')->assertOk();

        $this->assertTrue($response->json('weather_refreshed'));
        $this->assertTrue($response->json('risk_refresh_scheduled'));
        // The ratings were made right after that response (no queue worker), so the next read has them.
        $entry = $this->apiEntry('San Diego');
        $this->assertContains($entry['risk_level'], ['Low', 'Moderate', 'High', 'Severe']);
        $this->assertNotNull($entry['risk_calculated_at']);
        // Weather and risk keep their own times: the rating was made after (from) that weather.
        $this->assertNotNull($entry['weather']['fetched_at']);
        $this->assertTrue(Carbon::parse($entry['risk_calculated_at'])->gte(Carbon::parse($entry['weather']['fetched_at'])));
    }

    public function test_heat_map_dashboards_and_climora_share_one_rating(): void
    {
        Config::set('services.groq.enabled', false);
        $this->preparePredictions();
        $this->prepareMonthlyRainfall();
        app(LianBarangayWeatherService::class)->refreshAll();
        app(BarangayRiskMapService::class)->refreshForCurrentWeather(force: true);

        $mapLevel = $this->apiEntry('San Diego')['risk_level'];
        $dashboardLevel = app(DashboardData::class)->currentRiskRatings()->first(fn (HeatmapArea $a) => LianBarangays::canonicalName($a->barangay) === 'San Diego')?->risk_level;
        $this->assertSame($mapLevel, $dashboardLevel);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'San Diego']);
        $answer = (string) $this->actingAs($farmer)->postJson(route('ai-chat.message'), ['question' => 'What is the risk in San Diego?'])
            ->assertOk()->json('chat.answer');
        $this->assertStringContainsStringIgnoringCase($mapLevel, $answer);
    }

    private function apiEntry(string $barangay): array
    {
        $entry = collect($this->getJson('/api/lian/risk-map')->assertOk()->json('barangays'))->firstWhere('barangay', $barangay);
        $this->assertNotNull($entry);

        return $entry;
    }

    /** No prepared monthly rainfall and no historical dataset to build one from: a required input is genuinely missing. */
    private function makeMonthlyRainfallUnavailable(): void
    {
        $this->app->instance(AmbulongHistoricalClimateDataset::class, new AmbulongHistoricalClimateDataset(database_path('data/does-not-exist.csv')));
        MonthlyWeatherPrediction::query()->delete();
        $weather = Cache::get('weather-api:lian-barangay:v2:store');
        Cache::flush();
        Cache::forever('weather-api:lian-barangay:v2:store', $weather);
    }

    /** The prepared municipality-wide monthly rainfall the scorer needs (as the scheduled refresh leaves it). */
    private function prepareMonthlyRainfall(): void
    {
        $month = now()->startOfMonth();
        MonthlyWeatherPrediction::query()->updateOrCreate(
            ['prediction_year' => $month->year, 'prediction_month' => $month->month, 'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION],
            ['rainfall' => 200, 'tmax' => 30, 'tmin' => 24, 'temperature_average' => 27, 'humidity' => 75, 'wind_speed' => 10,
                'wind_direction' => 180, 'season' => 'Wet', 'generated_at' => now(), 'expires_at' => now()->addDay(),
                'status' => MonthlyWeatherPrediction::STATUS_READY,
                'metadata' => ['dataset_version' => app(MonthlyWeatherRandomForest::class)->datasetVersion()]],
        );
    }

    private function payloadFor(float $latitude, float $longitude): ?array
    {
        $name = collect(LianBarangays::coordinates())->search(fn (array $point) => abs($point[0] - $latitude) < 1e-6 && abs($point[1] - $longitude) < 1e-6);
        if (! $this->providerUp || in_array($name, $this->failing, true)) {
            return null;
        }

        $start = now('Asia/Manila')->startOfHour();
        $times = collect(range(0, 47))->map(fn (int $hour) => $start->copy()->addHours($hour)->format('Y-m-d\TH:i'))->all();

        return [
            'current' => ['time' => now('Asia/Manila')->format('Y-m-d\TH:i'), 'temperature_2m' => 27.5, 'relative_humidity_2m' => 85,
                'precipitation' => 0.2, 'rain' => 0.2, 'weather_code' => 3, 'is_day' => 0, 'wind_speed_10m' => 8.0],
            'hourly' => ['time' => $times, 'precipitation_probability' => array_fill(0, 48, 60), 'precipitation' => array_fill(0, 48, 0.3),
                'rain' => array_fill(0, 48, 0.3), 'temperature_2m' => array_fill(0, 48, 27), 'relative_humidity_2m' => array_fill(0, 48, 85),
                'wind_speed_10m' => array_fill(0, 48, 8.0)],
            'daily' => ['time' => [now('Asia/Manila')->toDateString()], 'precipitation_sum' => [7], 'rain_sum' => [7],
                'temperature_2m_max' => [30], 'temperature_2m_min' => [24], 'precipitation_probability_max' => [60]],
        ];
    }
}
