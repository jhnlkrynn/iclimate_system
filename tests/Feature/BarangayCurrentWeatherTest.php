<?php

namespace Tests\Feature;

use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
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
 * The shared barangay current weather behind the Heat Map (Farmer, MAO, IT), its Barangays Monitored pop-up,
 * Current Conditions, Advisories and Climora: one batched provider request, per-barangay last valid records,
 * honest freshness, and weather/risk timestamps that never borrow from each other.
 */
class BarangayCurrentWeatherTest extends TestCase
{
    use FakesBarangayWeather;
    use PreparesPredictions;
    use RefreshDatabase;

    /** Barangays the fake provider fails for. */
    private array $failing = [];

    private bool $providerUp = true;

    /** Added to every fake temperature, so a later refresh returns different values. */
    private float $shift = 0.0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 16:20', 'Asia/Manila'));
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/*' => fn ($request) => $this->openMeteoAnswer($request, fn (float $lat, float $lon) => $this->payloadFor($lat, $lon))]);
    }

    public function test_fresh_weather_shows_the_providers_actual_values_on_the_heat_map(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();

        $entry = $this->mapEntry('San Diego');
        [$lat] = LianBarangays::coordinateFor('San Diego');

        $this->assertSame('fresh', $entry['weather']['status']);
        $this->assertTrue($entry['weather']['is_fresh']);
        $this->assertSame($this->temperatureFor($lat), $entry['weather']['temperature_c']);
        $this->assertEquals(82.0, $entry['weather']['humidity_percent']);
        $this->assertEquals(12.3, $entry['weather']['wind_speed_kmh']);
        $this->assertSame('Open-Meteo', $entry['weather']['provider']);
        $this->assertSame(now()->toIso8601String(), $entry['weather']['fetched_at']);
        $this->assertNotEmpty($entry['weather']['condition']);
    }

    public function test_five_minute_old_weather_is_still_current_and_needs_no_provider_call(): void
    {
        app(LianBarangayWeatherService::class)->refreshAll();
        $this->travel(5)->minutes();
        Http::fake(['*' => Http::response('should not be called', 500)]);

        $record = app(LianBarangayWeatherService::class)->getCurrentWeather('Balibago');

        $this->assertSame('fresh', $record['status']);
        $this->assertSame(5, $record['age_minutes']);
        $this->assertFalse(app(LianBarangayWeatherService::class)->isDue());
    }

    public function test_expired_weather_is_served_immediately_and_the_page_never_refreshes_it(): void
    {
        app(LianBarangayWeatherService::class)->refreshAll();
        [$lat] = LianBarangays::coordinateFor('Bagong Pook');
        $this->travel(11)->minutes();
        $this->shift = 1.0;

        $response = $this->getJson('/api/lian/risk-map')->assertOk();

        // The response carries the last valid reading (11 min old: "recent", never shown as delayed) at once…
        $entry = collect($response->json('barangays'))->firstWhere('barangay', 'Bagong Pook');
        $this->assertSame('recent', $entry['weather']['status']);
        $this->assertSame($this->temperatureFor($lat) - 1.0, $entry['weather']['temperature_c']);
        $this->assertFalse($response->json('weather_refresh_scheduled'));

        // …and the request does not fetch: the stored reading stays until iclimate:refresh-barangay-weather runs.
        $record = app(LianBarangayWeatherService::class)->getCurrentWeather('Bagong Pook');
        $this->assertSame($this->temperatureFor($lat) - 1.0, $record['temperature_c']);

        $this->artisan('iclimate:refresh-barangay-weather');
        $record = app(LianBarangayWeatherService::class)->getCurrentWeather('Bagong Pook');
        $this->assertSame('fresh', $record['status']);
        $this->assertSame($this->temperatureFor($lat), $record['temperature_c']);
    }

    public function test_successful_refresh_updates_values_and_timestamp(): void
    {
        $service = app(LianBarangayWeatherService::class);
        $service->refreshAll();
        $first = $service->getCurrentWeather('Cumba');

        $this->travel(12)->minutes();
        $this->shift = 2.5;
        $this->getJson('/api/lian/risk-map?refresh=1')->assertOk()->assertJsonPath('weather_refreshed', true);
        $second = $service->getCurrentWeather('Cumba');

        $this->assertSame($first['temperature_c'] + 2.5, $second['temperature_c']);
        $this->assertTrue(Carbon::parse($second['fetched_at'])->gt(Carbon::parse($first['fetched_at'])));
        $this->assertSame('fresh', $second['status']);
    }

    public function test_failed_refresh_keeps_the_last_valid_value_and_marks_it_delayed_only_after_the_threshold(): void
    {
        $service = app(LianBarangayWeatherService::class);
        $service->refreshAll();
        $kept = $service->getCurrentWeather('Prenza')['temperature_c'];
        $this->providerUp = false;

        $this->travel(15)->minutes();
        $service->refreshAll();
        $recent = $service->getCurrentWeather('Prenza');
        $this->assertSame('recent', $recent['status']);
        $this->assertFalse($recent['stale']);
        $this->assertSame($kept, $recent['temperature_c']);

        $this->travel(16)->minutes();
        $service->refreshAll();
        $entry = $this->mapEntry('Prenza');
        $this->assertSame('stale', $entry['weather']['status']);
        $this->assertFalse($entry['weather']['is_fresh']);
        $this->assertSame($kept, $entry['weather']['temperature_c']);
        $this->assertStringContainsString('temporarily delayed', $entry['weather']['message']);
        $this->assertSame(31, $entry['weather']['age_minutes']);
    }

    public function test_no_previous_value_is_reported_as_unavailable_not_invented(): void
    {
        $this->providerUp = false;
        app(LianBarangayWeatherService::class)->refreshAll();

        $entry = $this->mapEntry('Luyahan');

        $this->assertSame('unavailable', $entry['weather']['status']);
        $this->assertNull($entry['weather']['temperature_c']);
        $this->assertNull($entry['weather']['humidity_percent']);
        $this->assertArrayNotHasKey('condition', $entry['weather']);
    }

    public function test_one_failed_barangay_does_not_affect_the_other_eighteen(): void
    {
        $this->failing = ['San Diego'];

        $records = app(LianBarangayWeatherService::class)->refreshAll();
        $summary = app(LianBarangayWeatherService::class)->summary($records);

        $this->assertSame('unavailable', $records['San Diego']['status']);
        $this->assertCount(18, collect($records)->where('status', 'fresh'));
        $this->assertSame(18, $summary['current']);
        $this->assertSame(['San Diego'], $summary['unavailable_barangays']);
        $this->assertSame('concurrent', $summary['last_refresh_method']);

        // A later failure for San Diego alone, after it had a reading, keeps that reading for San Diego only.
        $this->failing = [];
        app(LianBarangayWeatherService::class)->refreshAll();
        $this->failing = ['San Diego'];
        $this->travel(31)->minutes();
        $records = app(LianBarangayWeatherService::class)->refreshAll();

        $this->assertSame('stale', $records['San Diego']['status']);
        $this->assertNotNull($records['San Diego']['temperature_c']);
        $this->assertCount(18, collect($records)->where('status', 'fresh'));
    }

    public function test_all_nineteen_succeed_in_one_batched_request_and_current_conditions_says_19_of_19(): void
    {
        $this->getJson('/api/lian/risk-map?refresh=1')->assertOk();

        $this->assertCount(1, Http::recorded(), 'All 19 barangays must be fetched in one request, not 19 sequential ones.');
        $summary = $this->getJson('/api/lian/risk-map')->assertOk()->json('weather_summary');

        $this->assertSame(19, $summary['total']);
        $this->assertSame(19, $summary['current']);
        $this->assertSame(0, $summary['delayed']);
        $this->assertSame('Open-Meteo', $summary['provider']);
        $this->assertSame('batch', $summary['last_refresh_method']);
        $this->assertSame(now()->toIso8601String(), $summary['last_checked_at']);
    }

    public function test_each_barangay_uses_its_own_location(): void
    {
        $records = app(LianBarangayWeatherService::class)->refreshAll();
        $points = $this->requestedPoints(Http::recorded()[0][0]);

        $this->assertCount(19, $records);
        $this->assertSame(LianBarangays::all(), array_keys($records));
        $this->assertCount(19, collect($points)->map(fn (array $point) => implode(',', $point))->unique());

        foreach ($records as $barangay => $record) {
            $this->assertSame(LianBarangays::coordinateFor($barangay), [$record['latitude'], $record['longitude']]);
            $this->assertContainsEquals([$record['latitude'], $record['longitude']], $points);
            $this->assertSame($this->temperatureFor($record['latitude']), $record['temperature_c']);
            foreach (['temperature_c', 'humidity_percent', 'precipitation_mm', 'rain_probability_percent', 'wind_kmh', 'weather_condition', 'provider', 'observed_at', 'fetched_at', 'is_fresh'] as $field) {
                $this->assertArrayHasKey($field, $record);
            }
        }

        $this->assertGreaterThan(1, collect($records)->pluck('temperature_c')->unique()->count());
    }

    public function test_farmers_barangay_is_requested_first(): void
    {
        $this->getJson('/api/lian/risk-map?refresh=1&priority=San%20Diego')->assertOk();

        $points = $this->requestedPoints(Http::recorded()[0][0]);
        $this->assertEquals(LianBarangays::coordinateFor('San Diego'), $points[0]);
    }

    public function test_monthly_forecast_is_never_presented_as_current_weather(): void
    {
        $month = now()->startOfMonth();
        MonthlyWeatherPrediction::create([
            'prediction_year' => $month->year, 'prediction_month' => $month->month,
            'rainfall' => 321, 'tmax' => 40, 'tmin' => 35, 'temperature_average' => 37.7, 'humidity' => 55,
            'wind_speed' => 44, 'wind_direction' => 180, 'season' => 'Wet',
            'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
            'generated_at' => now(), 'expires_at' => now()->addDay(),
            'status' => MonthlyWeatherPrediction::STATUS_READY,
            'metadata' => ['dataset_version' => app(MonthlyWeatherRandomForest::class)->datasetVersion()],
        ]);
        app(BarangayRiskMapService::class)->riskMap(refresh: true);

        $entry = $this->mapEntry('Kapito');
        [$lat] = LianBarangays::coordinateFor('Kapito');

        $this->assertSame($this->temperatureFor($lat), $entry['weather']['temperature_c']);
        $this->assertNotEquals(37.7, $entry['weather']['temperature_c']);
        $this->assertEquals(82.0, $entry['weather']['humidity_percent']);
        $this->assertEquals(12.3, $entry['weather']['wind_speed_kmh']);
    }

    public function test_weather_refresh_never_gives_the_risk_rating_a_newer_timestamp(): void
    {
        $ratedAt = now()->subHours(2)->toIso8601String();
        Cache::forever(BarangayRiskMapService::LATEST_KEY, [
            'generated_at' => $ratedAt,
            'source_note' => 'Verified test map.',
            'barangays' => collect(LianBarangays::all())->map(fn (string $name): array => [
                'barangay' => $name, 'psgc_10d' => null, 'risk_level' => 'Moderate', 'risk_score' => 0.5,
                'color' => 'yellow', 'status' => 'ok', 'updated_at' => $ratedAt,
                'weather' => ['status' => 'unavailable'], 'last_recorded_yield' => null,
            ])->all(),
        ]);

        $entry = collect($this->getJson('/api/lian/risk-map?refresh=1')->assertOk()->json('barangays'))->firstWhere('barangay', 'Balibago');

        $this->assertSame('Moderate', $entry['risk_level']);
        $this->assertSame($ratedAt, $entry['risk_calculated_at']);
        $this->assertSame(now()->toIso8601String(), $entry['weather']['fetched_at']);
        $this->assertSame('fresh', $entry['weather']['status']);
    }

    public function test_missing_weather_is_never_rated_low_or_moderate(): void
    {
        $this->providerUp = false;

        $map = app(BarangayRiskMapService::class)->riskMap(refresh: true);

        $this->assertSame(['No Data'], collect($map['barangays'])->pluck('risk_level')->unique()->values()->all());
    }

    public function test_warm_up_command_fills_all_19_and_scheduled_run_skips_when_current(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])
            ->expectsOutputToContain('Latest weather for 19 of 19 barangays')
            ->assertSuccessful();

        $this->artisan('iclimate:refresh-barangay-weather')
            ->expectsOutputToContain('already current')
            ->assertSuccessful();
        $this->assertCount(1, Http::recorded());
    }

    /** @return array<string, array{0: string}> */
    public static function naturalBarangayWeatherQuestions(): array
    {
        return [
            'what is' => ['What is the weather in San Diego?'],
            'what\'s' => ["What's the weather in San Diego?"],
            'how is' => ['How is the weather in San Diego?'],
            'bare' => ['Weather in San Diego'],
            'kamusta' => ['Kamusta ang panahon sa San Diego?'],
            'ano' => ['Ano ang panahon sa San Diego?'],
            'umuulan' => ['Umuulan ba sa San Diego?'],
            'mainit' => ['Mainit ba sa San Diego?'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('naturalBarangayWeatherQuestions')]
    public function test_climora_reports_the_same_barangay_weather_as_the_heat_map(string $question): void
    {
        Config::set('services.groq.enabled', false);
        // Chat only reads prepared predictions; prepare them the way the other Climora tests do.
        $this->preparePredictions();
        app(LianBarangayWeatherService::class)->refreshAll();
        $heatMap = $this->mapEntry('San Diego')['weather'];
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), ['question' => $question]);

        $response->assertOk()->assertJsonPath('chat.intent', 'current_weather');
        $answer = (string) $response->json('chat.answer');
        $this->assertStringContainsString('San Diego', $answer);
        $this->assertStringContainsString(rtrim(rtrim(number_format($heatMap['temperature_c'], 2, '.', ''), '0'), '.').'°C', $answer);
        $this->assertStringNotContainsString('delayed', strtolower($answer));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function weatherQuestionRouting(): array
    {
        return [
            'no period' => ['What is the weather in San Diego?', 'current_weather'],
            'tagalog no period' => ['Kamusta ang panahon sa San Diego?', 'current_weather'],
            'tomorrow' => ['Weather tomorrow in San Diego', 'weather_forecast'],
            'later' => ['What is the weather later in San Diego?', 'weather_forecast'],
            'next week' => ['Forecast for next week', 'weather_forecast'],
            'named month' => ['Weather in November', 'weather_prediction'],
            'monthly' => ['Monthly rainfall forecast', 'weather_prediction'],
            'advisory stays advisory' => ['Is there a weather advisory for Lian?', 'weather_advisory'],
            'planting stays planting' => ['Should I plant rice this week?', 'planting_recommendation'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weatherQuestionRouting')]
    public function test_weather_questions_route_by_their_time_period(string $question, string $intent): void
    {
        $this->assertSame($intent, app(\App\Services\AI\IntentClassifierService::class)->classifyLocal($question)['intent']);
    }

    private function mapEntry(string $barangay): array
    {
        $entry = collect($this->getJson('/api/lian/risk-map')->assertOk()->json('barangays'))->firstWhere('barangay', $barangay);
        $this->assertNotNull($entry, "{$barangay} is missing from the risk map.");

        return $entry;
    }

    /** A different, deterministic temperature per location, so a mixed-up barangay would show. */
    private function temperatureFor(float $latitude): float
    {
        return round(25 + ($latitude - 13.9) * 20 + $this->shift, 2);
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
            'current' => [
                'time' => now('Asia/Manila')->format('Y-m-d\TH:i'),
                'temperature_2m' => $this->temperatureFor($latitude),
                'relative_humidity_2m' => 82,
                'precipitation' => 0.4,
                'rain' => 0.4,
                'weather_code' => 61,
                'is_day' => 1,
                'wind_speed_10m' => 12.3,
            ],
            'hourly' => [
                'time' => $times,
                'precipitation_probability' => array_fill(0, 48, 60),
                'precipitation' => array_fill(0, 48, 0.5),
                'rain' => array_fill(0, 48, 0.5),
                'temperature_2m' => array_fill(0, 48, 29),
                'relative_humidity_2m' => array_fill(0, 48, 82),
                'wind_speed_10m' => array_fill(0, 48, 12.3),
            ],
            'daily' => [
                'time' => [now('Asia/Manila')->toDateString()],
                'precipitation_sum' => [12],
                'rain_sum' => [12],
                'temperature_2m_max' => [31],
                'temperature_2m_min' => [24],
                'precipitation_probability_max' => [60],
            ],
        ];
    }
}
