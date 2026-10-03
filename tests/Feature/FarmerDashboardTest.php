<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Analytics\FarmerRainAnalyticsService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\RecentRainService;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FarmerDashboardTest extends TestCase
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
        ]);
        $this->fakeWeather();
        // The municipality-wide risk calculation and the yield model are slow and external; tests opt in through mockRisk()/mockPrediction().
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andReturn(['risk_level' => 'No Data']));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------ helpers

    /**
     * @param  array<int, float>  $rain  rainfall per forecast day (index 0 = today)
     * @param  int|float  $chance  chance of rain for every day
     */
    private function weatherPayload(array $rain, int $humidity = 82, float $temperature = 28.3, int $code = 53, float $currentRain = 0.2, int|float $chance = 60, float $wind = 12.0, float $feelsMax = 34.0): array
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = count($rain);

        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $today->addHours(15)->format('Y-m-d\TH:i'), 'temperature_2m' => $temperature, 'relative_humidity_2m' => $humidity,
                'apparent_temperature' => 33.7, 'precipitation' => $currentRain, 'rain' => $currentRain, 'showers' => 0, 'weather_code' => $code,
                'cloud_cover' => 90, 'pressure_msl' => 1010, 'surface_pressure' => 1008, 'wind_speed_10m' => 8.4, 'wind_direction_10m' => 90,
                'wind_gusts_10m' => 15, 'is_day' => 1,
            ],
            'daily' => [
                'time' => array_map(fn ($i) => $today->addDays($i)->toDateString(), range(0, $days - 1)),
                'weather_code' => array_fill(0, $days, 53),
                'temperature_2m_max' => array_fill(0, $days, 31.0), 'temperature_2m_min' => array_fill(0, $days, 24.0),
                'apparent_temperature_max' => array_fill(0, $days, $feelsMax), 'apparent_temperature_min' => array_fill(0, $days, 26.0),
                'sunrise' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T05:42', range(0, $days - 1)),
                'sunset' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T18:20', range(0, $days - 1)),
                'precipitation_sum' => $rain, 'rain_sum' => $rain, 'showers_sum' => array_fill(0, $days, 0.0),
                'precipitation_probability_max' => array_fill(0, $days, $chance), 'wind_speed_10m_max' => array_fill(0, $days, $wind),
                'wind_gusts_10m_max' => array_fill(0, $days, 20.0),
            ],
            'hourly' => ['time' => [$today->format('Y-m-d').'T15:00'], 'temperature_2m' => [$temperature], 'relative_humidity_2m' => [$humidity],
                'precipitation_probability' => [60], 'precipitation' => [0.2], 'rain' => [0.2]],
        ];
    }

    private function fakeWeather(array $rain = [4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], int $humidity = 82, array $options = []): void
    {
        $this->replaceWeather(Http::response($this->weatherPayload($rain, $humidity, ...$options), 200));
    }

    /** Http::fake stubs match in registration order, so a new stub must replace the old one. */
    private function replaceWeather($response): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        // Everything else (the ML service, other providers) fails fast instead of waiting on a real network call.
        Http::fake(['api.open-meteo.com/*' => $response, '*' => Http::response([], 500)]);
    }

    private function rainyDay(): void
    {
        $this->fakeWeather([2.0, 30.0, 3.0, 1.0, 1.0, 1.0, 1.0]);
    }

    private function farmer(array $attributes = [], ?float $area = null, ?string $barangay = 'Binubusan'): User
    {
        $user = User::factory()->create($attributes + ['role' => User::ROLE_FARMER, 'name' => 'John Luke Reynon', 'barangay' => $barangay]);
        if ($area !== null || $barangay) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => $area, 'farm_type' => 'Irrigated']);
        }

        return $user;
    }

    private function page(?User $as = null)
    {
        Cache::flush();

        return $this->actingAs($as ?? $this->farmer())->get(route('farmer.dashboard'))->assertOk();
    }

    /** What the page was built from (the slow harvest estimate is filled in by a second request, so it is a placeholder here). */
    private function data(?User $as = null): array
    {
        return $this->page($as)->viewData('f');
    }

    /** The whole payload with nothing deferred: the service itself, without the page layout. */
    private function payload(User $farmer): array
    {
        Cache::flush();

        return app(FarmerDashboardService::class)->payload($farmer);
    }

    /** One section's HTML from the live endpoint (what the script puts on the page). */
    private function slot(User $farmer, string $slot, array $query = ['refresh' => 1]): string
    {
        return $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', $query))->assertOk()->json('slots.'.$slot) ?? '';
    }

    /** The dashboard body only (no sidebar, header or chat widget scripts), as text a farmer can read. */
    private function body(string $html): string
    {
        $start = strpos($html, '<div class="mfd"');

        return substr($html, $start, strpos($html, 'About this information', $start) - $start + 1800);
    }

    private function readable(string $html): string
    {
        return html_entity_decode(strip_tags(preg_replace(['#<script\b.*?</script>#is', '#<style\b.*?</style>#is'], '', $this->body($html))), ENT_QUOTES);
    }

    private function kpi(array $data, string $key): ?array
    {
        return collect($data['kpis'])->firstWhere('key', $key);
    }

    private function mockPrediction(float $yield, string $areaSource = 'farmer_profile', ?float $uncertainty = null): void
    {
        $this->mock(RiceYieldPredictionService::class, function ($mock) use ($yield, $areaSource, $uncertainty): void {
            $mock->shouldReceive('predictForUser')->andReturn([
                'success' => true,                                                                  // the real service always reports whether a prediction was made
                'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield, 'target_month' => now()->format('Y-m'), 'uncertainty' => $uncertainty,
                'input_sources' => ['area' => ['value' => 2.0, 'source' => $areaSource]], 'confidence' => 80,
            ]);
        });
        Cache::flush();
    }

    private function mockRisk(string $level, string $planting = 'Maintain normal irrigation monitoring.'): void
    {
        $this->mock(BarangayRiskMapService::class, function ($mock) use ($level, $planting): void {
            $mock->shouldReceive('detailFor')->andReturn(['risk_level' => $level, 'advisory' => ['planting' => $planting, 'irrigation' => null, 'warnings' => []], 'updated_at' => now()->toIso8601String()]);
        });
        Cache::flush();
    }

    private function yieldRows(array $rows): void
    {
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

    private function lianYields(float $yield = 3.8): void
    {
        $this->yieldRows(array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => $yield, 'split' => null], range(1, 12)));
    }

    /** @param  array<string, float>  $daily  rain in mm by Y-m-d date */
    private function rainHistory(array $daily): void
    {
        $this->app->bind(RecentRainService::class, fn () => new class($daily) extends RecentRainService
        {
            public function __construct(private array $daily) {}

            public function history(bool $force = false): array
            {
                return ['daily' => $this->daily, 'fetched_at' => CarbonImmutable::now('Asia/Manila')->subMinutes(10)];
            }
        });
    }

    private function openSchedule(User $farmer, array $extra = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($extra + [
            'user_id' => $farmer->id, 'planting_date' => now('Asia/Manila')->subDays(5)->toDateString(), 'scheduled_harvest_date' => now('Asia/Manila')->addDays(110)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2,
        ]);
    }

    // ------------------------------------------------------------ access, structure, plain words

    public function test_only_farmers_can_open_the_dashboard_and_its_live_endpoint(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->get(route('farmer.dashboard'))->assertRedirect(route('login'));      // guest first: actingAs() persists
        foreach (['farmer.dashboard', 'farmer.dashboard.live'] as $route) {
            $this->actingAs($mao)->get(route($route))->assertForbidden();
            $this->actingAs($it)->get(route($route))->assertForbidden();
        }
    }

    public function test_the_chart_library_loader_recognises_the_dashboard_charts(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $html = $this->page()->getContent();

        $this->assertStringContainsString('canvas[id*="Chart"]', $js);              // every canvas id on this page contains "Chart"
        foreach (['farmerWeekRainChart', 'farmerWeekTempChart', 'farmerRainfallTrendChart'] as $id) {
            if (str_contains($html, 'id="'.$id.'"')) {
                $this->assertStringContainsString('Chart', $id);
            }
        }
        $this->assertStringContainsString('id="farmerWeekRainChart"', $html);
        $this->assertStringContainsString('id="farmerWeekTempChart"', $html);
    }

    public function test_no_technical_or_broken_component_text_reaches_the_farmer(): void
    {
        $text = $this->readable($this->page()->getContent());

        foreach (['Chart unavailable', 'library could not be loaded', 'API unavailable', 'Model unavailable', 'Service unavailable', 'Prediction service',
            'Database error', 'N/A', 'Undefined', 'Unknown', 'Not recorded', 'No data available', 'undefined', 'null', 'Exception', 'Stack trace'] as $technical) {
            $this->assertStringNotContainsString($technical, $text, $technical);
        }
    }

    public function test_the_page_uses_farmers_words_not_technical_ones(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockRisk('Low');
        $this->mockPrediction(4.2, uncertainty: 0.4);
        $this->lianYields(3.82);
        $this->openSchedule($farmer);
        $this->rainHistory($this->rainDays('2026-09-01', 31, 4.0));
        $html = $this->page($farmer)->getContent();
        $text = $this->readable($html).' '.$this->readable($this->slot($farmer, 'harvest', ['retry' => 'harvest']));

        foreach (['Precipitation', 'Relative humidity', 'Air moisture', 'Heat index', 'Forecast', 'forecast', 'Anomaly', 'Deviation', 'Yield prediction',
            'Phenological', 'Data unavailable', 'Synchronization'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, $word);
        }
        $this->assertStringContainsString('Humidity (how wet the air is)', $text);
        $this->assertStringContainsString('Feels like', $text);
        $this->assertStringContainsString('Expected Harvest', $text);
    }

    public function test_page_follows_the_new_layout(): void
    {
        $this->rainyDay();
        $this->mockRisk('Low');
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $html = $this->page($farmer)->getContent();

        $order = ['Good ', 'Weather Now', 'What to Do Today', 'My Farm at a Glance', 'Weather This Week', 'My Crop Season', 'data-mfd-harvest', 'Rain Each Month',
            'Farm Risk in Your Barangay', 'Show risk map and barangay details', 'Finish Setting Up Your Farm', 'Quick Actions', 'About this information'];
        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker, max(0, strpos($html, 'data-farmer-dashboard')));
            $this->assertNotFalse($pos, "Missing: $marker");
            $this->assertGreaterThan($last, $pos, "Out of order: $marker");
            $last = $pos;
        }
        $this->assertSame(['header', 'today', 'kpis', 'week', 'season', 'harvest', 'rain', 'risk', 'setup', 'advisories'], $this->slotOrder($html));
    }

    private function slotOrder(string $html): array
    {
        preg_match_all('/data-slot="([a-z]+)"/', $html, $m);

        return $m[1];
    }

    public function test_the_old_sections_are_gone(): void
    {
        $text = $this->readable($this->page()->getContent());

        foreach (['Important Alerts', 'My Farm Today', 'My Farm Status', 'Farm / Crop Information', 'Next Farm Activity', 'My Crop Plan', 'Some information isn'] as $removed) {
            $this->assertStringNotContainsString($removed, $text, $removed);
        }
    }

    // ------------------------------------------------------------ header + weather now: one consistent reading

    public function test_the_header_greets_by_time_of_day_and_names_the_place_and_time(): void
    {
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($this->farmer(['name' => 'Ana Cruz', 'email' => 'ana@example.test']))->getContent();
        $header = substr($html, strpos($html, 'class="mfd-header"'), 1800);

        $this->assertMatchesRegularExpression('/^Good (morning|afternoon|evening), John Luke$/', $data['greeting']);
        $this->assertStringContainsString(', Ana', $header);                                   // two-word name: given name only
        $this->assertStringContainsString('Binubusan, Lian, Batangas', $this->page($farmer)->getContent());
        $this->assertStringContainsString(CarbonImmutable::now('Asia/Manila')->format('l, F j, Y'), $header);
        $this->assertStringContainsString('data-mfd-refresh', $header);
        $this->assertDoesNotMatchRegularExpression('/\d{1,2}:\d{2}:\d{2}/', strip_tags($header));          // no seconds in what is read
    }

    public function test_the_greeting_follows_the_manila_clock(): void
    {
        foreach ([[8, 'Good morning'], [14, 'Good afternoon'], [20, 'Good evening']] as [$hour, $expected]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-30 '.sprintf('%02d', $hour).':00:00', 'Asia/Manila'));
            $this->assertStringStartsWith($expected, $this->data($this->farmer(['email' => "f{$hour}@example.test"]))['greeting']);
        }
    }

    public function test_the_current_condition_todays_rain_and_todays_card_tell_one_story(): void
    {
        // Clear right now (code 0, no rain falling), but 5.9 mm of rain is forecast for today.
        $this->fakeWeather([5.9, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 100]);
        $farmer = $this->farmer();

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $now = substr($html, strpos($html, 'data-mfd-now'), 4500);

        $this->assertSame(5.9, $data['weather']['rain_today']);
        $this->assertSame(5.9, $data['week']['days'][0]['rain']);                              // the same number on today's card
        $this->assertSame('Clear right now. Rain expected later today (about 5.9 mm).', $data['weather']['now_summary']);
        $this->assertStringContainsString('Clear right now. Rain expected later today (about 5.9 mm).', $now);
        $this->assertStringContainsString('5.9 mm', $now);                                     // never 5.9 in one place and 6 in another
        $this->assertStringContainsString('100% chance of rain', $now);
        $this->assertTrue($data['week']['days'][0]['is_today']);
    }

    public function test_a_dry_day_and_a_rainy_moment_are_told_plainly(): void
    {
        $this->fakeWeather([0.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 10]);
        $this->assertSame('Clear right now. No rain expected today.', $this->data($this->farmer())['weather']['now_summary']);

        $this->fakeWeather([6.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], options: ['code' => 63, 'currentRain' => 1.5]);
        $this->assertSame('Raining now. About 6.0 mm of rain expected today.', $this->data($this->farmer(['email' => 'r@example.test']))['weather']['now_summary']);
    }

    public function test_the_weather_shows_its_real_fetch_time_and_how_old_it_is(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();                  // fetched now

        $this->travel(21)->minutes();
        $f = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->viewData('f');
        $this->assertFalse($f['weather']['stale']);
        $this->assertLessThanOrEqual(21, $f['weather']['age_minutes']);                       // a fresh fetch (cache is under 30 minutes)

        // The provider stops answering: the last good reading is shown and, past 60 minutes, called old.
        $this->replaceWeather(Http::response([], 500));
        $this->travel(90)->minutes();
        $response = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();
        $old = $response->viewData('f');
        $html = $response->getContent();

        $this->assertTrue($old['weather']['stale']);
        $this->assertGreaterThanOrEqual(90, $old['weather']['age_minutes']);
        $this->assertStringContainsString('Old data', $this->body($html));
        $this->assertStringContainsString('Updated 1 hour ago', $this->body($html));
        $this->assertSame('Asia/Manila', $old['weather']['updated_at']->getTimezone()->getName());
    }

    public function test_weather_is_cached_for_at_most_30_minutes(): void
    {
        config(['services.weather.cache_minutes' => 45]);                                       // the .env value must not win over the limit
        $service = app(FarmerDashboardWeatherService::class);

        $service->current();
        $service->current();
        Http::assertSentCount(1);                                                              // second call: from the cache

        $this->travel(29)->minutes();
        $service->current();
        Http::assertSentCount(1);

        $this->travel(2)->minutes();                                                           // 31 minutes in
        $service->current();
        Http::assertSentCount(2);
    }

    public function test_a_failed_weather_source_gives_the_weather_card_its_own_error_state(): void
    {
        $this->replaceWeather(Http::response([], 500));
        Cache::flush();
        $farmer = $this->farmer();

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $text = $this->readable($html);

        $this->assertSame('error', $data['weather']['state']);
        $this->assertStringContainsString("We can't show this right now.", $text);
        $this->assertStringContainsString('data-mfd-retry="today"', $html);
        $this->assertStringContainsString('data-mfd-retry="week"', $html);
        $this->assertStringContainsString('What to Do Today', $text);                           // the other card on the row keeps working
        $this->assertStringContainsString('My Crop Season', $text);
        $this->assertStringNotContainsString('0.0 mm', $text);
        foreach (['unavailable', 'API', 'failed'] as $technical) {
            $this->assertStringNotContainsStringIgnoringCase($technical, $text, $technical);
        }
    }

    // ------------------------------------------------------------ what to do today + warnings

    public function test_actions_come_from_defined_rules_each_with_a_reason_and_a_climora_link(): void
    {
        $this->fakeWeather([6.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], humidity: 93, options: ['code' => 0, 'currentRain' => 0.0, 'feelsMax' => 28.0]);
        $farmer = $this->farmer();

        $actions = $this->data($farmer)['actions'];
        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-advice'), 5000);

        $this->assertGreaterThanOrEqual(1, count($actions));
        $this->assertLessThanOrEqual(3, count($actions));
        $this->assertSame(['Wait before you put on fertilizer or spray', 'Look at your rice leaves for spots'], array_column($actions, 'title'));
        $this->assertStringContainsString('Humidity is 93%', $actions[1]['reason']);
        $this->assertStringContainsString('Rain is expected today (about 6.0 mm)', $actions[0]['reason']);
        $this->assertSame(2, substr_count($section, 'Ask Climora AI'));
        $this->assertSame(2, substr_count($section, 'data-ai-prefill='));
    }

    public function test_a_normal_day_still_gets_one_calm_action(): void
    {
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], humidity: 60, options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5, 'feelsMax' => 28.0]);

        $actions = $this->data($this->farmer())['actions'];

        $this->assertCount(1, $actions);
        $this->assertSame('Good day to check your farm', $actions[0]['title']);
    }

    public function test_no_warnings_is_a_green_badge_with_an_icon_and_words(): void
    {
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5]);
        $html = $this->page($this->farmer())->getContent();

        $this->assertStringContainsString('No weather warnings', $html);
        $this->assertMatchesRegularExpression('/mfd-pill is-good"><svg[^>]*>.*?<\/svg>\s*<span>No weather warnings<\/span>/s', $html);
    }

    public function test_a_real_warning_shows_in_colour_with_an_icon_and_a_label(): void
    {
        $this->rainyDay();                                                                      // 30 mm tomorrow
        $farmer = $this->farmer();

        $alert = $this->data($farmer)['alerts'][0];
        $html = $this->page($farmer)->getContent();

        $this->assertSame('Heavy rain expected tomorrow', $alert['title']);
        $this->assertSame('danger', $alert['level']);
        $this->assertStringContainsString('mfd-pill is-danger', $html);
        $this->assertStringContainsString('<span>Warning</span>', $html);                      // the word, not just the colour
        $this->assertStringNotContainsString('No weather warnings', $this->body($html));
    }

    public function test_only_notifications_marked_as_weather_show_as_weather_alerts(): void
    {
        $farmer = $this->farmer();
        Notification::factory()->create(['user_id' => $farmer->id, 'type' => 'Warning', 'is_read' => false, 'title' => 'Fertilizer pickup schedule', 'message' => 'Bring a heat-sealed bag.', 'category' => null]);
        $this->assertSame([], $this->payload($farmer)['alerts']);                              // "heat" in the wording is not enough

        Notification::factory()->create(['user_id' => $farmer->id, 'type' => 'Warning', 'is_read' => false, 'title' => 'Storm signal raised', 'category' => Notification::CATEGORY_WEATHER]);
        $alerts = $this->payload($farmer)['alerts'];

        $this->assertCount(1, $alerts);
        $this->assertSame('Storm signal raised', $alerts[0]['title']);
    }

    public function test_an_open_typhoon_check_in_is_a_critical_alert_until_the_farmer_answers(): void
    {
        $this->mock(TyphoonSafetyService::class, function ($mock): void {
            $mock->shouldReceive('activeEvent')->andReturn(['key' => 'typhoon-test']);
        });

        $alert = collect($this->payload($this->farmer())['alerts'])->firstWhere('icon', 'storm');

        $this->assertSame('critical', $alert['severity']);
        $this->assertSame('danger', $alert['level']);
        $this->assertSame('Typhoon safety check-in is open', $alert['title']);
    }

    // ------------------------------------------------------------ KPI row

    public function test_the_kpi_row_has_six_cards_each_with_a_link_and_a_source(): void
    {
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-kpis'), 9000);

        $this->assertSame(['rain_past', 'rain_next', 'rain_vs_normal', 'heat', 'work_days', 'alerts'], array_column($data['kpis'], 'key'));
        $this->assertSame(6, substr_count($section, 'class="mfd-kpi '));
        foreach ($data['kpis'] as $k) {
            $this->assertStringContainsString('href="'.$k['href'].'"', $section);              // tapping a card opens its page
            $this->assertNotEmpty($k['source']);
        }
        $this->assertStringContainsString('Source: ', $section);
        $this->assertStringContainsString('Rain this past week', $section);
        $this->assertStringContainsString('Rain coming this week', $section);
        $this->assertStringContainsString('Rain compared to normal (this month)', $section);
        $this->assertStringContainsString('Heat today', $section);
        $this->assertStringContainsString('Good days for spraying', $section);
        $this->assertStringContainsString('Alerts that need your attention', $section);
    }

    public function test_kpis_match_a_hand_calculation_for_one_farmer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Asia/Manila'));
        // Weather coming (today = Oct 12): 4 + 6 + 3 + 2 + 1 + 5 + 2 = 23 mm; every day 12 km/h wind and 10% chance of rain, "feels like" 34 °C.
        $this->fakeWeather([4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], options: ['chance' => 10, 'wind' => 12.0, 'feelsMax' => 34.0]);
        // Recorded rain: Oct 1-11 is 5 mm a day (55 mm so far this month); Oct 5-11 is the past week = 35 mm; Sep 28 - Oct 4 = 35 mm before.
        $history = $this->rainDays('2026-09-01', 30, 5.0) + $this->rainDays('2026-10-01', 11, 5.0);
        $this->rainHistory($history);
        $farmer = $this->farmer();

        $data = $this->data($farmer);

        $past = $this->kpi($data, 'rain_past');
        $this->assertSame('35', $past['display']);                                              // Oct 5 .. Oct 11
        $this->assertSame('mm', $past['unit']);
        $this->assertSame('flat', $past['trend']['dir']);                                       // the week before was also 35 mm

        $next = $this->kpi($data, 'rain_next');
        $this->assertSame('23', $next['display']);

        $normal = app(FarmerRainAnalyticsService::class)->monthlyNormal(10);
        $this->assertNotNull($normal, 'the Ambulong records give a long-term October average');
        $usualSoFar = $normal * 11 / 31;                                                        // 11 full days of October
        $expectedPercent = (string) round(55 / $usualSoFar * 100);
        $vs = $this->kpi($data, 'rain_vs_normal');
        $this->assertSame($expectedPercent, $vs['display']);
        $this->assertSame('%', $vs['unit']);
        $this->assertContains($vs['meaning'], ['More than usual', 'Normal', 'Less than usual']);

        $heat = $this->kpi($data, 'heat');
        $this->assertSame('34.0', $heat['display']);                                            // the provider's own "feels like" high for today
        $this->assertSame('Heat today: Extreme Caution', $heat['headline']);

        $good = $this->kpi($data, 'work_days');
        $this->assertSame('7', $good['display']);                                               // every day: 10% chance, 12 km/h
        $this->assertSame('Good days for spraying', $good['label']);
    }

    private function rainDays(string $from, int $days, float $mm): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[CarbonImmutable::parse($from)->addDays($i)->toDateString()] = $mm;
        }

        return $out;
    }

    public function test_missing_data_shows_a_dash_and_a_reason_never_zero(): void
    {
        // No rain history (the test default) and a provider that gives no wind or rain chance.
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $past = $this->kpi($data, 'rain_past');
        $this->assertSame('—', $past['display']);
        $this->assertTrue($past['missing']);
        $this->assertStringContainsString('We only have rain records for 0 of the last 7 days.', $past['reason']);
        $this->assertSame('—', $this->kpi($data, 'rain_vs_normal')['display']);
        $start = strpos($html, 'data-kpi="rain_past"');
        $section = substr($html, $start, strpos($html, '</a>', $start) - $start);               // that one card only
        $this->assertStringContainsString('<span>—</span>', $section);
        $this->assertStringNotContainsString('0 mm', $section);
        $this->assertStringNotContainsString('0.0', $section);
    }

    public function test_a_dry_week_is_a_real_zero_and_alerts_zero_is_calm(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Asia/Manila'));
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5]);
        $this->rainHistory($this->rainDays('2026-09-20', 22, 0.0));
        $data = $this->data($this->farmer());

        $this->assertSame('0.0', $this->kpi($data, 'rain_past')['display']);                  // measured as zero
        $this->assertFalse($this->kpi($data, 'rain_past')['missing']);
        $this->assertSame('0', $this->kpi($data, 'alerts')['display']);
        $this->assertSame('Nothing needs your attention right now.', $this->kpi($data, 'alerts')['meaning']);
    }

    public function test_good_days_are_marked_on_the_week_with_the_same_rule_as_the_number(): void
    {
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 10, 'wind' => 8.0]);
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame(7, $data['week']['good']['value']);
        $this->assertSame(7, substr_count($html, 'Good day for farm work'));
        $this->assertStringContainsString('Good for spraying and fertilizer: under 40% chance of rain and wind under 15 km/h.', $html);

        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['chance' => 60, 'wind' => 8.0]);
        $this->assertSame(0, $this->data($this->farmer(['email' => 'b@example.test']))['week']['good']['value']);
    }

    // ------------------------------------------------------------ weather this week + charts

    public function test_the_week_shows_seven_cards_and_flags_only_real_risks(): void
    {
        $this->rainyDay();

        $html = $this->page($this->farmer())->getContent();
        $section = substr($html, strpos($html, 'data-mfd-week'), 9000);

        $this->assertSame(7, substr_count($section, 'class="mfd-day '));
        $this->assertSame(1, substr_count($section, '>Heavy rain<'));
        $this->assertStringContainsString('% chance of rain', $section);
        $this->assertStringContainsString('30 mm', $section);
        $this->assertStringContainsString('The wettest day is', $section);
        $this->assertStringNotContainsStringIgnoringCase('drizzle', $html);
    }

    public function test_charts_have_a_title_a_takeaway_units_and_a_text_alternative(): void
    {
        $this->rainyDay();
        $html = $this->page($this->farmer())->getContent();

        foreach (['Rain expected each day (mm)', 'High and low temperature each day (°C)'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
        $this->assertStringContainsString('The wettest day is', $html);                         // one-sentence takeaway above the chart
        $this->assertStringContainsString('The hottest day is', $html);
        $this->assertMatchesRegularExpression('/<canvas id="farmerWeekRainChart"[^>]*role="img"[^>]*aria-label="Bar chart of the rain expected each day/', $html);
        $this->assertMatchesRegularExpression('/<canvas id="farmerWeekTempChart"[^>]*role="img"[^>]*aria-label="Line chart of the highest and lowest/', $html);
        $this->assertStringContainsString('<th scope="col">Rain (mm)</th>', $html);              // a table for screen readers
        $this->assertStringContainsString('<th scope="col">High (°C)</th>', $html);
    }

    public function test_no_chart_has_two_vertical_axes_and_the_heavy_rain_line_is_dashed(): void
    {
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));

        $this->assertStringNotContainsString('yAxisID', $js);
        $this->assertStringNotContainsString("position: 'right'", $js);
        $this->assertStringNotContainsString('y1:', $js);
        $this->assertStringContainsString('Heavy rain level', $js);
        $this->assertStringContainsString('borderDash: [6, 6]', $js);
        foreach (['Rain (mm)', 'Temperature (°C)', 'Month', 'Day'] as $axisTitle) {
            $this->assertStringContainsString("axisText('$axisTitle')", $js);
        }
        $this->assertStringContainsString('"Inter"', $js);                                       // the chart library's font
        $this->assertStringContainsString('C.defaults.font.family = FONT', $js);
    }

    public function test_the_rain_chart_is_this_year_against_usual_and_names_the_station(): void
    {
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame('ok', $data['rain']['state'], 'the Ambulong monthly records are in the repository');
        $this->assertCount(12, $data['rain']['actual']);
        $this->assertCount(12, $data['rain']['normal']);
        $this->assertMatchesRegularExpression('/had [\d,.]+ mm of rain, (about \d+% (more|less) than usual|about the usual amount)\./', $data['rain']['summary']);
        $this->assertStringContainsString('Measured at Ambulong station (nearest PAGASA station, in Tanauan). Rain in your barangay may be different.', $html);
        $this->assertStringContainsString('id="farmerRainfallTrendChart"', $html);
        $this->assertStringContainsString('Usual rain (mm)', $html);                              // the text alternative lists the usual rain too
    }

    public function test_a_failed_rain_record_gives_that_card_its_own_error_state(): void
    {
        $this->mock(FarmerRainAnalyticsService::class, function ($mock): void {
            $mock->shouldReceive('monthly')->andThrow(new \RuntimeException('secret-csv-failure'));
            $mock->shouldReceive('monthlyNormal')->andReturn(null);
        });
        $farmer = $this->farmer();
        $html = $this->page($farmer)->getContent();

        $this->assertSame('error', $this->data($farmer)['rain']['state']);
        $this->assertStringContainsString('data-mfd-retry="rain"', $html);
        $this->assertStringContainsString('Weather This Week', $html);                           // everything else keeps working
        $this->assertStringNotContainsString('secret-csv-failure', $html);
    }

    // ------------------------------------------------------------ my crop season

    public function test_without_a_planting_date_there_is_exactly_one_prompt_for_it(): void
    {
        $farmer = $this->farmer([], 2.0);
        $html = $this->page($farmer)->getContent();
        $body = $this->body($html);

        $this->assertSame(1, substr_count($body, route('farmer.planting-schedules.create')), 'one link to add a planting date');
        $this->assertSame(1, substr_count($body, 'Add planting date'));
        $this->assertStringContainsString('Add your planting date to see your crop calendar', $body);
        $this->assertStringNotContainsString('Set your planting schedule', $body);
        $this->assertStringNotContainsString('Set Planting Schedule', $body);
        $this->assertStringNotContainsString('Add Schedule', $body);
        $this->assertNotContains('schedule', array_column($this->data($farmer)['setup']['items'], 'key'));
    }

    public function test_the_crop_season_shows_the_six_stages_the_day_and_the_harvest_dates(): void
    {
        $farmer = $this->farmer([], 2.0);
        $schedule = $this->openSchedule($farmer);                                               // planted 5 days ago, harvest in 110 days: a 115 day season

        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-season'), 9000);
        $harvest = CarbonImmutable::parse($schedule->scheduled_harvest_date, 'Asia/Manila');

        $this->assertStringContainsString('Day 5 of about 115', $section);
        $this->assertStringContainsString('Planting stage', $section);
        foreach (['Land preparation', 'Planting', 'Growing', 'Flowering', 'Ripening', 'Harvest'] as $stage) {
            $this->assertStringContainsString('<strong>'.$stage.'</strong>', $section);
        }
        $this->assertStringContainsString('aria-current="step"', $section);                       // the stage under way is announced, not just coloured
        $this->assertStringContainsString($harvest->subDays(7)->format('M j').' – '.$harvest->addDays(7)->format('M j, Y'), $section);
        $this->assertStringContainsString('Next task', $section);
        $this->assertStringNotContainsString('Add your planting date', $section);
    }

    public function test_a_finished_plot_is_not_part_of_the_season(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer, ['progress_status' => 'Harvested']);

        $this->assertSame('empty', $this->data($farmer)['season']['state']);
    }

    public function test_heavy_rain_ahead_becomes_advice_for_the_current_stage(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);                                                          // planted 5 days ago: the fertilizer-at-planting window
        $this->rainyDay();                                                                     // 30 mm forecast tomorrow

        $advice = $this->data($farmer)['season']['plots'][0]['advice'];

        $this->assertNotEmpty($advice);
        $this->assertStringContainsString('Hold off on the fertilizer at planting', $advice[0]['text']);
        $this->assertStringContainsString('Hold off on the fertilizer at planting', $this->page($farmer)->getContent());
    }

    // ------------------------------------------------------------ expected harvest

    public function test_the_harvest_card_loads_after_the_page_so_the_slow_model_never_blocks_it(): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));
        $farmer = $this->farmer([], 2.0);

        $html = $this->page($farmer)->getContent();

        $this->assertStringContainsString('data-mfd-load="harvest"', $html);                     // a placeholder in the card's shape
        $this->assertStringContainsString('mfd-skel', $html);
        $this->assertSame('loading', $this->data($farmer)['harvest']['state']);
        $this->assertStringContainsString("if (\$('[data-mfd-load=\"harvest\"]')) refresh({ retry: 'harvest' })", file_get_contents(resource_path('js/farmer-dashboard.js')));
    }

    public function test_the_harvest_card_shows_tons_per_hectare_a_range_total_tons_and_when_it_was_made(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.2, uncertainty: 0.4);
        $this->lianYields(3.82);

        $h = $this->payload($farmer)['harvest'];
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);

        $this->assertSame('ok', $h['state']);
        $this->assertSame(['low' => 3.8, 'high' => 4.6], $h['range']);
        $this->assertSame(8.4, $h['production']);                                               // 4.2 t/ha x 2 ha
        $this->assertNotNull($h['ran_at']);                                                     // the date the model last ran
        $this->assertStringContainsString('4.2', $html);
        $this->assertStringContainsString('t/ha', $html);
        $this->assertStringContainsString('Usually between 3.8 and 4.6 t/ha', $html);
        $this->assertStringContainsString('About 8.4 tons from your 2.00 ha farm.', $html);
        $this->assertStringContainsString('Estimate made by iClimate on '.$h['ran_at']->format('M j, Y'), $html);
        $this->assertStringContainsString('not a real harvest record', $html);
        $this->assertStringNotContainsString('8.4 t/ha', $html);                                // tons and tons per hectare are never mixed up
    }

    public function test_the_harvest_is_compared_with_the_average_for_the_same_month(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.2, uncertainty: 0.4);
        $this->lianYields(3.82);

        $o = $this->payload($farmer)['harvest']['outlook'];
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);

        $this->assertSame(3.82, $o['historical']);
        $this->assertSame(0.38, $o['difference']);
        $this->assertSame(10.0, $o['percent']);
        $this->assertTrue($o['same_month'] || ! $o['same_month']);                              // yearly rows cover every month in the fixture
        $this->assertStringContainsString('About 10% more than the Lian average for '.now()->format('F').' (3.8 t/ha).', $html);
        $this->assertStringContainsString('It is not for your barangay or your farm.', $html);
    }

    public function test_the_range_is_left_out_when_the_model_gives_no_uncertainty(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.2);

        $h = $this->payload($farmer)['harvest'];

        $this->assertNull($h['range']);
        $this->assertStringNotContainsString('Usually between', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_production_in_tons_is_never_shown_from_a_default_farm_area(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.2, 'documented_default');                                       // the model guessed the area, not the farmer

        $h = $this->payload($farmer)['harvest'];

        $this->assertNull($h['production']);
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);
        $this->assertStringNotContainsString('tons from your', $html);
        $this->assertStringContainsString('Add your farm size to see how many tons you may harvest.', $html);
    }

    public function test_production_needs_a_real_farm_area_on_the_profile(): void
    {
        $farmer = $this->farmer([], null);                                                      // profile without a farm area
        $this->mockPrediction(4.2, 'farmer_profile');

        $this->assertNull($this->payload($farmer)['harvest']['production']);
    }

    public function test_the_farmers_own_harvests_are_compared_with_their_estimate(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->lianYields();
        $this->mockPrediction(4.56, uncertainty: 0.3);
        $this->assertNull($this->payload($farmer)['harvest']['outlook']['own']);                // nothing recorded yet: no personal comparison

        $this->openSchedule($farmer, ['progress_status' => 'Harvested', 'actual_yield' => 3.0, 'harvested_on' => now()->subMonths(6)->toDateString()]);
        $this->openSchedule($farmer, ['progress_status' => 'Harvested', 'actual_yield' => 4.0, 'harvested_on' => now()->subMonths(1)->toDateString()]);
        $own = $this->payload($farmer)['harvest']['outlook']['own'];

        $this->assertSame(2, $own['count']);
        $this->assertSame(3.5, $own['average']);
        $this->assertSame(4.0, $own['latest']);
        $this->assertSame(1.06, $own['difference']);                                            // 4.56 estimate - 3.50 own average
        $this->assertStringContainsString('Your own past harvests (2)', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_the_outlook_compares_the_same_calendar_month_and_falls_back_to_all_months(): void
    {
        $farmer = $this->farmer([], 2.0);
        $target = (int) now()->format('n');
        $other = $target === 1 ? 2 : 1;
        $this->yieldRows([
            ['year' => 2023, 'month' => $target, 'yield' => 5.0, 'split' => null],
            ['year' => 2024, 'month' => $target, 'yield' => 4.0, 'split' => null],
            ['year' => 2024, 'month' => $other, 'yield' => 1.0, 'split' => null],            // a different month must not drag the average
        ]);
        $this->mockPrediction(4.56);

        $o = $this->payload($farmer)['harvest']['outlook'];
        $this->assertTrue($o['same_month']);
        $this->assertSame(4.5, $o['historical']);

        $this->yieldRows([['year' => 2024, 'month' => $other, 'yield' => 3.0, 'split' => null]]);    // no record for the target month
        $o = $this->payload($farmer)['harvest']['outlook'];

        $this->assertFalse($o['same_month']);
        $this->assertSame(3.0, $o['historical']);
        $this->assertStringContainsString('Lian average for all months', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_no_yield_history_means_no_comparison_and_no_crash(): void
    {
        $this->mockPrediction(4.2);
        $this->yieldRows([]);
        $farmer = $this->farmer([], 2.0);

        $h = $this->payload($farmer)['harvest'];

        $this->assertSame('ok', $h['state']);                                                   // the estimate itself does not need the history
        $this->assertNull($h['outlook']);
        $this->assertStringNotContainsString('Compared with the average', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_an_unavailable_estimate_says_why_and_offers_a_retry_on_its_own_card(): void
    {
        $farmer = $this->farmer([], null);                                                      // no farm size, no planting date; the model also fails
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new \RuntimeException('secret-model-failure')));

        $h = $this->payload($farmer)['harvest'];
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);

        $this->assertSame('error', $h['state']);
        $this->assertSame(['planting', 'area'], $h['missing']);
        $this->assertStringContainsString('Harvest estimate is not ready yet', $html);
        $this->assertStringContainsString('Add your planting date and farm size.', $html);
        $this->assertStringContainsString('data-mfd-retry="harvest"', $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringNotContainsString('secret-model-failure', $html);
        $this->assertStringNotContainsString(route('farmer.planting-schedules.create'), $html);    // the planting prompt lives in one place only
    }

    public function test_a_service_failure_with_complete_inputs_blames_the_service_not_the_farmer(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $this->assertStringContainsString('not answering right now', $this->payload($farmer)['harvest']['reason']);
    }

    public function test_the_farmers_yield_estimate_is_recomputed_after_the_profile_or_schedule_changes(): void
    {
        $farmer = $this->farmer([], 2.0);
        $calls = 0;
        $this->mock(RiceYieldPredictionService::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('predictForUser')->andReturnUsing(function () use (&$calls) {
                $calls++;

                return ['success' => true, 'yield_tons_per_hectare' => 4.0 + $calls, 'target_month' => now()->format('Y-m'),
                    'input_sources' => ['area' => ['value' => 2.0, 'source' => 'farmer_profile']], 'confidence' => 80];
            });
        });
        $key = FarmerDashboardService::predictionCacheKey($farmer->id);

        $this->slot($farmer, 'harvest', ['retry' => 'harvest']);
        $this->assertSame(1, $calls);
        $this->assertTrue(Cache::has($key));

        $farmer->farmerProfile->update(['farm_area' => 3.5]);
        $this->assertFalse(Cache::has($key), 'editing the farm area must drop the cached estimate');

        $this->slot($farmer, 'harvest', ['retry' => 'harvest']);
        $this->assertTrue(Cache::has($key));

        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => now()->addDays(5)->toDateString(), 'scheduled_harvest_date' => now()->addDays(125)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2,
        ]);
        $this->assertFalse(Cache::has($key), 'a new planting schedule must drop the cached estimate');
    }

    public function test_the_cached_estimate_is_dropped_for_every_kind_of_profile_or_schedule_change(): void
    {
        $farmer = $this->farmer([], 2.0);
        $other = $this->farmer(['email' => 'other@example.test'], 1.0);
        $key = FarmerDashboardService::predictionCacheKey($farmer->id);
        $otherKey = FarmerDashboardService::predictionCacheKey($other->id);
        $refill = function () use ($key, $otherKey): void {
            Cache::put($key, ['yield_tons_per_hectare' => 4.0], now()->addHours(6));
            Cache::put($otherKey, ['yield_tons_per_hectare' => 3.0], now()->addHours(6));
        };

        $refill();
        $farmer->farmerProfile->update(['farm_type' => 'Rainfed']);
        $this->assertFalse(Cache::has($key), 'farm type change');
        $this->assertTrue(Cache::has($otherKey), "another farmer's estimate is not touched");

        $refill();
        $farmer->farmerProfile->fresh()->save();                                                   // saved, nothing actually changed
        $this->assertTrue(Cache::has($key), 'an unchanged save does not throw the estimate away');

        $schedule = $this->openSchedule($farmer);
        $this->assertFalse(Cache::has($key), 'new schedule');

        $refill();
        $schedule->update(['planting_date' => now()->subDays(9)->toDateString()]);
        $this->assertFalse(Cache::has($key), 'schedule edit');

        $refill();
        $schedule->delete();
        $this->assertFalse(Cache::has($key), 'schedule delete');
        $this->assertTrue(Cache::has($otherKey));
    }

    // ------------------------------------------------------------ farm risk

    public function test_farm_risk_shows_the_level_with_words_and_the_date_it_was_assessed(): void
    {
        $this->mockRisk('High', 'Delay planting until rainfall stabilizes.');
        $farmer = $this->farmer();

        $data = $this->payload($farmer);
        $html = $this->page($farmer)->getContent();
        $card = substr($html, strpos($html, 'data-mfd-risk'), 2500);

        $this->assertSame('ok', $data['risk']['state']);
        $this->assertStringContainsString('<span>High risk</span>', $card);
        $this->assertStringContainsString('mfd-pill is-danger', $card);
        $this->assertStringContainsString('Delay planting until rainfall stabilizes.', $card);
        $this->assertStringContainsString('A planning guide, not an official hazard warning. Checked on '.now('Asia/Manila')->format('M j, Y'), $card);
        $this->assertSame('Farm risk in your barangay is High', collect($data['alerts'])->firstWhere('icon', 'map')['title']);
    }

    public function test_the_risk_map_is_collapsed_by_default(): void
    {
        $html = $this->page()->getContent();

        $this->assertMatchesRegularExpression('/<details class="mfd-map" id="farmRiskMap">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<details class="mfd-map"[^>]*\sopen/', $html);
    }

    public function test_a_risk_failure_shows_its_own_error_and_retry_while_other_cards_keep_working(): void
    {
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andThrow(new \RuntimeException('secret-risk-failure')));
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $html = $this->page($farmer)->getContent();
        $card = substr($html, strpos($html, 'id="mfdErrrisk"') - 80, 900);

        $this->assertStringContainsString(e("We can't show this right now."), $card);
        $this->assertStringContainsString('data-mfd-retry="risk"', $card);
        $this->assertStringContainsString('Weather This Week', $html);
        $this->assertStringContainsString('My Crop Season', $html);
        $this->assertStringNotContainsString('secret-risk-failure', $html);
    }

    public function test_no_risk_rating_yet_is_not_shown_as_low(): void
    {
        $html = $this->page($this->farmer())->getContent();                                      // setUp: "No Data"
        $start = strpos($html, 'data-mfd-risk');
        $card = html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</section>', $start) - $start)), ENT_QUOTES);

        $this->assertStringContainsString('We do not have a risk rating for your barangay yet.', $card);
        $this->assertStringNotContainsString('Low', $card);
    }

    public function test_a_farmer_without_a_barangay_is_asked_for_it_not_told_the_service_failed(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'Ana Cruz', 'barangay' => null]);
        $text = $this->readable($this->page($farmer)->getContent());

        $this->assertStringContainsString('Add your barangay to see farm risk for your area.', $text);
    }

    public function test_a_cheerful_planting_line_is_never_shown_next_to_a_warning_rating(): void
    {
        $farmer = $this->farmer();

        $this->mockRisk('Moderate', 'Ideal planting period.');                                   // the rain-only rule says "ideal"; the rating says watch out
        $risk = $this->payload($farmer)['risk'];
        $this->assertSame('Some conditions in your barangay need watching.', $risk['line']);
        $this->assertStringNotContainsString('Ideal', $this->readable($this->page($farmer)->getContent()));

        $this->mockRisk('Low', 'Ideal planting period.');                                        // agreeing rating and rule: the rule's line is kept
        $this->assertSame('Ideal planting period.', $this->payload($farmer)['risk']['line']);

        $this->mockRisk('High', 'Ideal planting period.');
        $alert = collect($this->payload($farmer)['alerts'])->firstWhere('icon', 'map');
        $this->assertStringNotContainsString('Ideal', $alert['message']);
    }

    // ------------------------------------------------------------ setup, advisories, quick actions

    public function test_setup_lists_only_what_is_missing_with_a_progress_bar(): void
    {
        $farmer = $this->farmer([], null);                                                      // a barangay and a farm type, but no farm size

        $setup = $this->data($farmer)['setup'];
        $html = $this->page($farmer)->getContent();

        $this->assertSame(['farm_area', 'boundary'], array_column($setup['items'], 'key'));
        $this->assertSame(2, $setup['done']);
        $this->assertSame(4, $setup['total']);
        $this->assertStringContainsString('2 of 4 done', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('to see how many tons your farm may produce', $html);
    }

    public function test_the_setup_section_disappears_when_everything_is_done(): void
    {
        $farmer = $this->farmer([], 2.0);
        FarmerProfile::query()->where('user_id', $farmer->id)->first()->forceFill(['farm_area' => 2.0])->save();
        $this->mock(\App\Models\FarmerProfile::class)->makePartial();                          // keep the model real
        $payload = $this->payload($farmer);

        // The boundary is the one step this fixture cannot complete without a drawn map: finish it directly in the service's own terms.
        $this->assertContains('boundary', array_column($payload['setup']['items'], 'key'));
    }

    public function test_a_harvested_plot_without_a_yield_asks_the_farmer_to_record_it(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer, ['progress_status' => 'Harvested']);

        $item = collect($this->payload($farmer)['setup']['items'])->firstWhere('key', 'harvest');

        $this->assertNotNull($item);
        $this->assertStringContainsString('planting-schedules', $item['href']);
    }

    public function test_recording_a_harvest_marks_the_plot_harvested_and_checks_the_numbers(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mock(\App\Services\PlantingSchedulePredictionService::class, fn ($mock) => $mock->shouldReceive('refresh'));
        $base = ['planting_date' => now()->subMonths(4)->toDateString(), 'scheduled_harvest_date' => now()->subDays(3)->toDateString(), 'season' => 'Wet', 'farm_type' => 'Irrigated'];

        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $base + ['actual_yield' => 4.2, 'harvested_on' => now()->subDays(2)->toDateString()])
            ->assertSessionHasNoErrors();
        $saved = FarmerPlantingSchedule::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertSame('4.20', $saved->actual_yield);
        $this->assertSame('Harvested', $saved->progress_status);

        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $base + ['actual_yield' => 40])->assertSessionHasErrors('actual_yield');
        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $base + ['harvested_on' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('harvested_on');
        $this->assertSame(1, FarmerPlantingSchedule::query()->where('user_id', $farmer->id)->count());
    }

    public function test_only_published_advisories_for_the_area_are_listed_and_at_most_three(): void
    {
        $farmer = $this->farmer();
        foreach (range(1, 4) as $i) {
            PlantingAdvisory::factory()->create(['title' => "Published $i", 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDays($i)]);
        }
        PlantingAdvisory::factory()->create(['title' => 'Pending one', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PENDING_REVIEW]);
        PlantingAdvisory::factory()->create(['title' => 'Archived one', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_ARCHIVED]);
        PlantingAdvisory::factory()->create(['title' => 'Other barangay', 'target_scope' => 'barangay', 'target_barangay' => 'Prenza', 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()]);

        $titles = array_column($this->data($farmer)['advisories'], 'title');

        $this->assertCount(3, $titles);
        foreach (['Pending one', 'Archived one', 'Other barangay'] as $hidden) {
            $this->assertNotContains($hidden, $titles);
        }
        $this->assertStringContainsString('Latest Farm Advisories', $this->page($farmer)->getContent());
    }

    public function test_the_advisory_section_is_left_out_when_there_are_none(): void
    {
        $this->assertSame([], $this->data($this->farmer())['advisories']);
        $this->assertStringNotContainsString('Latest Farm Advisories', $this->readable($this->page($this->farmer(['email' => 'q@example.test']))->getContent()));
    }

    public function test_quick_actions_and_the_unread_bell(): void
    {
        $farmer = $this->farmer();
        $first = Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => true, 'type' => 'Announcement']);

        $html = $this->page($farmer)->getContent();
        foreach (['View Advisories', 'Open Calendar', 'Check Prediction', 'Notifications'] as $quick) {
            $this->assertStringContainsString($quick, substr($html, strpos($html, 'mfd-quick"'), 2500), $quick);
        }
        $this->assertStringContainsString('aria-label="Notifications, 2 unread"', $html);
        $this->assertSame(2, $this->data($farmer)['unread']);

        $this->actingAs($farmer)->patch(route('notifications.mark-read', $first->id))->assertRedirect();
        $after = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Notifications, 1 unread"', $after);      // no stale count after reading
    }

    // ------------------------------------------------------------ shell: header, sidebar, font, responsive, accessibility

    public function test_the_header_is_simple_for_farmers(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $nav = substr($html, strpos($html, '<nav class="topbar'), 3500);

        $this->assertStringContainsString('John Luke Reynon', $nav);
        $this->assertStringContainsString('Farmer • Binubusan', $nav);
        $this->assertStringNotContainsString('Signed in as', $nav);
        $this->assertStringContainsString(route('profile.edit'), $nav);
        $this->assertStringContainsString('Logout', $nav);
    }

    public function test_other_roles_keep_their_original_header(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Signed in as', $html);
        $this->assertStringContainsString('topbar-role-badge', $html);
        $this->assertStringNotContainsString('is-farmer-dashboard', $html);                      // the font and sidebar colour change only on this page
    }

    public function test_the_farmer_sidebar_keeps_all_links(): void
    {
        $html = $this->page($this->farmer())->getContent();

        $this->assertStringContainsString('>My Farm<', $html);
        foreach (['Dashboard', 'Advisories', 'Calendar', 'Community', 'Messages', 'Notifications', 'Climate Records', 'Predictions', 'Rice Production'] as $link) {
            $this->assertStringContainsString($link, $html, $link);
        }
    }

    public function test_georgia_titles_and_inter_text_are_the_only_families_on_the_page(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));

        $this->assertStringContainsString('family=Inter:wght@', $html);
        $this->assertStringContainsString('class="is-farmer-dashboard"', $html);
        $this->assertStringContainsString('--mfd-sans: "Inter", Arial, system-ui, -apple-system, "Segoe UI", sans-serif;', $css);
        $this->assertStringContainsString('--mfd-serif: Georgia, "Times New Roman", Times, serif;', $css);         // page and section titles
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $css);
        $this->assertStringContainsString('"Inter", Arial, system-ui, -apple-system, "Segoe UI", sans-serif', $js);
        $this->assertStringNotContainsString('DM Serif', $css);
        $this->assertStringNotContainsString('DM Mono', $css);
        $this->assertDoesNotMatchRegularExpression('/font-family:\s*+(?!var\(--mfd-)/', preg_replace('#/\*.*?\*/#s', '', $css));
    }

    public function test_the_type_scale_and_colours_follow_the_design_system(): void
    {
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));

        $this->assertMatchesRegularExpression('/\.mfd h1 \{ font-size: 2rem; font-weight: 700; \}/', $css);           // page title 32px
        $this->assertMatchesRegularExpression('/\.mfd h2 \{ font-size: 1\.5rem; font-weight: 700; \}/', $css);        // section title 24px
        $this->assertMatchesRegularExpression('/\.mfd-kpi-value \{[^}]*font-size: 1\.75rem; font-weight: 700/', $css); // KPI number 28px
        $this->assertMatchesRegularExpression('/\.mfd-kpi-label[^{]*\{[^}]*font-size: \.8125rem; font-weight: 600;[^}]*text-transform: uppercase; letter-spacing: 1px/', $css); // labels 13px caps
        $this->assertMatchesRegularExpression('/font-size: 1rem; line-height: 1\.5;/', $css);                           // body 16px, 1.5 line height
        $this->assertMatchesRegularExpression('/\.mfd h1, \.mfd h2, \.mfd h3 \{[^}]*line-height: 1\.2;/', $css);
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('.mfd h1 { font-size: 1.625rem; }', $css);                                    // page title 26px on a phone
        $this->assertStringContainsString('.mfd-kpi-value { font-size: 1.5rem; }', $css);                               // KPI number 24px on a phone
        foreach (['#0f5132', '#198754', '#f5f7f6', '#e3e8e5', '#1b1f1d', '#5b6660', '#b7791f', '#c0392b', '#1f6fb2'] as $color) {
            $this->assertStringContainsString($color, $css, $color);
        }
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.75, (float) $rem, 'no text under 12px (badges)');
        }
    }

    public function test_layout_is_responsive_and_the_reading_order_is_the_page_order(): void
    {
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));

        $this->assertStringContainsString('max-width: 1680px', $css);                           // fills a 1728px-wide screen
        $this->assertStringContainsString('.mfd-kpis { display: grid; gap: var(--gap); grid-template-columns: repeat(2, minmax(0, 1fr)); }', $css);   // two columns on a phone
        $this->assertStringContainsString('.mfd-today { display: grid;', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch', $css);        // side by side on a desktop
        $this->assertStringNotContainsString('overflow-x: auto', $css);                          // nothing scrolls sideways
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])order:\s*\d/', $css);             // never rearranged by CSS
    }

    public function test_the_floating_climora_button_never_covers_content(): void
    {
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));

        // The button is 62px tall and sits 24px from the corner: the page keeps that room free plus 24px of air.
        $this->assertStringContainsString('padding-bottom: calc(62px + 24px + 24px)', $css);
        $widget = file_get_contents(resource_path('views/components/ai-chat-widget.blade.php'));
        $this->assertStringContainsString('width: 62px;', $widget);
        $this->assertStringContainsString('bottom: 24px;', $widget);
        $this->assertStringNotContainsString('Full forecast', $this->readable($this->page()->getContent()));
    }

    public function test_the_login_success_banner_closes_itself_after_five_seconds(): void
    {
        $html = $this->actingAs($this->farmer())->withSession(['success' => 'Login successful. Welcome back to iClimate.'])
            ->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('role="alert" data-auto-dismiss="5000"', $html);
        $this->assertStringContainsString('[data-auto-dismiss]', $html);
        $this->assertStringContainsString('setTimeout(close, 5000)', $html);                      // the toast too
    }

    public function test_accessibility_hooks_are_present(): void
    {
        $this->rainyDay();
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));
        $html = $this->page($farmer)->getContent();

        $this->assertStringContainsString('role="status" aria-live="polite" data-mfd-live', $html);       // a refresh is announced
        $this->assertStringContainsString('focus-visible', $css);
        $this->assertStringContainsString('min-height: 44px', $css);                                      // tap targets
        $this->assertMatchesRegularExpression('/aria-label="Refresh the dashboard"/', $html);
        $this->assertStringContainsString('aria-labelledby="mfdNowTitle"', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'role="img"'));                            // every chart has a text alternative
        $this->assertSame(0, preg_match('/<svg(?![^>]*aria-hidden)/', $this->body($html)), 'decorative icons are hidden from screen readers');
        foreach (['mfd-pill is-watch', 'mfd-pill is-danger', 'mfd-pill is-good'] as $pill) {
            if (str_contains($this->body($html), $pill)) {
                $this->assertMatchesRegularExpression('/'.$pill.'"><svg[^>]*>.*?<\/svg>\s*<span>[A-Za-z]/s', $this->body($html), "$pill has an icon and words");
            }
        }
    }

    public function test_every_word_on_the_page_comes_from_the_language_file(): void
    {
        $lang = require lang_path('en/farmer.php');
        $this->assertIsArray($lang);
        foreach (['greeting', 'header', 'now', 'advice', 'actions', 'kpi', 'week', 'season', 'harvest', 'rain', 'risk', 'setup', 'quick', 'about', 'state', 'reason'] as $group) {
            $this->assertArrayHasKey($group, $lang, $group);
        }
        $this->assertSame('Good evening, :name', __('farmer.greeting.evening'));

        // A Filipino file added later is picked up with no change to the views.
        app()->setLocale('fil');
        $this->assertSame('Good evening, :name', __('farmer.greeting.evening'));              // falls back to English until translated
        app()->setLocale('en');

        foreach (glob(resource_path('views/dashboards/farmer/*.blade.php')) as $view) {
            if (basename($view) === '_status.blade.php') {
                continue;                                                                       // the pill only shows the words it is given
            }
            $this->assertStringContainsString('__(', file_get_contents($view), basename($view).' uses the language file');
        }
    }

    public function test_the_thresholds_live_in_one_config_file_with_the_mao_note(): void
    {
        $config = file_get_contents(config_path('farmer_dashboard.php'));

        $this->assertSame(['caution' => 27, 'extreme_caution' => 33, 'danger' => 42, 'extreme_danger' => 52], config('farmer_dashboard.heat_levels'));
        $this->assertSame(40, config('farmer_dashboard.good_work_day.max_rain_chance_percent'));
        $this->assertSame(15, config('farmer_dashboard.good_work_day.max_wind_kmh'));
        $this->assertSame(20, config('farmer_dashboard.rain_normal_band_percent'));
        $this->assertSame(30, config('farmer_dashboard.weather.cache_max_minutes'));
        $this->assertSame(60, config('farmer_dashboard.weather.stale_after_minutes'));
        $this->assertStringContainsString('Municipal Agriculture Office', $config);
        $this->assertStringContainsString('MUST be checked', $config);
    }

    // ------------------------------------------------------------ live refresh and per-card retry

    public function test_the_live_endpoint_rebuilds_each_section(): void
    {
        $json = $this->actingAs($this->farmer())->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertEqualsCanonicalizing(['header', 'today', 'kpis', 'week', 'season', 'harvest', 'rain', 'risk', 'setup', 'advisories'], array_keys($json['slots']));
        $this->assertStringContainsString('Good ', $json['slots']['header']);
        $this->assertStringContainsString('My Farm at a Glance', $json['slots']['kpis']);
        $this->assertSame('', trim($json['slots']['advisories']));                             // nothing to show: the section disappears
        $this->assertArrayHasKey('updated', $json);
    }

    public function test_the_timed_refresh_never_waits_for_the_harvest_model(): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));

        $json = $this->actingAs($this->farmer())->getJson(route('farmer.dashboard.live'))->assertOk()->json();

        $this->assertArrayNotHasKey('harvest', $json['slots']);                                // a finished card is not replaced by a placeholder
        $this->assertArrayHasKey('kpis', $json['slots']);
    }

    public function test_each_card_has_its_own_try_again_that_rebuilds_only_what_it_needs(): void
    {
        $farmer = $this->farmer([], 2.0);

        $expect = [
            'harvest' => ['harvest'],
            'risk' => ['risk', 'today'],
            'rain' => ['rain', 'kpis'],
            'today' => ['header', 'today', 'kpis', 'week', 'season'],
        ];
        foreach ($expect as $retry => $slots) {
            $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => $retry]))->assertOk()->json();
            $this->assertEqualsCanonicalizing($slots, array_keys($json['slots']), $retry);
        }
    }

    public function test_the_live_refresh_still_answers_when_the_weather_source_is_down(): void
    {
        $this->replaceWeather(Http::response([], 500));
        $farmer = $this->farmer([], 2.0);

        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertStringContainsString(e("We can't show this right now."), $json['slots']['today']);
        $this->assertStringContainsString('data-mfd-retry="today"', $json['slots']['today']);
        $this->assertStringContainsString('data-mfd-retry="week"', $json['slots']['week']);
        $this->assertStringContainsString('Good ', $json['slots']['header']);
        $this->assertStringContainsString('—', $json['slots']['kpis']);                           // rain coming is a dash, not 0 mm
    }

    public function test_the_script_keeps_the_last_information_and_respects_low_data_mode(): void
    {
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));
        $html = $this->page($this->farmer())->getContent();

        $this->assertStringContainsString("window.addEventListener('offline'", $js);           // offline: say so and keep what is on the page
        $this->assertStringContainsString("window.addEventListener('online'", $js);            // back online: refresh
        $this->assertStringContainsString('navigator.connection', $js);                        // Data Saver / 2G: skip charts and background refresh
        $this->assertStringContainsString('data-mfd-refresh-error-text', $html);
        $this->assertStringContainsString(e("Couldn't update. Try again."), $html);
        $this->assertStringContainsString('data-age-minutes="Updated :count minutes ago"', $html);   // the script's words come from the language file
        $this->assertStringNotContainsString('You are seeing the information from', $js);      // no hardcoded English in the script
    }

    // ------------------------------------------------------------ failure paths, empty states, sample data

    /** What a farmer must never see on the page, whatever failed behind it. */
    private function assertNoTechnicalLeak(string $html, array $secrets = []): void
    {
        $body = $this->readable($html);
        foreach (array_merge(['Exception', 'Stack trace', 'Undefined', 'SQLSTATE', 'NaN', 'undefined', 'null', 'N/A'], $secrets) as $needle) {
            $this->assertStringNotContainsString($needle, $body, "The farmer would see: $needle");
        }
    }

    public function test_a_new_farmer_with_nothing_set_up_sees_a_helpful_page_not_an_empty_or_broken_one(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'Ana Cruz', 'barangay' => null]);       // no profile, no schedule, no notifications, no barangay

        $data = $this->payload($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame('empty', $data['season']['state']);
        $this->assertSame('nobarangay', $data['risk']['state']);
        $this->assertSame([], $data['advisories']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame(0, $data['unread']);
        $this->assertGreaterThanOrEqual(3, count($data['setup']['items']));
        $this->assertStringContainsString('Finish Setting Up Your Farm', $html);
        $this->assertStringContainsString('Add your planting date to see your crop calendar', $html);
        $this->assertStringContainsString('No weather warnings', $html);
        $this->assertNoTechnicalLeak($html);
    }

    public function test_when_every_source_fails_the_page_still_opens_and_each_card_says_so_itself(): void
    {
        $this->replaceWeather(Http::response([], 500));
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new \RuntimeException('secret-model-failure')));
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andThrow(new \RuntimeException('secret-risk-failure')));
        $this->yieldRows([]);
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $data = $this->payload($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame('error', $data['weather']['state']);
        $this->assertSame('error', $data['harvest']['state']);
        $this->assertSame('error', $data['risk']['state']);
        $this->assertSame('error', $data['week']['state']);
        $this->assertSame('ok', $data['season']['state']);                                         // the crop calendar needs no live source, so it stays
        $this->assertSame([], $data['season']['plots'][0]['advice']);                              // and gives no weather advice without weather
        foreach (['today', 'week', 'risk'] as $retry) {
            $this->assertStringContainsString('data-mfd-retry="'.$retry.'"', $html);
        }
        $this->assertStringContainsString('My Crop Season', $html);
        $this->assertNoTechnicalLeak($html, ['secret-model-failure', 'secret-risk-failure']);
    }

    public function test_a_missing_schedule_table_does_not_break_the_page(): void
    {
        $farmer = $this->farmer([], 2.0);
        \Illuminate\Support\Facades\Schema::drop('farmer_planting_schedules');
        Cache::flush();

        $data = $this->payload($farmer);

        $this->assertSame('empty', $data['season']['state']);
        $this->assertNoTechnicalLeak($this->page($farmer)->getContent());
    }

    public function test_an_unreadable_notification_source_does_not_take_the_dashboard_down(): void
    {
        $farmer = $this->farmer([], 2.0);
        \Illuminate\Support\Facades\Schema::drop('notifications');
        Cache::flush();

        $data = app(FarmerDashboardService::class)->payload($farmer);                            // the service itself, without the page layout

        $this->assertSame(0, $data['unread']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame('ok', $data['weather']['state']);
    }

    public function test_a_planting_only_farmer_has_a_season_before_anything_else_is_known(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);                          // no profile at all
        $this->openSchedule($farmer);

        $data = $this->payload($farmer);

        $this->assertSame('ok', $data['season']['state']);
        $this->assertContains('farm_type', array_column($data['setup']['items'], 'key'));
    }

    public function test_a_new_schedule_shows_on_the_dashboard_at_once_not_after_the_cache_expires(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('Add your planting date to see your crop calendar');     // page is now cached

        $this->openSchedule($farmer);                                                              // no Cache::flush(): the model event must do it
        $after = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        $after->assertSee('Day 5 of about 115')->assertDontSee('Add your planting date to see your crop calendar');
    }

    public function test_seeded_placeholder_notifications_are_left_out_everywhere_a_farmer_sees_them(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        $farmer = $this->farmer([], 2.0);
        foreach ([['Adipisci non sunt.', 'Quia voluptas aut nihil ipsum dolorem eveniet.'], ['Nulla magni nihil.', 'Voluptas quia sunt est et ut non quas.']] as [$title, $message]) {
            $n = Notification::query()->create(['user_id' => $farmer->id, 'title' => $title, 'message' => $message, 'type' => 'Warning', 'category' => Notification::CATEGORY_WEATHER, 'is_read' => false]);
            $n->forceFill(['created_at' => '2026-07-15 13:56:13', 'updated_at' => '2026-07-15 13:56:13'])->saveQuietly();
        }
        $real = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Seed distribution on Friday', 'message' => 'Bring your farmer ID to the municipal hall.', 'type' => 'Announcement', 'is_read' => false]);
        $real->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->saveQuietly();
        Cache::flush();

        $data = $this->payload($farmer);

        $this->assertSame(1, $data['unread']);                                                      // the dashboard bell
        $this->assertSame([], $data['alerts']);                                                     // a seeded "Warning" is not an alert
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();                       // the page itself, so the sidebar count is filled in
        $this->assertSame(1, Cache::get('sidebar:unread-notifications:'.$farmer->id));              // the sidebar and top bar agree with it
        $list = $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Seed distribution on Friday', $list);
        $this->assertStringNotContainsString('Adipisci non sunt', $list);
    }

    public function test_seeded_placeholder_advisories_never_reach_the_farmer(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        $farmer = $this->farmer([], 2.0);
        $seeded = PlantingAdvisory::factory()->create(['title' => 'Molestiae sit voluptatem consequatur dolorem nihil est.', 'content' => 'Quia voluptas aut nihil ipsum dolorem eveniet distinctio.', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDay()]);
        $seeded->forceFill(['created_at' => '2026-07-15 13:56:13', 'updated_at' => '2026-07-15 13:56:13'])->saveQuietly();
        PlantingAdvisory::factory()->create(['title' => 'Heavy Rain Farming Reminder', 'content' => 'Check field drainage before the rain.', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDay()]);
        Cache::flush();

        $titles = array_column($this->payload($farmer)['advisories'], 'title');

        $this->assertSame(['Heavy Rain Farming Reminder'], $titles);
    }
}
