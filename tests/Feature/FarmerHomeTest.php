<?php

namespace Tests\Feature;

use App\Jobs\PrepareFarmerYieldEstimate;
use App\Models\FarmerProfile;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Farmer Home (2026-10): five plain parts from saved data — the main message, weather today, barangay risk, expected
 * yield this season, the next 7 days with the latest planting advice — and the typhoon check-in only during a typhoon.
 */
class FarmerHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.weather.latitude' => 14.033, 'services.weather.longitude' => 120.650, 'services.weather.timezone' => 'Asia/Manila',
            'services.weather.cache_minutes' => 5, 'services.weather.forecast_days' => 7, 'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
            // The yield estimate is made by a queued job; here it runs at once, as if the job had finished.
            'services.farming_ai.queue_connection' => 'sync',
        ]);
        $this->fakeWeather();
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
        $this->mockRisk('No Data');
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn(null));
    }

    // ------------------------------------------------------------ helpers

    /** @param  array<int, float>  $rain  rainfall per forecast day (index 0 = today) */
    private function weatherPayload(array $rain, float $wind = 8.4, int $code = 53, float $chance = 60): array
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = count($rain);

        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $today->addHours(15)->format('Y-m-d\TH:i'), 'temperature_2m' => 28.3, 'relative_humidity_2m' => 82,
                'apparent_temperature' => 33.7, 'precipitation' => 0.2, 'rain' => 0.2, 'showers' => 0, 'weather_code' => $code,
                'cloud_cover' => 90, 'pressure_msl' => 1010, 'surface_pressure' => 1008, 'wind_speed_10m' => $wind, 'wind_direction_10m' => 90,
                'wind_gusts_10m' => 15, 'is_day' => 1,
            ],
            'daily' => [
                'time' => array_map(fn ($i) => $today->addDays($i)->toDateString(), range(0, $days - 1)),
                'weather_code' => array_fill(0, $days, $code),
                'temperature_2m_max' => array_fill(0, $days, 31.0), 'temperature_2m_min' => array_fill(0, $days, 24.0),
                'apparent_temperature_max' => array_fill(0, $days, 34.0), 'apparent_temperature_min' => array_fill(0, $days, 26.0),
                'sunrise' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T05:42', range(0, $days - 1)),
                'sunset' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T18:20', range(0, $days - 1)),
                'precipitation_sum' => $rain, 'rain_sum' => $rain, 'showers_sum' => array_fill(0, $days, 0.0),
                'precipitation_probability_max' => array_fill(0, $days, $chance), 'wind_speed_10m_max' => array_fill(0, $days, $wind),
                'wind_gusts_10m_max' => array_fill(0, $days, 20.0),
            ],
            'hourly' => ['time' => [$today->format('Y-m-d').'T15:00'], 'temperature_2m' => [28.3], 'relative_humidity_2m' => [82],
                'precipitation_probability' => [60], 'precipitation' => [0.2], 'rain' => [0.2]],
        ];
    }

    private function fakeWeather(array $rain = [4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], float $wind = 8.4): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.open-meteo.com/*' => Http::response($this->weatherPayload($rain, $wind), 200), '*' => Http::response([], 500)]);
    }

    /** What iclimate:refresh-weather-cache stores; the page only reads it. */
    private function storeWeather(): void
    {
        app(FarmerDashboardWeatherService::class)->current(force: true);
        app(WeatherApiService::class)->forecast(refresh: true);
    }

    private function farmer(?float $area = 2.0, ?string $barangay = 'Binubusan', array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => User::ROLE_FARMER, 'name' => 'John Luke Reynon', 'barangay' => $barangay]);
        if ($barangay !== null) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => $area, 'farm_type' => 'Irrigated']);
        }

        return $user;
    }

    /** @param  bool  $estimate  the farmer's estimate was already prepared (the warm-up or a background run) */
    private function home(?User $as = null, bool $withWeather = true, bool $estimate = false)
    {
        $as ??= $this->farmer();
        Cache::flush();
        if ($withWeather) {
            $this->storeWeather();
        }
        if ($estimate) {
            app(FarmerDashboardService::class)->prepareYieldEstimate($as->load('farmerProfile'));
        }

        return $this->actingAs($as)->get(route('farmer.dashboard'))->assertOk();
    }

    private function mockPrediction(float $yield): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn([
            'success' => true, 'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield, 'target_month' => now()->format('Y-m'),
            'input_sources' => ['area' => ['value' => 2.0, 'source' => 'farmer_profile']],
        ]));
    }

    /** The Home badge is the Heat Map's [This Week] rating (2026-10); the planting rule still comes from the barangay record. */
    private function mockRisk(string $level, string $planting = 'Maintain normal irrigation monitoring.'): void
    {
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andReturn(
            ['risk_level' => $level, 'barangay' => 'Binubusan', 'advisory' => ['planting' => $planting], 'updated_at' => now()->toIso8601String()]
        ));
        $week = ['Low' => 'low', 'Moderate' => 'moderate', 'High' => 'high'][$level] ?? 'nodata';
        $this->mock(BarangayWeekRiskService::class, fn ($mock) => $mock->shouldReceive('forBarangay')->andReturn(
            ['barangay' => 'Binubusan', 'level' => $week, 'reason' => 'calm', 'reasons' => [], 'updated_at' => now('Asia/Manila')->toImmutable()]
        ));
    }

    private function lianYields(float $yield): void
    {
        $rows = array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => $yield, 'split' => null], range(1, 12));
        $this->app->instance(ProjectYieldDataset::class, new class($rows) extends ProjectYieldDataset
        {
            public function __construct(private array $custom) {}

            public function available(): bool
            {
                return true;
            }

            public function rows(): Collection
            {
                return collect($this->custom);
            }
        });
    }

    private function text(string $html): string
    {
        // From the end of the dashboard's own opening tag, so its data-* attributes are not read as page text.
        $start = strpos($html, '>', (int) strpos($html, 'data-farmer-dashboard')) + 1;

        return (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#s', '', substr($html, $start)))));
    }

    // ------------------------------------------------------------ layout

    public function test_home_has_exactly_the_five_parts_in_reading_order(): void
    {
        $html = $this->home()->getContent();

        preg_match_all('/data-slot="([a-z]+)"/', $html, $m);
        $this->assertSame(['message', 'typhoon', 'weather', 'risk', 'harvest', 'week'], $m[1]);
        $this->assertMatchesRegularExpression('/data-slot="typhoon"\s+hidden/', $html, 'no typhoon: the check-in is hidden');
        foreach (["Today's Message", 'Weather Today', 'Barangay Risk', 'Expected Yield This Season', 'Next 7 Days', 'Latest Planting Advice'] as $title) {
            $this->assertStringContainsString($title, $this->text($html));
        }
    }

    public function test_the_removed_sections_are_gone(): void
    {
        $html = $this->home()->getContent();

        foreach (['My Farm Today', "Today's Highlights", 'Quick Actions', 'Reminders', 'Planting guide', 'This Month', 'Past Harvest',
            'data-mfd-popup', 'Complete Farm Information', 'data-slot="today"', 'data-slot="highlights"', 'data-slot="quick"'] as $gone) {     // Home has two charts again since 2026-10 (FarmerNaicelFeaturesTest)
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    public function test_the_page_is_one_request_and_never_calls_the_provider_or_the_model(): void
    {
        Queue::fake();                                                                         // before the farmer: a new profile queues its estimate too
        $farmer = $this->farmer();
        $this->storeWeather();
        $sent = count(Http::recorded());
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));

        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        $this->assertSame($sent, count(Http::recorded()), 'no weather provider call on page load');
        Queue::assertPushed(PrepareFarmerYieldEstimate::class);
    }

    // ------------------------------------------------------------ 2. weather in words

    public function test_rain_and_wind_are_words_never_millimetres(): void
    {
        $this->fakeWeather([30.0, 12.0, 2.0, 0.0, 0.0, 0.0, 0.0], wind: 25.0);
        $text = $this->text($this->home()->getContent());

        $this->assertStringContainsString('Heavy rain', $text);
        $this->assertStringContainsString('Moderate rain', $text);
        $this->assertStringContainsString('Light rain', $text);
        $this->assertStringContainsString('Strong wind', $text);
        $this->assertStringContainsString('28°C', $text);
        $this->assertDoesNotMatchRegularExpression('/\d\s*mm\b/', $text);
    }

    public function test_the_current_condition_and_todays_rain_say_which_is_which(): void
    {
        $this->fakeWeather([12.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], wind: 16.0);       // drizzle now, moderate rain over the whole day
        $html = $this->home()->getContent();
        $weather = $this->section($html, 'data-fh-weather');

        $this->assertStringContainsString('<span class="fh-when">Now:</span> ', $weather);
        $this->assertStringContainsString('<span class="fh-when">All day:</span> Moderate rain', $weather);
        $this->assertStringContainsString('<span class="fh-when">Now:</span> Windy', $weather);

        $fil = $this->section($this->home($this->farmer(attributes: ['email' => 'fil@example.test', 'preferred_language' => 'fil']))->getContent(), 'data-fh-weather');
        $this->assertStringContainsString('<span class="fh-when">Ngayon:</span>', $fil);
        $this->assertStringContainsString('<span class="fh-when">Buong araw:</span> Katamtamang ulan', $fil);
    }

    public function test_a_forecast_day_never_names_two_different_rains(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        $payload = $this->weatherPayload([4.0, 4.0, 4.0, 4.0, 4.0, 4.0, 4.0], code: 65);  // "Heavy Rain" code, 4 mm in the day
        Http::fake(['api.open-meteo.com/*' => Http::response($payload, 200), '*' => Http::response([], 500)]);

        $week = strip_tags($this->section($this->home()->getContent(), 'data-fh-week'));

        $this->assertStringContainsString('Light rain', $week);
        $this->assertStringNotContainsString('Heavy Rain', $week);
    }

    public function test_filipino_words_for_rain_wind_and_risk(): void
    {
        $farmer = $this->farmer(attributes: ['preferred_language' => 'fil']);
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], wind: 16.0);
        $this->mockRisk('Moderate');
        $text = $this->text($this->home($farmer)->getContent());

        $this->assertStringContainsString('Mensahe Ngayon', $text);
        $this->assertStringContainsString('Mahangin', $text);
        $this->assertStringContainsString('Katamtaman', $text);
        $this->assertStringContainsString('Susunod na 7 Araw', $text);
    }

    public function test_old_saved_weather_is_shown_with_its_time_when_the_provider_is_down(): void
    {
        $farmer = $this->farmer();
        $this->storeWeather();                                                                   // saved by the scheduled refresh
        $this->travel(3)->hours();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response([], 500)]);
        $this->storeWeather();                                                                   // the next refresh fails: the last reading stays
        Cache::forget(FarmerDashboardService::cacheKey($farmer));

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $weather = $this->section($html, 'data-fh-weather');

        $this->assertStringContainsString('28°C', $weather);
        $this->assertMatchesRegularExpression('/class="ic-updated"[^>]*>Updated: /', $weather);
        $this->assertStringNotContainsString('No data yet', $weather);
    }

    public function test_with_no_saved_weather_at_all_each_card_says_no_data_yet_never_an_error(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response([], 500)]);

        $html = $this->home($this->farmer(), withWeather: false)->getContent();

        foreach (['data-fh-weather', 'data-fh-week'] as $card) {
            $section = strtolower(strip_tags($this->section($html, $card)));
            $this->assertStringContainsString('no data yet', $section, $card);
            $this->assertStringNotContainsString('unavailable', $section, $card);
            $this->assertStringNotContainsString('try again', $section, $card);
        }
    }

    /** One card's HTML, from its marker attribute to the end of its <section>. */
    private function section(string $html, string $marker): string
    {
        $start = strrpos(substr($html, 0, (int) strpos($html, $marker)), '<section');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    // ------------------------------------------------------------ 3. barangay risk

    public function test_risk_is_one_badge_with_the_heat_maps_week_rating(): void
    {
        foreach (['Low' => ['Low', 'is-good'], 'Moderate' => ['Moderate', 'is-watch'], 'High' => ['High', 'is-danger']] as $level => [$label, $tone]) {
            $this->mockRisk($level);
            $html = $this->home($this->farmer(attributes: ['email' => strtolower($level).'@example.test']))->getContent();
            $this->assertMatchesRegularExpression('#<p class="fh-risk-badge '.$tone.'">'.$label.'</p>#', $html, $level);
        }
    }

    public function test_no_rating_is_never_shown_as_low_and_no_barangay_goes_to_the_notification_bell(): void
    {
        $html = $this->home()->getContent();
        $this->assertStringNotContainsString('fh-risk-badge', $html);
        $this->assertStringContainsString('No data yet', $this->text($html));

        // 2026-10 (Naicel): a missing barangay is a notification in the bell, not a card on Home.
        $noBarangay = $this->farmer(barangay: null, attributes: ['email' => 'nb@example.test']);
        $html = $this->home($noBarangay)->getContent();
        $this->assertStringNotContainsString('Add your barangay in your profile to see the risk.', $this->text($html));
        $this->assertStringNotContainsString('data-fh-risk', $html);
        $this->assertDatabaseHas('notifications', ['user_id' => $noBarangay->id, 'category' => 'setup:barangay', 'title' => 'Add your barangay']);
    }

    // ------------------------------------------------------------ 4. expected yield this season

    public function test_yield_is_one_number_for_this_season_with_normal_higher_or_lower(): void
    {
        $this->lianYields(4.0);
        foreach ([4.1 => 'Normal', 5.0 => 'Higher', 3.0 => 'Lower'] as $yield => $label) {
            $this->mockPrediction((float) $yield);
            $html = $this->home($this->farmer(attributes: ['email' => 'y'.$yield.'@example.test']), estimate: true)->getContent();
            $this->assertStringContainsString(number_format((float) $yield, 2).' <small>t/ha</small>', $html);
            $this->assertStringContainsString('>'.$label.'</p>', $html, (string) $yield);
        }
        $season = \App\Support\RiceSeason::current() === 'Wet' ? 'Wet season' : 'Dry season';
        $this->assertStringContainsString($season, $this->text($html));
    }

    public function test_an_estimate_not_saved_yet_is_a_placeholder(): void
    {
        Queue::fake();
        $html = $this->home($this->farmer())->getContent();

        $this->assertStringContainsString('data-mfd-load="harvest"', $html);
        $this->assertStringContainsString('Your estimate is being prepared.', $html);
    }

    public function test_an_unreachable_model_service_is_a_calm_note_and_is_tried_again_soon(): void
    {
        $farmer = $this->farmer();                       // the default mock answers nothing, as an unreachable service does
        $text = $this->text($this->home($farmer)->getContent());

        $this->assertStringContainsString('No data yet', $text);
        $this->assertStringContainsString('It appears here after the next update.', $text);
        $this->travel(6)->minutes();
        $this->assertNull(Cache::get(FarmerDashboardService::predictionCacheKey($farmer->id)), 'a service failure is kept only 5 minutes');
    }

    public function test_a_missing_farm_size_goes_to_the_notification_bell(): void
    {
        // 2026-10 (Naicel): a missing farm size is a notification in the bell, not a card on Home.
        $farmer = $this->farmer(area: null);
        $html = $this->home($farmer)->getContent();

        $this->assertStringNotContainsString('Add your farm size to see your estimate.', $this->text($html));
        $this->assertStringNotContainsString('data-fh-harvest', $html);
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'category' => 'setup:farm_area', 'title' => 'Add your farm size']);
    }

    public function test_the_harvest_check_only_reads_and_answers_once_the_estimate_is_saved(): void
    {
        Queue::fake();
        $farmer = $this->farmer();
        Cache::forget(FarmerDashboardService::predictionCacheKey($farmer->id));
        $this->storeWeather();
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));

        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk()->json();
        $this->assertSame([], $json['slots'], 'still preparing: nothing replaces the placeholder');

        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id), ['success' => true, 'yield_tons_per_hectare' => 4.2, 'target_month' => now()->format('Y-m')], now()->addHour());
        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest-check']))->assertOk()->json();
        $this->assertSame(['harvest'], array_keys($json['slots']));
        $this->assertStringContainsString('4.20', $json['slots']['harvest']);
    }

    // ------------------------------------------------------------ 1. the main message

    public function test_the_message_follows_the_most_important_thing_first(): void
    {
        $this->mockRisk('Low', 'Ideal planting period.');
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
        $this->assertStringContainsString('Good weather for planting this week.', $this->text($this->home()->getContent()));

        // Never cheerful next to a Moderate rating.
        $this->mockRisk('Moderate', 'Ideal planting period.');
        $this->assertStringContainsString('You may plant this week; keep an eye on the rain.', $this->text($this->home($this->farmer(attributes: ['email' => 'm@example.test']))->getContent()));

        // Heavy rain today beats the planting rule.
        $this->fakeWeather([40.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
        $this->assertStringContainsString('Heavy rain is expected today.', $this->text($this->home($this->farmer(attributes: ['email' => 'h@example.test']))->getContent()));

        // High risk beats heavy rain.
        $this->mockRisk('High');
        $this->assertStringContainsString('Farm risk in your barangay is high.', $this->text($this->home($this->farmer(attributes: ['email' => 'r@example.test']))->getContent()));
    }

    public function test_the_message_never_says_good_to_plant_when_the_planting_advice_below_says_to_wait(): void
    {
        $this->mockRisk('Low', 'Ideal planting period.');
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]);
        PlantingAdvisory::factory()->create([
            'title' => '16-Day Consider Delaying Planting Activities', 'advisory_type' => PlantingAdvisory::TYPE_PLANTING, 'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'target_barangay' => 'Binubusan', 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(3), 'published_at' => now()->subDay(),
        ]);

        $text = $this->text($this->home()->getContent());

        $this->assertStringNotContainsString('Good weather for planting this week.', $text);
        $this->assertStringContainsString('Be careful if you plant this week.', $text);
    }

    // ------------------------------------------------------------ 5. week and advisory

    public function test_seven_days_and_the_latest_planting_advisory_with_see_all(): void
    {
        PlantingAdvisory::factory()->create([
            'title' => 'Plant after the rain', 'advisory_type' => PlantingAdvisory::TYPE_PLANTING, 'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'target_barangay' => 'Binubusan', 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(3), 'published_at' => now()->subDay(),
        ]);
        $html = $this->home()->getContent();

        $this->assertSame(7, substr_count($html, '<li class="fh-day'));
        $this->assertStringContainsString('Plant after the rain', $html);
        $this->assertStringContainsString('See all', $html);
        $this->assertStringContainsString(route('planting-advisories.index'), $html);
    }

    // ------------------------------------------------------------ typhoon check-in

    public function test_typhoon_check_in_appears_only_during_an_active_typhoon(): void
    {
        $this->assertStringNotContainsString('data-fh-typhoon', $this->home()->getContent());

        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn([
            'key' => 'advisory-1', 'title' => 'Typhoon Ompong', 'severity' => 'High', 'source' => 'PAGASA', 'valid_until' => null, 'reason' => 'x',
        ]));
        $farmer = $this->farmer(attributes: ['email' => 't@example.test']);
        $html = $this->home($farmer)->getContent();

        $this->assertStringContainsString('data-fh-typhoon', $html);
        $this->assertStringContainsString(route('typhoon-safety.store'), $html);
        $this->assertStringContainsString('A typhoon is near.', $this->text($html));

        TyphoonSafetyResponse::query()->create(['user_id' => $farmer->id, 'event_key' => 'advisory-1', 'event_title' => 'Typhoon Ompong', 'status' => 'safe', 'responded_at' => now()]);
        $this->assertStringContainsString('Your answer:', $this->text($this->home($farmer)->getContent()));
    }

    // ------------------------------------------------------------ refresh and language

    public function test_the_live_endpoint_returns_every_part(): void
    {
        $farmer = $this->farmer();
        $this->storeWeather();

        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live'))->assertOk()->json();

        $this->assertSame(['message', 'typhoon', 'weather', 'risk', 'harvest', 'week'], array_keys($json['slots']));
    }

    public function test_the_language_choice_is_saved_and_the_numbers_stay(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.language'), ['language' => 'fil'])->assertOk();

        $this->assertSame('fil', $farmer->fresh()->preferred_language);
        $html = $this->home($farmer->fresh())->getContent();
        $this->assertStringContainsString('Panahon Ngayon', $html);
        $this->assertStringContainsString('28°C', $html);
    }

    public function test_english_and_filipino_have_the_same_keys(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                $out = array_merge($out, is_array($v) && $v !== [] ? $flatten($v, $prefix.$k.'.') : [$prefix.$k]);
            }

            return $out;
        };
        $en = $flatten(require lang_path('en/farmer.php'));
        $fil = $flatten(require lang_path('fil/farmer.php'));
        $this->assertSame([], array_values(array_diff(array_filter($en, fn ($k) => str_starts_with($k, 'home.') || str_starts_with($k, 'nav.') || str_starts_with($k, 'my_farm_page.') || str_starts_with($k, 'announcements.')), $fil)));
    }

    public function test_no_technical_terms_on_home(): void
    {
        $text = strtolower($this->text($this->home()->getContent()));

        foreach (['random forest', 'rmse', 'api', 'model', 'json', 'exception', 'null'] as $term) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($term, '/').'\b/', $text, $term);
        }
    }
}
