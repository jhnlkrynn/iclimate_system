<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Analytics\FarmerKpiService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
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
    /** The dashboard body only (no sidebar, header or chat widget scripts), as text a farmer can read. */
    private function body(string $html): string
    {
        $start = strpos($html, '<div class="mfd"');
        $end = strpos($html, 'data-fad-modal', $start);

        return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
    }

    private function readable(string $html): string
    {
        // Dropdown choices are left out: they are place names (the barangay "Kapito" is not the word "API"), not wording.
        return html_entity_decode(strip_tags(preg_replace(['#<script\b.*?</script>#is', '#<style\b.*?</style>#is', '#<select\b.*?</select>#is'], '', $this->body($html))), ENT_QUOTES);
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
        $html = $this->page($farmer)->getContent();
        $text = $this->readable($html).' '.$this->readable('<div class="mfd">'.$this->slot($farmer, 'harvest', ['retry' => 'harvest']).'</div>');

        foreach (['Precipitation', 'Relative humidity', 'Air moisture', 'Heat index', 'Anomaly', 'Deviation', 'Yield prediction', 'Meteorological',
            'Phenological', 'Data unavailable', 'Synchronization', 'Regression', 'RMSE', 'Confidence', 'Probability', 'How sure we are', 'Agronomic', 'Hydrological'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, $word);
        }
        // Calm wording: nothing on a normal day reads as discouraging.
        foreach (['Poor', 'Bad condition', 'Failure', 'performing badly', 'serious risk'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, $word);
        }
        $this->assertStringContainsString('Humidity', $text);
        $this->assertStringContainsString('Rice Yield Forecast', $text);
    }

    public function test_page_follows_the_new_layout(): void
    {
        $this->rainyDay();
        $this->mockRisk('Low');
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $html = $this->page($farmer)->getContent();

        // Header, the numbers, today's highlights, then the summaries of the other Farmer pages, then quick actions.
        $order = ['Good ', 'id="mfdTodayTitle"', 'id="mfdHighlightsTitle"', 'id="mfdWeatherTitle"', 'id="mfdScheduleTitle"', 'id="mfdRiskTitle"',
            'id="mfdHarvestTitle"', 'id="mfdAdvisoriesTitle"', 'id="mfdFarmTitle"', 'id="mfdUpdatesTitle"', 'id="mfdQuickTitle"'];
        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker, max(0, strpos($html, 'data-farmer-dashboard')));
            $this->assertNotFalse($pos, "Missing: $marker");
            $this->assertGreaterThan($last, $pos, "Out of order: $marker");
            $last = $pos;
        }
        $this->assertSame(['header', 'today', 'highlights', 'weather', 'schedule', 'risk', 'harvest', 'advisories', 'farm', 'updates', 'quick'], $this->slotOrder($html));
        foreach (["Today's Highlights", 'My Farm Today', 'Weather for Lian, Batangas', 'My Farm Schedule', 'Farm Condition in Your Barangay', 'Latest Farming Advice', 'Reminders & Updates', 'Quick Actions'] as $title) {
            $this->assertStringContainsString($title, $this->readable($html), $title);
        }
    }

    private function slotOrder(string $html): array
    {
        preg_match_all('/data-slot="([a-z]+)"/', $html, $m);

        return $m[1];
    }

    public function test_the_old_sections_are_gone(): void
    {
        $this->rainyDay();
        $html = $this->page()->getContent();
        $body = $this->body($html);
        $text = $this->readable($html);

        foreach (['My Farm at a Glance', 'What You Should Know Today', 'Weather This Week', 'Rain and Spraying This Week', 'Rain Each Month',
            'Finish Setting Up Your Farm', 'About this information', 'Show risk map and barangay details', 'More rain and risk details', 'Important Alerts'] as $removed) {
            $this->assertStringNotContainsString($removed, $text, $removed);
        }
        // No full map, chart or seven-day table on the overview: their own pages have them.
        $this->assertStringNotContainsString('<canvas', $body);
        $this->assertStringNotContainsString('data-risk-map-url', $body);
        $this->assertStringNotContainsString('<table', $body);
        $this->assertSame(4, preg_match_all('/class="mfd-day( is-today)?"/', $body));                              // a short preview, not the full week
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
        $rain = $this->kpiCard($html, 'rain');
        $weather = substr($html, strpos($html, 'data-mfd-weather'), 9000);

        $this->assertSame(5.9, $data['weather']['rain_today']);
        $this->assertSame(5.9, $data['week']['preview'][0]['rain']);                            // the same number on today's forecast card
        $this->assertTrue($data['week']['preview'][0]['is_today']);
        $this->assertSame('Clear right now. Rain expected later today (about 5.9 mm).', $data['weather']['now_summary']);
        $this->assertStringContainsString('Clear right now. Rain expected later today (about 5.9 mm).', $weather);
        $this->assertMatchesRegularExpression('#<span>5\.9</span><small>mm</small>#', $rain);   // never 5.9 in one place and 6 in another
        $this->assertStringContainsString('100% chance of rain', $rain);
        $this->assertStringContainsString('Rain is expected later today.', $rain);
        $this->assertStringContainsString('5.9 mm', $weather);
    }

    /** One "My Farm Today" card's markup. */
    private function kpiCard(string $html, string $key): string
    {
        $from = strpos($html, 'data-kpi="'.$key.'"');
        $this->assertNotFalse($from, $key.' card');

        return substr($html, $from, strpos($html, '</article>', $from) - $from);
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
        $this->assertStringContainsString('Weather information is temporarily unavailable.', $text);
        $this->assertStringContainsString('data-mfd-retry="weather"', $html);
        $this->assertStringContainsString("Today's Highlights", $text);                          // the other sections keep working
        $this->assertStringContainsString('Check the advisories before field work', $text);
        $this->assertStringContainsString('Quick Actions', $text);
        $this->assertStringNotContainsString('0.0 mm', $text);
        foreach (['API', 'failed', 'Exception'] as $technical) {
            $this->assertStringNotContainsString($technical, $text, $technical);
        }
    }

    // ------------------------------------------------------------ what to do today + warnings

    public function test_actions_come_from_defined_rules_each_with_a_reason_and_a_climora_link(): void
    {
        $this->fakeWeather([6.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], humidity: 93, options: ['code' => 0, 'currentRain' => 0.0, 'feelsMax' => 28.0]);
        $farmer = $this->farmer();

        $actions = $this->data($farmer)['actions'];
        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-highlights'), 6000);

        $this->assertGreaterThanOrEqual(1, count($actions));
        $this->assertLessThanOrEqual(3, count($actions));
        $this->assertSame(['Wait before you put on fertilizer or spray', 'Look at your rice leaves for spots'], array_column($actions, 'title'));
        $this->assertStringContainsString('Humidity is 93%', $actions[1]['reason']);
        $this->assertStringContainsString('Rain is expected today (about 6.0 mm)', $actions[0]['reason']);
        // Today's Highlights carries the first action with its reason, and Climora can be asked about it.
        $this->assertStringContainsString('Wait before you put on fertilizer or spray', $section);
        $this->assertStringContainsString('Rain can wash fertilizer and spray away.', $section);
        $this->assertSame(1, substr_count($section, 'data-ai-prefill='));
        $this->assertStringContainsString('window.iClimateAi.prefill(ask.dataset.aiPrefill)', file_get_contents(resource_path('js/farmer-dashboard.js')));
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
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], humidity: 60, options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5, 'feelsMax' => 28.0]);
        $this->mockRisk('Low');
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $card = $this->kpiCard($html, 'condition');

        $this->assertSame([], $data['alerts']);
        $this->assertSame('good', $data['condition']['status']);
        $this->assertStringContainsString('mfd-kpi is-good', substr($html, strpos($html, 'data-kpi="condition"') - 60, 80));
        $this->assertMatchesRegularExpression('/<svg[^>]*>.*?<\/svg>\s*<span>Good<\/span>/s', $card);       // an icon and the word, not colour alone
        $this->assertStringContainsString('Good day to check your farm', $html);
    }

    public function test_a_real_warning_shows_in_colour_with_an_icon_and_a_label(): void
    {
        $this->rainyDay();                                                                      // 30 mm tomorrow
        $farmer = $this->farmer();

        $data = $this->data($farmer);
        $alert = $data['alerts'][0];
        $html = $this->page($farmer)->getContent();
        $highlights = substr($html, strpos($html, 'data-mfd-highlights'), 6000);

        $this->assertSame('Heavy rain expected tomorrow', $alert['title']);
        $this->assertSame('danger', $alert['level']);
        $this->assertStringContainsString('mfd-hl is-danger', $highlights);
        $this->assertStringContainsString('Warning', $highlights);                                 // the word, not just the colour
        $this->assertStringContainsString('About 30 mm of rain is coming.', $highlights);           // what is happening
        $this->assertStringContainsString('Check field drainage and do not spray.', $highlights);   // and what to do
        $this->assertSame('Needs Attention', $data['condition']['value']);
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

    public function test_good_days_are_marked_on_the_forecast_with_the_spraying_rule(): void
    {
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 10, 'wind' => 8.0]);
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame(7, $data['week']['good']['value']);
        $this->assertCount(4, $data['week']['preview']);
        $this->assertSame(4, substr_count($html, '<span>Good for spraying</span>'));                         // each day shown on the preview

        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['chance' => 60, 'wind' => 8.0]);
        $this->assertSame(0, $this->data($this->farmer(['email' => 'b@example.test']))['week']['good']['value']);
    }

    // ------------------------------------------------------------ weather this week + charts

    public function test_the_forecast_preview_shows_four_days_and_flags_only_real_risks(): void
    {
        $this->rainyDay();

        $html = $this->page($this->farmer())->getContent();
        $section = substr($html, strpos($html, 'data-mfd-weather'), 16000);

        $this->assertSame(4, preg_match_all('/class="mfd-day( is-today)?"/', $section));
        $this->assertSame(1, substr_count($section, '<span>Heavy rain</span>'));
        $this->assertStringContainsString('% rain', $section);
        $this->assertStringContainsString('30 mm', $section);
        $this->assertStringContainsString('The wettest day is', $section);
        $this->assertStringContainsString('View Full Forecast', $section);
        $this->assertStringContainsString('href="'.route('weather-predictions.index', ['tab' => 'weather-forecast']).'"', $section);
        $this->assertStringContainsString('Source: Open-Meteo', $section);                        // the configured provider, by name
        $this->assertStringContainsString('Weather for the whole of Lian, not measured on your farm.', $section);
        $this->assertStringNotContainsStringIgnoringCase('drizzle', $html);
    }

    // ------------------------------------------------------------ my crop season

    public function test_without_a_planting_date_the_status_and_schedule_give_a_planting_guide_and_nothing_is_made_up(): void
    {
        $farmer = $this->farmer([], 2.0);
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $status = $this->kpiCard($html, 'planting');
        $schedule = substr($html, strpos($html, 'data-mfd-schedule'), strpos($html, '</section>', strpos($html, 'data-mfd-schedule')) - strpos($html, 'data-mfd-schedule'));

        $this->assertSame('empty', $data['schedule']['state']);
        $this->assertNull($data['profile']['planting_date']);
        $this->assertStringNotContainsString('Not Set', $status);
        $this->assertStringContainsString('Add your planting date to receive better guidance.', $status);
        $this->assertStringContainsString('Add Planting Date', $status);
        $this->assertStringContainsString('data-mfd-popup="planting"', $status);
        $this->assertStringContainsString('Planting guide for this season', $schedule);
        $this->assertStringContainsString('Usual rice calendar, counted from planting day', $schedule);
        $this->assertStringContainsString('Add your planting date to get a reminder before each activity.', $schedule);
        $this->assertStringNotContainsString('Current stage', $schedule);                       // no stage or day count without a planting date
        $this->assertStringNotContainsString('after planting', $status);
        $this->assertNotContains('schedule', array_column($data['setup']['items'], 'key'));
    }

    public function test_the_planting_guide_says_the_heat_maps_own_planting_advice_and_never_cheers_next_to_a_warning(): void
    {
        $farmer = $this->farmer([], 2.0);

        $this->mockRisk('Low', 'Ideal planting period.');
        $guide = $this->payload($farmer)['planting_guide'];
        $this->assertSame(['good', 'good'], [$guide['key'], $guide['tone']]);
        $this->assertStringContainsString('Good month to plant', $this->kpiCard($this->page($farmer)->getContent(), 'planting'));
        // The usual rice calendar is the Calendar's own rule set, counted from planting day.
        $this->assertSame(['land_prep', 'basal', 'topdress1', 'topdress2', 'harvest'], array_column($guide['steps'], 'key'));

        $this->mockRisk('Moderate', 'Ideal planting period.');                                  // the rain-only rule says "ideal", the rating says watch
        $guide = $this->payload($farmer)['planting_guide'];
        $this->assertSame(['good_check', 'watch'], [$guide['key'], $guide['tone']]);
        $html = $this->page($farmer)->getContent();
        $this->assertStringNotContainsString('Good month to plant', $html);
        $this->assertStringContainsString('Good rain for planting', $this->kpiCard($html, 'planting'));

        $this->mockRisk('High', 'Ideal planting period.');
        $this->assertSame('check_first', $this->payload($farmer)['planting_guide']['key']);

        $this->mockRisk('Low', 'Delay planting due to flood risk.');
        $this->assertSame(['wait_flood', 'watch'], [$this->payload($farmer)['planting_guide']['key'], $this->payload($farmer)['planting_guide']['tone']]);

        // No rating: a plain planning message, never a guessed planting word.
        $this->mockRisk('No Data');
        $guide = $this->payload($farmer)['planting_guide'];
        $this->assertNull($guide['key']);
        $this->assertSame('Plan your planting', $guide['title']);

        // Once a date is saved the farmer's own schedule is shown instead.
        $this->openSchedule($farmer);
        $this->assertNull($this->payload($farmer)['planting_guide']);
    }

    public function test_this_month_compares_the_stored_monthly_estimate_with_past_years_and_is_left_out_without_one(): void
    {
        $farmer = $this->farmer([], 2.0);
        $month = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('preparedMonthlyWeatherForecasts')->andReturn([
            $month->format('Y-m') => ['ready' => true, 'generated_at' => now()->toIso8601String(), 'predictions' => ['rainfall' => 229.25, 'temperature' => 27.75, 'tmax' => 31.6, 'tmin' => 23.9, 'humidity' => 81.7, 'wind_speed' => 2.1, 'season' => 'Dry']],
        ]));

        $climate = $this->payload($farmer)['climate'];
        $this->assertSame(229.3, $climate['rain']);                                            // rounded as on the Predictions page
        $this->assertSame('dry', $climate['season']);
        $weather = $this->readable($this->slot($farmer, 'weather', ['retry' => 'weather']));
        $this->assertStringContainsString('This Month', $weather);
        $this->assertStringContainsString('about 229 mm of rain expected', $weather);
        $this->assertStringContainsString('Temperatures about 24–32°C.', $weather);

        // No prepared estimate for this month: the block is left out, never filled with a guess.
        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('preparedMonthlyWeatherForecasts')->andReturn([]));
        $this->assertNull($this->payload($farmer)['climate']);
        $this->assertStringNotContainsString('This Month', $this->readable($this->slot($farmer, 'weather', ['retry' => 'weather'])));
    }

    public function test_without_an_estimate_the_yield_number_is_the_latest_real_record_labelled_as_one(): void
    {
        $farmer = $this->farmer([], 2.0);                                                        // setUp: the model gives no estimate
        \App\Models\RiceProduction::query()->create(['barangay' => 'Binubusan', 'season' => 'Dry', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 5.56, 'area_hectares' => 10, 'total_production' => 55.6, 'year' => 2023]);

        // The yield number fills in after the page opens (the model is slow), the way the live endpoint sends it.
        $card = $this->readable($this->kpiCard($this->slot($farmer, 'today', ['retry' => 'harvest']), 'yield'));
        $this->assertStringContainsString('5.56', $card);
        $this->assertStringContainsString('Last recorded harvest in Binubusan (2023)', $card);
        $this->assertStringNotContainsString('Not Available', $card);

        // No record and no yield history either: a plain "updating" word, never a made-up number.
        \App\Models\RiceProduction::query()->delete();
        $this->yieldRows([]);
        $card = $this->kpiCard($this->slot($farmer, 'today', ['retry' => 'harvest']), 'yield');
        $this->assertStringContainsString('Updating soon', $card);
        $this->assertStringNotContainsString('0.00', $card);
    }

    public function test_each_setup_button_opens_its_own_pop_up_on_the_dashboard(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'John Luke Reynon', 'barangay' => null]);   // nothing set up yet
        $html = $this->page($farmer)->getContent();

        // One pop-up of each kind, each with its own title and its own form.
        foreach (['planting' => ['Add Planting Information', 'farmer.dashboard.planting-info'], 'farm-size' => ['Add Farm Size', 'farmer.dashboard.farm-size'],
            'profile' => ['Update Profile', 'farmer.dashboard.profile'], 'farm-map' => ['Map My Farm', 'farmer.dashboard.farm-map']] as $name => [$title, $route]) {
            $this->assertSame(1, substr_count($html, 'data-mfd-popup-modal="'.$name.'"'), $name);
            $popup = $this->popup($html, $name);
            $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="mfdPopupTitle-'.$name.'"', $popup);
            $this->assertMatchesRegularExpression('/<h2 id="mfdPopupTitle-'.$name.'"[^>]*>'.$title.'<\/h2>/', $popup);
            $this->assertStringContainsString('action="'.route($route).'"', $popup);
        }

        // What is inside depends on the pop-up: the farm size one has no profile fields or map, and so on.
        $names = fn (string $popup) => array_values(array_diff($this->fieldNames($popup), ['_token']));
        $this->assertSame(['planting_date', 'farm_area'], $names($this->popup($html, 'planting')));
        // The farm type is in the farm size pop-up too, but only shown when it is opened as "Farm Information" from Quick Actions.
        $this->assertSame(['farm_area', 'farm_type'], $names($this->popup($html, 'farm-size')));
        $this->assertMatchesRegularExpression('/<div class="fad-field mfd-pm-info">\s*<label[^>]*for="mfdSizeFarmType"/', $this->popup($html, 'farm-size'));
        $this->assertSame(['name', 'contact_number', 'email', 'address', 'barangay', 'farm_type'], $names($this->popup($html, 'profile')));
        $this->assertSame([], $names($this->popup($html, 'farm-map')));
        $this->assertStringContainsString('id="mfdFarmMap"', $this->popup($html, 'farm-map'));
        foreach (['planting', 'farm-size', 'profile'] as $name) {
            $this->assertStringNotContainsString('mfdFarmMap', $this->popup($html, $name), $name);
        }

        // Each button names the pop-up it opens, and keeps its page as the link target for when scripts are off.
        // data-fast-nav-bound keeps the layout's page-leaving fade from starting on a click that does not leave the page.
        $plain = preg_replace('#<svg\b.*?</svg>#s', '', $html);
        $opens = fn (string $label) => preg_match('/<a [^>]*data-mfd-popup="([a-z-]+)" aria-haspopup="dialog" data-fast-nav-bound="true"[^>]*>\s*'.preg_quote($label, '/').'\s*</', $plain, $m) ? $m[1] : null;
        $this->assertSame('planting', $opens('Add Planting Date'));
        $this->assertSame('farm-size', $opens('Add farm size'));
        $this->assertSame('profile', $opens('Update profile'));
        $this->assertSame('farm-map', $opens('Map my farm'));

        $setup = collect($this->data($farmer)['setup']['items'])->pluck('cta', 'key');
        $this->assertSame(['barangay' => 'Update profile', 'farm_type' => 'Update profile', 'farm_area' => 'Add farm size', 'boundary' => 'Map my farm'], $setup->all());
    }

    public function test_the_pop_ups_start_from_what_the_farmer_has_saved(): void
    {
        $farmer = $this->farmer(['email' => 'juan@example.com', 'contact_number' => '0917 123 4567', 'address' => 'Purok 2'], 1.5);
        $schedule = $this->openSchedule($farmer);
        $outline = [['lat' => 14.03, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.651]];
        $farmer->farmerProfile->farmBoundary()->create(['boundary_coordinates' => $outline, 'calculated_area_hectares' => 0.6, 'calculated_perimeter_meters' => 380]);

        $html = $this->page($farmer)->getContent();

        $planting = $this->popup($html, 'planting');
        $this->assertMatchesRegularExpression('/name="farm_area"[^>]*value="1\.50"/s', $planting);
        $this->assertMatchesRegularExpression('/name="planting_date"[^>]*value="'.$schedule->planting_date->toDateString().'"/s', $planting);
        $size = $this->popup($html, 'farm-size');
        $this->assertMatchesRegularExpression('/name="farm_area"[^>]*value="1\.50"/s', $size);
        $this->assertMatchesRegularExpression('/data-mfd-popup-save>Update Farm Size</', $size);  // a saved size is updated, not added again
        $profile = $this->popup($html, 'profile');
        $this->assertMatchesRegularExpression('/name="name"[^>]*value="John Luke Reynon"/s', $profile);
        $this->assertMatchesRegularExpression('/name="email"[^>]*value="juan@example\.com"/s', $profile);
        $this->assertMatchesRegularExpression('/name="contact_number"[^>]*value="0917 123 4567"/s', $profile);
        $this->assertMatchesRegularExpression('/name="address"[^>]*value="Purok 2"/s', $profile);
        $this->assertMatchesRegularExpression('/<option value="Binubusan" selected>/', $profile);
        $this->assertMatchesRegularExpression('/<option value="Irrigated" selected>/', $profile);
        $this->assertStringContainsString('data-boundary="'.e(json_encode($outline)).'"', $this->popup($html, 'farm-map'));
        // The map pop-up checks the farm against the official boundary files and shows where it is.
        $map = $this->popup($html, 'farm-map');
        $this->assertStringContainsString('data-lian-url="'.asset('geojson/lian-boundary.geojson').'"', $map);
        $this->assertStringContainsString('data-barangays-url="'.asset('geojson/lian-barangays.geojson').'"', $map);
        foreach (['Selected Farm Location', 'Municipality', 'Lian, Batangas', 'Barangay', 'Status', 'Not selected yet', 'Only farms located within Lian, Batangas can be registered.'] as $text) {
            $this->assertStringContainsString($text, $map, $text);
        }
        foreach (['GeoJSON', 'polygon', 'centroid', 'geometry', 'intersection'] as $technical) {
            $this->assertStringNotContainsStringIgnoringCase($technical, $this->readable('<body>'.preg_replace('/data-[a-z-]+="[^"]*"/', '', $map).'</body>'), $technical);
        }

        $this->assertMatchesRegularExpression('/data-mfd-popup-save>Save Farm Size</', $this->popup($this->page($this->farmer())->getContent(), 'farm-size'));
    }

    /** One pop-up's markup, from its opening tag to the next pop-up (or the end of the page). */
    private function popup(string $html, string $name): string
    {
        $from = strpos($html, 'data-mfd-popup-modal="'.$name.'"');
        $this->assertNotFalse($from, $name.' pop-up');
        $next = strpos($html, 'data-mfd-popup-modal="', $from + 30);

        return substr($html, $from, $next === false ? null : $next - $from);
    }

    /** @return array<int, string> */
    private function fieldNames(string $html): array
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function test_after_the_pop_up_saves_only_the_sections_that_use_its_values_are_rebuilt(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $slots = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'planting']))->assertOk()->json('slots');

        $this->assertSame(['today', 'highlights', 'schedule', 'harvest', 'farm', 'updates', 'quick'], array_keys($slots));
        $this->assertStringContainsString('My Farm Schedule', $slots['schedule']);

        $live = fn (string $retry) => array_keys($this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => $retry]))->assertOk()->json('slots'));
        $this->assertSame(['header', 'today', 'highlights', 'risk', 'advisories', 'farm', 'updates', 'quick'], $live('profile'));   // the slow harvest card is asked for separately
        $this->assertSame(['farm', 'updates', 'quick'], $live('map'));
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));
        $this->assertStringContainsString("planting: ['today', 'highlights', 'schedule', 'harvest', 'farm', 'updates', 'quick']", $js);   // the script marks the same sections busy
    }

    public function test_the_schedule_shows_the_planting_date_the_day_the_next_activity_and_the_harvest_dates(): void
    {
        $farmer = $this->farmer([], 2.0);
        $schedule = $this->openSchedule($farmer);                                               // planted 5 days ago, harvest in 110 days: a 115 day season

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-schedule'), 6000);
        $planted = CarbonImmutable::parse($schedule->planting_date->toDateString(), 'Asia/Manila');
        $harvest = CarbonImmutable::parse($schedule->scheduled_harvest_date, 'Asia/Manila');

        // The calendar's own record: the planting date as saved, never a made-up one.
        $this->assertSame($planted->toDateString(), $data['schedule']['planting_date']->toDateString());
        $this->assertStringContainsString($planted->format('F j, Y'), $section);
        $this->assertStringContainsString('Day 5 of about 115', $section);
        $this->assertStringContainsString('Planting', $section);
        // Fertilizer at planting runs days 0-7, so it is due now; the first extra fertilizer starts on day 14.
        $this->assertSame('basal', $data['schedule']['current']['key']);
        $this->assertSame('topdress1', $data['schedule']['next']['key']);
        $this->assertSame(9, $data['schedule']['next']['in_days']);
        $this->assertStringContainsString('Fertilizer at planting', $section);
        $this->assertStringContainsString('First extra fertilizer', $section);
        $this->assertStringContainsString($planted->addDays(14)->format('l, F j'), $section);
        $this->assertStringContainsString($harvest->subDays(7)->format('M j').' – '.$harvest->addDays(7)->format('M j, Y'), $section);
        $this->assertStringContainsString('role="progressbar"', $section);
        // The same plot in "My Farm Today" and in Today's Highlights.
        $this->assertStringContainsString('Day 5 after planting', $this->kpiCard($html, 'planting'));
        $this->assertStringContainsString('Fertilizer at planting is due now', $this->readable($html));
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
        $slots = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['retry' => 'harvest']))->assertOk()->json('slots');
        $html = $slots['harvest'];

        $this->assertSame('ok', $h['state']);
        $this->assertSame(['low' => 3.8, 'high' => 4.6], $h['range']);
        $this->assertSame(8.4, $h['production']);                                               // 4.2 t/ha x 2 ha
        $this->assertNotNull($h['ran_at']);                                                     // the date the model last ran
        // Same unit and decimals as the Predictions page, in the number card and in the forecast card.
        $this->assertMatchesRegularExpression('#<span>4\.20</span><small>t/ha</small>#', $slots['today']);
        $this->assertStringContainsString('About 8.4 tons from your 2.00 ha farm', $slots['today']);
        $this->assertStringContainsString('4.20 t/ha', $html);
        $this->assertStringContainsString('Usually between 3.80 and 4.60 t/ha', $html);
        $this->assertStringContainsString('For your 2.00 ha farm: about 8.4 tons.', $html);
        $this->assertStringContainsString('the same one on the Predictions page', $html);
        $this->assertStringContainsString('Normal', $html);                                      // 10% above the average is inside the typical band
        $this->assertStringContainsString('Forecast updated: '.$h['ran_at']->format('F j, Y'), $html);
        $this->assertStringContainsString(route('weather-predictions.index', ['tab' => 'rice-yield']), $html);
        $this->assertStringNotContainsStringIgnoringCase('how sure', $html);                    // no model confidence on the farmer's page
        $this->assertStringNotContainsString('8.4 t/ha', $html);
        $this->assertStringNotContainsString('8.40 t/ha', $html);                                // tons and tons per hectare are never mixed up
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
        $this->assertStringContainsString('Lian average for '.now()->format('F').': 3.82 t/ha.', $html);
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
        $this->assertStringContainsString('Your own past harvests: 3.50 t/ha (2 recorded).', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
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
        $this->assertStringNotContainsString('Lian average', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_an_unavailable_estimate_says_why_and_offers_what_fixes_it_on_its_own_card(): void
    {
        $farmer = $this->farmer([], null);                                                      // no farm size, no planting date; the model also fails
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new \RuntimeException('secret-model-failure')));

        $h = $this->payload($farmer)['harvest'];
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES);

        $this->assertSame('error', $h['state']);
        $this->assertSame(['area'], $h['missing']);                                            // the planting date is not a model input
        $this->assertStringContainsString('Coming soon.', $text);
        $this->assertStringContainsString("Add your farm size to get this season's forecast.", $text);
        $this->assertStringContainsString('Add farm size', $text);
        $this->assertStringNotContainsString('Planting date', $text);
        $this->assertStringNotContainsString('0.00', $html);                                     // never a made-up zero
        $this->assertStringNotContainsString('data-mfd-retry="harvest"', $html);                // a retry cannot add the farm size
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringNotContainsString('secret-model-failure', $html);
    }

    public function test_too_few_weather_records_is_said_plainly_and_not_blamed_on_the_service_or_the_farmer(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')
            ->andReturn(['success' => false, 'predicted_yield' => null, 'missing_inputs' => ['rainfall_6m', 'temp_6m'], 'api_error' => 'Not enough verified input data to run the rice-yield model (missing: rainfall_6m, temp_6m).']));

        $harvest = $this->payload($farmer)['harvest'];

        $this->assertSame('error', $harvest['state']);
        $this->assertStringContainsString("once iClimate's weather records are complete", $harvest['reason']);
        $this->assertStringNotContainsString('not answering', $harvest['reason']);
        $this->assertStringNotContainsString('rainfall_6m', $harvest['reason']);                 // no model input names
    }

    public function test_a_service_failure_with_complete_inputs_blames_the_service_not_the_farmer(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $this->assertStringContainsString('will be back in a few minutes', $this->payload($farmer)['harvest']['reason']);
        $this->assertStringContainsString('data-mfd-retry="harvest"', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));   // here a retry can help
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
        $card = substr($html, strpos($html, 'data-mfd-risk'), 3500);

        $this->assertSame('ok', $data['risk']['state']);
        $this->assertStringContainsString('<span>High Risk</span>', $card);
        $this->assertStringContainsString('mfd-pill is-danger', $card);
        $this->assertStringContainsString('Binubusan', $card);
        $this->assertStringContainsString('Delay planting until rainfall stabilizes.', $card);
        $this->assertStringContainsString('Last checked: '.now('Asia/Manila')->format('M j'), $card);
        $this->assertStringContainsString('The same rating as the Heat Map.', $card);
        $this->assertStringContainsString('href="'.route('heat-map.index').'"', $card);
        $this->assertStringContainsString('High Risk', $this->kpiCard($html, 'risk'));            // the number card says the same
        $this->assertSame('Farm risk in your barangay is High', collect($data['alerts'])->firstWhere('icon', 'map')['title']);
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
        $this->assertStringContainsString('Weather for Lian, Batangas', $html);
        $this->assertStringContainsString('My Farm Schedule', $html);
        $this->assertStringNotContainsString('secret-risk-failure', $html);
    }

    public function test_no_risk_rating_yet_is_not_shown_as_low(): void
    {
        $html = $this->page($this->farmer())->getContent();                                      // setUp: "No Data"
        $start = strpos($html, 'data-mfd-risk');
        $card = html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</section>', $start) - $start)), ENT_QUOTES);

        $this->assertStringContainsString('Your barangay rating is being updated.', $card);
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
        $this->assertStringContainsString('Latest Farming Advice', $this->page($farmer)->getContent());
    }

    public function test_with_no_advisories_the_advice_card_says_so(): void
    {
        $this->assertSame([], $this->data($this->farmer())['advisories']);
        $text = $this->readable($this->page($this->farmer(['email' => 'q@example.test']))->getContent());

        $this->assertStringContainsString('Latest Farming Advice', $text);
        $this->assertStringContainsString('No new farming advice for your area today.', $text);
    }

    public function test_the_advice_card_puts_the_farmers_barangay_first_and_marks_what_is_new(): void
    {
        $farmer = $this->farmer();
        PlantingAdvisory::factory()->create(['title' => 'Lian-wide reminder', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subHour(), 'published_at' => now()->subHour()]);
        PlantingAdvisory::factory()->create(['title' => 'Binubusan drainage check', 'target_scope' => 'barangay', 'target_barangay' => 'Binubusan', 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDays(2), 'published_at' => now()->subDays(2)]);
        PlantingAdvisory::factory()->create(['title' => 'Old guidance', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDays(20), 'published_at' => now()->subDays(20)]);

        $data = $this->data($farmer);

        $this->assertSame('Binubusan drainage check', $data['advisories'][0]['title']);
        $this->assertTrue($data['advisories'][0]['for_barangay']);
        $this->assertSame([true, true, false], array_column($data['advisories'], 'is_new'));
        $this->assertSame(2, $data['advisories_new']);                                          // published this week
        $this->assertContains('advisories', array_column($data['reminders'], 'key'));
        $this->assertSame('advisory', collect($data['highlights'])->firstWhere('key', 'advisory')['icon']);
        $this->assertNotEmpty($data['advisories'][0]['source']);                                // who it is from, as on the Advisories page
    }

    public function test_my_farm_shows_the_farmers_own_farm(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 10:00', 'Asia/Manila'));
        $this->fakeWeather([4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0]);
        $this->mockRisk('Low');
        $farmer = $this->farmer([], 1.5);                                                        // lives in Binubusan
        $this->openSchedule($farmer);                                                            // planted 5 days ago
        $farmer->farmerProfile->farmBoundary()->create(['boundary_coordinates' => [['lat' => 14.03, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.651]],
            'barangay' => 'San Diego', 'calculated_area_hectares' => 0.6, 'calculated_perimeter_meters' => 380]);

        $data = $this->data($farmer);
        $p = $data['profile'];
        $this->assertSame('Binubusan', $p['barangay']);
        $this->assertSame(1.5, $p['area']);
        $this->assertSame('Irrigated (canal or pump)', $p['water']);
        $this->assertSame('2026-09-29', $p['planting_date']->toDateString());
        $this->assertSame('Barangay San Diego', $p['mapped_in']);                                // the mapped farm's barangay, from the map
        $this->assertSame(['value' => 'Good', 'status' => 'good', 'note' => 'Only light rain today. Conditions are manageable.'], $data['condition']);

        $section = $this->farmSection($this->page($farmer)->getContent());
        foreach (['My Farm', 'Binubusan', '1.50 ha', 'Irrigated (canal or pump)', 'September 29, 2026', 'Mapped in Barangay San Diego', 'Your farm information is complete.'] as $text) {
            $this->assertStringContainsString($text, $section, $text);
        }
        $this->assertStringNotContainsString('data-mfd-popup', $section);                        // nothing is missing, so nothing to add
        foreach (['14.03', '120.65', '2026-09-29', '@'] as $raw) {
            $this->assertStringNotContainsString($raw, $section, $raw);                          // no raw positions, dates or personal details
        }
    }

    public function test_my_farm_says_what_is_missing_and_offers_the_right_pop_up(): void
    {
        $this->replaceWeather(Http::response([], 500));                                          // no weather either
        $farmer = $this->farmer();                                                               // no farm size, no map, no planting date; no risk rating yet

        $data = $this->data($farmer);
        $this->assertNull($data['profile']['area']);
        $this->assertNull($data['profile']['planting_date']);
        $this->assertNull($data['condition']['value']);                                          // never "Good" without data

        $html = $this->page($farmer)->getContent();
        $section = $this->farmSection($html);
        $this->assertStringNotContainsString('Not set', $section);
        $this->assertStringContainsString('Still needed', $section);
        preg_match_all('/data-mfd-popup="([a-z-]+)"[^>]*>([^<]+)</', $section, $m);
        $opens = array_combine($m[2], $m[1]);
        $this->assertSame('farm-size', $opens['Add farm size']);                                 // farm size: in the details and in Still needed
        $this->assertSame('planting', $opens['Add when you plant']);                             // planting date
        $this->assertSame('farm-map', $opens['Map my farm']);
        foreach (['>0<', '0.00 ha', '0°C', 'N/A', 'Unknown'] as $fake) {
            $this->assertStringNotContainsString($fake, $section, $fake);
        }
        $this->assertStringContainsString('Check your field', $this->kpiCard($html, 'condition'));   // a safe thing to do, not a rating
        $this->assertStringNotContainsString('>Good<', $this->kpiCard($html, 'condition'));
    }

    public function test_a_planting_date_still_to_come_is_upcoming_not_active(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer, ['planting_date' => now('Asia/Manila')->addDays(9)->toDateString()]);

        $data = $this->data($farmer);
        $this->assertFalse($data['schedule']['planted']);
        $this->assertSame(9, $data['schedule']['until_planting']);
        $this->assertStringContainsString('Planting in 9 days', $this->kpiCard($this->page($farmer)->getContent(), 'planting'));
    }

    public function test_farm_condition_is_the_most_serious_real_warning_never_a_separate_opinion(): void
    {
        // Heavy rain today: a real risk, said plainly with what to do. It is never softened to "Good".
        $this->fakeWeather([40.0, 2.0, 1.0, 1.0, 1.0, 1.0, 1.0]);
        $this->mockRisk('Low');
        $data = $this->data($this->farmer());
        $this->assertSame('Needs Attention', $data['condition']['value']);
        $this->assertSame('danger', $data['condition']['status']);
        $this->assertStringContainsString('Heavy rain expected today', $data['condition']['note']);
        $this->assertStringContainsString('Heavy rain expected today', $data['alerts'][0]['title']);  // the same warning as Today's Highlights
        $this->assertSame('heavy', $data['weather']['levels']['rain']);

        // A calm day in a barangay rated Moderate: the rating shows through, in the rating's own words.
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5]);
        $this->mockRisk('Moderate');
        $data = $this->data($this->farmer(['email' => 'moderate@example.test']));
        if ($data['alerts'] === []) {
            $this->assertSame(['value' => 'Watch', 'status' => 'watch', 'note' => __('farmer.condition.note_check')], $data['condition']);
        }
        $this->assertNotSame('Good', $data['condition']['value']);

        // The same calm day rated High is a high risk on the card too: one risk level everywhere on the page.
        $this->mockRisk('High');
        $data = $this->data($this->farmer(['email' => 'high@example.test']));
        $this->assertSame('Needs Attention', $data['condition']['value']);
        $this->assertTrue($data['risk']['high']);
    }

    public function test_my_farm_today_is_six_numbers_from_the_same_reading(): void
    {
        $this->fakeWeather([12.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], 82, ['temperature' => 31.0]);
        $data = $this->data($farmer = $this->farmer());

        $this->assertSame(['temperature' => 'warm', 'rain' => 'moderate', 'humidity' => 'high'], $data['weather']['levels']);
        $html = $this->page($farmer)->getContent();
        $today = substr($html, strpos($html, 'data-mfd-today'), strpos($html, 'data-mfd-highlights') - strpos($html, 'data-mfd-today'));
        $this->assertSame(['weather', 'rain', 'condition', 'planting', 'risk', 'yield'], preg_match_all('/data-kpi="([a-z]+)"/', $today, $m) ? $m[1] : []);
        $this->assertMatchesRegularExpression('#<span>31\.0</span><small>°C</small>#', $this->kpiCard($html, 'weather'));
        $this->assertMatchesRegularExpression('#<span>12</span><small>mm</small>#', $this->kpiCard($html, 'rain'));
        $this->assertStringContainsString('Updated '.$data['weather']['updated_label'], $this->kpiCard($html, 'weather'));
        $this->assertStringContainsString('82%', substr($html, strpos($html, 'data-mfd-weather'), 5000));   // humidity is in the weather card, not repeated
        foreach (['Total Users', 'Total Farmers', 'System Requests', 'Database Records'] as $admin) {
            $this->assertStringNotContainsString($admin, $today, $admin);                        // no administrator numbers
        }

        // The bands come from the one config file, and the top bands are the warning limits themselves.
        $kpi = app(FarmerKpiService::class);
        $this->assertSame('heavy', $kpi->todayLevels(30.0, (float) config('farmer_dashboard.heavy_rain_mm'), 50.0)['rain']);
        $this->assertSame('very_high', $kpi->todayLevels(30.0, 0.0, (float) config('farmer_dashboard.humid_percent'))['humidity']);
        $this->assertSame(['temperature' => null, 'rain' => null, 'humidity' => null], $kpi->todayLevels(null, null, null));
    }

    public function test_a_number_that_may_confuse_has_a_what_does_this_mean_pop_up(): void
    {
        $this->mockRisk('Low');
        $html = $this->page($this->farmer())->getContent();

        foreach (['mfdLearnChance', 'mfdLearnCondition', 'mfdLearnRisk'] as $id) {
            $this->assertMatchesRegularExpression('/<button type="button" class="mfd-learn" data-ic-template="'.$id.'"[^>]*aria-haspopup="dialog"/', $html, $id);
            $this->assertStringContainsString('<template id="'.$id.'">', $html);
        }
        $this->assertStringContainsString('What should I do?', $html);
        $this->assertMatchesRegularExpression('/A 60% chance of rain means rain is possible in Lian today\./', $html);
        $this->assertStringContainsString("closeLabel: button.dataset.icCloseLabel || undefined", file_get_contents(resource_path('js/info-modal.js')));
    }

    public function test_the_yield_forecast_reads_plainly_against_the_usual_yield(): void
    {
        $kpi = app(FarmerKpiService::class);
        $this->assertSame(['typical', 'typical', 'above', 'below'], array_map($kpi->yieldAgainstTypical(...), [10.0, -10.0, 10.5, -18.0]));

        // Well under the Lian average: said calmly, with what to look at; never "bad" or "poor".
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(3.0);
        $this->lianYields(4.0);
        $this->assertSame('below', $this->payload($farmer)['harvest']['typical']);
        $html = $this->slot($farmer, 'harvest', ['retry' => 'harvest']);
        $this->assertStringContainsString('Below usual', $html);
        foreach (['bad', 'poor', 'fail'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, strip_tags($html), $word);
        }
    }

    public function test_latest_farming_advice_opens_in_the_advisory_pop_up(): void
    {
        $farmer = $this->farmer();
        PlantingAdvisory::factory()->create(['title' => 'Prepare field drainage', 'status' => 'published', 'target_barangay' => null, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(3)]);
        $html = $this->page($farmer)->getContent();
        if (! str_contains($html, 'Prepare field drainage')) {
            $this->markTestSkipped('The advisory factory does not produce an in-effect advisory for this farmer.');
        }

        $this->assertMatchesRegularExpression('/<a class="mfd-btn is-quiet"[^>]*data-fad-view[^>]*aria-haspopup="dialog"[^>]*>View Advisory</', $html);
        $this->assertStringContainsString('data-fad-modal', $html);                               // the same pop-up shell as the Advisories page
        $this->assertStringContainsString('id="fadModalLoading"', $html);
    }

    public function test_the_greeting_keeps_a_two_word_family_name_together(): void
    {
        $name = fn (string $full) => $this->payload(User::factory()->create(['role' => User::ROLE_FARMER, 'name' => $full]))['name'];

        $this->assertSame('Juan', $name('Juan Dela Cruz'));
        $this->assertSame('Maria Clara', $name('Maria Clara De Leon'));
        $this->assertSame('Jose', $name('Jose De La Cruz'));
        $this->assertSame('John Luke', $name('John Luke Reynon'));
        $this->assertSame('Ana', $name('Ana Santos'));
    }

    /** The "My Farm at a Glance" section's own markup. */
    /** The "My Farm" section's own markup. */
    private function farmSection(string $html): string
    {
        $from = strpos($html, 'data-mfd-farm>');
        $this->assertNotFalse($from, 'My Farm section');

        return substr($html, $from, strpos($html, '</section>', $from) - $from);
    }

    public function test_quick_actions_are_four_tasks_not_a_second_menu(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $quick = $this->quickSection($html);

        // Climora, then three farm tasks, each opening its own pop-up on the dashboard.
        $this->assertSame(1, substr_count($quick, 'data-ai-open'));
        preg_match_all('/<a class="mfd-quick-card"[^>]*data-mfd-popup="([a-z-]+)"[^>]*data-quick="([a-z]+)"/', $quick, $m);
        $this->assertSame(['farm' => 'farm-size', 'plan' => 'planting', 'map' => 'farm-map'], array_combine($m[2], $m[1]));
        $this->assertSame(3, substr_count($quick, '<a '));
        // The Farm Information card is the farm size pop-up with its water source field shown, under its own title.
        $this->assertMatchesRegularExpression('/data-quick="farm"\s+data-mfd-popup-variant="info" data-mfd-popup-title="Farm Information"/', $quick);
        $this->assertSame(['farm_area', 'farm_type'], array_values(array_diff($this->fieldNames($this->popup($html, 'farm-size')), ['_token'])));

        // Pages that are already linked from the cards above are not repeated here.
        foreach (['View Advisories', 'Open Calendar', 'Check Prediction', 'Notifications', 'My Profile', 'Update Profile', 'Community'] as $gone) {
            $this->assertStringNotContainsString($gone, $quick, $gone);
        }
        foreach ([route('planting-advisories.index'), route('notifications.index'), route('profile.edit'), route('weather-predictions.index')] as $link) {
            $this->assertStringNotContainsString('href="'.$link, $quick, $link);
        }
    }

    public function test_quick_action_wording_follows_what_the_farmer_has_saved(): void
    {
        // Nothing saved yet: the same cards, never marked "Not set".
        $new = $this->farmer();
        $this->assertSame(['farm' => false, 'map' => false, 'plan' => false], $this->data($new)['quick']);
        $text = html_entity_decode(strip_tags($this->quickSection($this->page($new)->getContent())), ENT_QUOTES);
        foreach (['Update Farm Information', 'Map My Farm', 'Planting Plan'] as $title) {
            $this->assertStringContainsString($title, $text, $title);
        }
        $this->assertStringNotContainsString('Not set', $text);

        // Everything saved: the same three cards, with no "Not set".
        $set = $this->farmer([], 2.0);
        $this->openSchedule($set);
        $set->farmerProfile->farmBoundary()->create(['boundary_coordinates' => [['lat' => 14.03, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.65], ['lat' => 14.031, 'lng' => 120.651]], 'calculated_area_hectares' => 0.6, 'calculated_perimeter_meters' => 380]);
        $this->assertSame(['farm' => true, 'map' => true, 'plan' => true], $this->data($set)['quick']);
        $text = html_entity_decode(strip_tags($this->quickSection($this->page($set)->getContent())), ENT_QUOTES);
        foreach (['Update Farm Information', 'Map My Farm', 'Planting Plan'] as $title) {
            $this->assertStringContainsString($title, $text, $title);
        }
        $this->assertStringNotContainsString('Not set', $text);

        // A harvested plan is finished: the card goes back to "Add Planting Plan".
        $done = $this->farmer([], 2.0);
        $this->openSchedule($done, ['progress_status' => 'Harvested']);
        $this->assertFalse($this->data($done)['quick']['plan']);
    }

    public function test_unread_notifications_are_still_counted_for_the_page(): void
    {
        $farmer = $this->farmer();
        $first = Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => true, 'type' => 'Announcement']);

        $this->assertSame(2, $this->data($farmer)['unread']);

        $this->actingAs($farmer)->patch(route('notifications.mark-read', $first->id))->assertRedirect();

        $this->assertSame(1, $this->data($farmer)['unread']);                                    // no stale count after reading
    }

    /** The Quick Actions section's own markup. */
    private function quickSection(string $html): string
    {
        $from = strpos($html, 'data-mfd-quick');
        $this->assertNotFalse($from, 'Quick Actions section');

        return substr($html, $from, strpos($html, '</section>', $from) - $from);
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
        foreach (['Dashboard', 'Advisories', 'Calendar', 'Community', 'Messages', 'Climate Records', 'Predictions', 'Rice Production'] as $link) {
            $this->assertStringContainsString($link, $html, $link);
        }
        // Notifications are reached from the top bar bell only, so the sidebar does not repeat them.
        $this->assertStringContainsString('data-ic-notifications', $html);
        $this->assertDoesNotMatchRegularExpression('/class="sidebar-link[^"]*" href="'.preg_quote(route('notifications.index'), '/').'"/', $html);
    }

    public function test_georgia_titles_and_inter_text_are_the_only_families_on_the_page(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));

        $this->assertStringContainsString('family=Inter:wght@', $html);
        $this->assertStringContainsString('class="is-farmer-dashboard"', $html);
        $this->assertStringContainsString('--mfd-sans: "Inter", Arial, system-ui, -apple-system, "Segoe UI", sans-serif;', $css);
        $this->assertStringContainsString('--mfd-serif: Georgia, "Times New Roman", Times, serif;', $css);         // page and section titles
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $css);
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
        $this->assertStringContainsString('.mfd[data-farmer-dashboard] .mfd-kpis { display: grid; gap: 16px; grid-template-columns: repeat(3, minmax(0, 1fr)); }', $css);   // desktop
        $this->assertStringContainsString('.mfd[data-farmer-dashboard] .mfd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $css);                            // tablet
        $this->assertStringContainsString('.mfd[data-farmer-dashboard] .mfd-kpis, .mfd[data-farmer-dashboard] .mfd-quick { grid-template-columns: minmax(0, 1fr); gap: 12px; }', $css);   // phone
        $this->assertStringContainsString('.mfd[data-farmer-dashboard] { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch; }', $css);      // pairs on a desktop
        $this->assertStringNotContainsString('overflow-x: auto', $css);                          // nothing scrolls sideways
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])order:\s*\d/', $css);             // never rearranged by CSS
    }

    public function test_the_mao_and_it_dashboards_keep_their_own_look(): void
    {
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));
        $sections = substr($css, strpos($css, 'Farmer dashboard sections'), strpos($css, 'dashboard pop-ups') - strpos($css, 'Farmer dashboard sections'));

        // Every new rule is scoped to the Farmer dashboard (the IT dashboard's root is also .mfd).
        preg_match_all('/^\s*([.#][^{@]*)\{/m', $sections, $m);
        foreach ($m[1] as $selector) {
            foreach (explode(',', $selector) as $one) {
                $this->assertStringStartsWith('.mfd[data-farmer-dashboard]', trim($one), trim($one));
            }
        }
        // The shared rules the other dashboards use are still there, unchanged.
        foreach (['.mfd-kpis { display: grid; gap: var(--gap); grid-template-columns: repeat(2, minmax(0, 1fr)); }', '.mfd-alert-list { display: grid; gap: 8px; }', '.mfd-chart { position: relative; height: 280px; }'] as $rule) {
            $this->assertStringContainsString($rule, $css, $rule);
        }
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
        $body = $this->body($html);

        $this->assertStringContainsString('role="status" aria-live="polite" data-mfd-live', $html);       // a refresh is announced
        $this->assertStringContainsString('focus-visible', $css);
        $this->assertStringContainsString('min-height: 44px', $css);                                      // tap targets
        $this->assertMatchesRegularExpression('/aria-label="Refresh the dashboard"/', $html);
        $this->assertStringContainsString('<label for="mfdLanguage">Language</label>', $html);            // the language selector is labelled
        foreach (['mfdTodayTitle', 'mfdHighlightsTitle', 'mfdWeatherTitle', 'mfdScheduleTitle', 'mfdRiskTitle', 'mfdFarmTitle', 'mfdUpdatesTitle', 'mfdQuickTitle'] as $id) {
            $this->assertStringContainsString('aria-labelledby="'.$id.'"', $html, $id);
        }
        $slots = preg_replace('/<div class="mfd-tabs".*?<\/nav>/s', '', substr($body, 0, strpos($body, 'data-mfd-popup-modal') ?: strlen($body)));
        $this->assertSame(0, preg_match('/<svg(?![^>]*aria-hidden)/', $slots), 'decorative icons are hidden from screen readers');
        foreach (['mfd-pill is-watch', 'mfd-pill is-danger', 'mfd-pill is-good'] as $pill) {
            if (str_contains($slots, $pill)) {
                $this->assertMatchesRegularExpression('/'.$pill.'"><svg[^>]*>.*?<\/svg>\s*<span>[A-Za-z]/s', $slots, "$pill has an icon and words");
            }
        }
    }

    public function test_every_word_on_the_page_comes_from_the_language_file(): void
    {
        $en = require lang_path('en/farmer.php');
        $fil = require lang_path('fil/farmer.php');
        foreach (['greeting', 'header', 'today', 'highlights', 'weather_card', 'schedule', 'risk', 'harvest', 'advisories', 'my_farm', 'updates', 'quick', 'learn', 'state', 'reason'] as $group) {
            $this->assertArrayHasKey($group, $en, $group);
        }

        // English and Filipino have the same keys (Filipino also names the weather words the provider sends).
        $keys = function (array $a, string $prefix = '') use (&$keys): array {
            $out = [];
            foreach ($a as $k => $v) {
                $out = array_merge($out, is_array($v) && $v !== [] ? $keys($v, $prefix.$k.'.') : [$prefix.$k]);
            }

            return $out;
        };
        $missing = array_diff(array_filter($keys($en), fn ($k) => $k !== 'conditions'), $keys($fil));
        $this->assertSame([], array_values($missing), 'every English key has a Filipino translation');

        $this->assertSame('Good evening, :name', __('farmer.greeting.evening'));
        app()->setLocale('fil');
        $this->assertSame('Magandang gabi, :name', __('farmer.greeting.evening'));
        $this->assertSame('Mababang Panganib', __('farmer.risk.level_full.low'));
        $this->assertSame('Mahinang Ulan', FarmerDashboardService::conditionText('Light Rain'));
        app()->setLocale('en');
        $this->assertSame('Light Rain', FarmerDashboardService::conditionText('Light Rain'));

        foreach (glob(resource_path('views/dashboards/farmer/*.blade.php')) as $view) {
            if (basename($view) === '_status.blade.php') {
                continue;                                                                       // the pill only shows the words it is given
            }
            $this->assertStringContainsString('__(', file_get_contents($view), basename($view).' uses the language file');
        }
    }

    public function test_the_farmer_can_switch_to_filipino_and_the_numbers_stay_the_same(): void
    {
        $this->mockRisk('Low');
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $english = $this->page($farmer);
        $en = $english->viewData('f');
        $this->assertStringContainsString('<option value="en" lang="en" selected>English</option>', $english->getContent());

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.language'), ['language' => 'fil'])->assertOk()->assertJson(['language' => 'fil']);
        $this->assertSame('fil', $farmer->fresh()->preferred_language);                          // kept on the account

        $page = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();            // no Cache::flush(): a separate copy per language
        $fil = $page->viewData('f');
        $html = $page->getContent();
        $text = $this->readable($html);

        $this->assertSame('fil', $fil['locale']);
        $this->assertMatchesRegularExpression('/^Magandang (umaga|hapon|gabi), John Luke$/', $fil['greeting']);
        foreach (['Ang Aking Bukid Ngayon', 'Panahon Ngayon', 'Ulan Ngayon', 'Kalagayan ng Bukid', 'Mababang Panganib', 'Mahalaga Ngayon', 'Iskedyul ng Aking Bukid', 'Mabilisang Gawain'] as $word) {
            $this->assertStringContainsString($word, $text, $word);
        }
        $this->assertStringContainsString('<option value="fil" lang="fil" selected>Filipino</option>', $html);
        // Names, barangays, numbers and units are never translated, and every number is the same as in English.
        $this->assertStringContainsString('Binubusan, Lian, Batangas', $text);
        $this->assertSame($en['weather']['temperature'], $fil['weather']['temperature']);
        $this->assertSame($en['weather']['rain_today'], $fil['weather']['rain_today']);
        $this->assertSame($en['risk']['level'], $fil['risk']['level']);
        $this->assertSame($en['schedule']['day'], $fil['schedule']['day']);
        $this->assertSame($en['schedule']['planting_date']->toDateString(), $fil['schedule']['planting_date']->toDateString());
        $this->assertStringContainsString('2.00 ha', $text);
        $this->assertStringContainsString('°C', $text);
        // The refresh and the pop-ups answer in the same language.
        $this->assertStringContainsString('Ang Aking Bukid Ngayon', $this->slot($farmer, 'today', []));

        // Only English and Filipino can be chosen; without scripts the form posts back to the dashboard.
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.language'), ['language' => 'fr'])->assertUnprocessable();
        $this->actingAs($farmer)->post(route('farmer.dashboard.language'), ['language' => 'en'])->assertRedirect(route('farmer.dashboard'));
        $this->assertSame('en', $farmer->fresh()->preferred_language);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->postJson(route('farmer.dashboard.language'), ['language' => 'fil'])->assertForbidden();
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

    public function test_the_new_language_is_used_at_once_even_with_the_signed_in_user_cached(): void
    {
        config(['auth.user_cache_seconds' => 30]);
        $farmer = $this->farmer([], 2.0);
        $provider = auth()->createUserProvider('users');
        $this->assertSame('en', \App\Support\FarmerLanguage::resolve(tap(request(), fn ($r) => $r->setUserResolver(fn () => $provider->retrieveById($farmer->id)))));   // now cached

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.language'), ['language' => 'fil'])->assertOk();

        // The next request reads the signed-in user through the cache: it must already carry the new choice.
        $this->assertSame('fil', $provider->retrieveById($farmer->id)->preferred_language);
    }

    public function test_the_live_endpoint_rebuilds_each_section(): void
    {
        $json = $this->actingAs($this->farmer())->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertEqualsCanonicalizing(['header', 'today', 'highlights', 'weather', 'schedule', 'risk', 'harvest', 'advisories', 'farm', 'updates', 'quick'], array_keys($json['slots']));
        $this->assertStringContainsString('Good ', $json['slots']['header']);
        $this->assertStringContainsString('My Farm Today', $json['slots']['today']);
        $this->assertStringContainsString('My Farm', $json['slots']['farm']);
        $this->assertStringContainsString('No new farming advice for your area today.', $json['slots']['advisories']);
        $this->assertArrayHasKey('updated', $json);
    }

    public function test_the_timed_refresh_never_waits_for_the_harvest_model(): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));

        $json = $this->actingAs($this->farmer())->getJson(route('farmer.dashboard.live'))->assertOk()->json();

        $this->assertArrayNotHasKey('harvest', $json['slots']);                                // a finished card is not replaced by a placeholder
        $this->assertArrayHasKey('today', $json['slots']);
    }

    public function test_each_card_has_its_own_try_again_that_rebuilds_only_what_it_needs(): void
    {
        $farmer = $this->farmer([], 2.0);

        $expect = [
            'harvest' => ['harvest', 'today'],
            'risk' => ['risk', 'today', 'highlights'],
            'weather' => ['header', 'today', 'highlights', 'weather'],
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

        $this->assertStringContainsString('The latest weather is on its way.', $json['slots']['today']);
        $this->assertStringContainsString('data-mfd-retry="weather"', $json['slots']['today']);
        $this->assertStringContainsString(e("We can't show this right now."), $json['slots']['weather']);
        $this->assertStringContainsString('Good ', $json['slots']['header']);
        $this->assertStringNotContainsString('0.0 mm', $json['slots']['today']);                  // not a made-up zero
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
        $text = $this->readable($html);

        $this->assertSame('empty', $data['season']['state']);
        $this->assertSame('nobarangay', $data['risk']['state']);
        $this->assertSame([], $data['advisories']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame(0, $data['unread']);
        $this->assertGreaterThanOrEqual(3, count($data['setup']['items']));
        $this->assertStringContainsString('Good morning, Ana', str_replace(['afternoon', 'evening'], 'morning', $text));
        $this->assertStringContainsString('Lian, Batangas', $text);
        $this->assertStringContainsString('Still needed', $text);
        $this->assertStringContainsString('Add your planting date to receive better guidance.', $text);
        $this->assertStringContainsString('Planting guide for this season', $text);
        $this->assertStringContainsString('Add your barangay to see farm risk for your area.', $text);
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
        foreach (['weather', 'risk'] as $retry) {
            $this->assertStringContainsString('data-mfd-retry="'.$retry.'"', $html);
        }
        $this->assertStringContainsString('My Farm Schedule', $html);
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

        $this->assertNull($data['unread']);                                                     // unknown, never shown as 0
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
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('Add your planting date to receive better guidance.');     // page is now cached

        $this->openSchedule($farmer);                                                              // no Cache::flush(): the model event must do it
        $after = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();

        $after->assertSee('Day 5 of about 115')->assertDontSee('Add your planting date to receive better guidance.');
    }

    public function test_messages_community_and_rice_production_come_from_their_own_pages_sources(): void
    {
        $farmer = $this->farmer([], 2.0);
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'name' => 'MAO Staff']);
        $conversation = \App\Models\Conversation::query()->create(['participant_one_id' => min($farmer->id, $mao->id), 'participant_two_id' => max($farmer->id, $mao->id), 'last_message_at' => now()]);
        foreach (['Hello', 'Seed pickup is Friday'] as $body) {
            \App\Models\ConversationMessage::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $mao->id, 'body' => $body]);
        }
        \App\Models\ConversationMessage::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $farmer->id, 'body' => 'Thank you']);   // my own message is not unread
        \App\Models\ConversationMessage::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $mao->id, 'body' => 'Read already', 'read_at' => now()]);
        \App\Models\FeedPost::query()->create(['user_id' => $mao->id, 'title' => 'Seed Distribution Program', 'body' => 'Free certified seeds at the municipal hall.', 'category' => 'Announcement', 'visibility' => 'All Farmers']);
        \App\Models\FeedPost::query()->create(['user_id' => $mao->id, 'title' => 'Archived post', 'body' => 'Old.', 'category' => 'Announcement', 'visibility' => 'All Farmers', 'archived_at' => now()]);
        \App\Models\RiceProduction::query()->create(['barangay' => 'Binubusan', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.1, 'area_hectares' => 10, 'total_production' => 41, 'year' => 2024]);
        \App\Models\RiceProduction::query()->create(['barangay' => 'Binubusan', 'season' => 'Dry', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 3.6, 'area_hectares' => 10, 'total_production' => 36, 'year' => 2023]);
        \App\Models\RiceProduction::query()->create(['barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Rainfed', 'yield_per_hectare' => 5.0, 'area_hectares' => 10, 'total_production' => 50, 'year' => 2025]);

        $data = $this->payload($farmer);

        $this->assertSame(2, $data['messages']);
        $this->assertSame(2, \App\Models\ConversationMessage::unreadCountFor($farmer));         // the Messages page's own rule
        $this->assertSame('Seed Distribution Program', $data['community']['title']);
        $this->assertSame('Municipal Agriculture Office', $data['community']['author']);
        $this->assertSame(4.1, $data['production']['yield']);                                    // the farmer's barangay, newest year
        $this->assertSame(2024, (int) $data['production']['year']);
        $this->assertContains('messages', array_column($data['reminders'], 'key'));

        $html = $this->page($farmer)->getContent();
        $updates = substr($html, strpos($html, 'data-mfd-updates'), 6000);
        $this->assertStringContainsString('2 unread messages', $updates);
        $this->assertStringContainsString('Seed Distribution Program', $updates);
        $this->assertStringNotContainsString('Seed pickup is Friday', $updates);                 // never a message thread on the dashboard
        foreach (['messages.index', 'community-feed.index', 'notifications.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $updates, $route);
        }
        $this->assertStringContainsString('Last recorded harvest in Binubusan', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
        $this->assertStringContainsString('A recorded past harvest, not this season.', $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_every_summary_links_to_its_own_farmer_page(): void
    {
        $this->mockRisk('Low');
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $body = $this->body($this->page($farmer)->getContent());

        foreach ([route('weather-predictions.index', ['tab' => 'weather-forecast']), route('heat-map.index'), route('calendar.index'), route('planting-advisories.index'),
            route('community-feed.index'), route('messages.index'), route('notifications.index')] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $body, $href);
        }
        $this->assertStringContainsString(route('weather-predictions.index', ['tab' => 'rice-yield']), $this->slot($farmer, 'harvest', ['retry' => 'harvest']));
    }

    public function test_a_moderate_barangay_rating_is_one_of_todays_highlights(): void
    {
        $this->fakeWeather([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0], options: ['code' => 0, 'currentRain' => 0.0, 'chance' => 5]);
        $this->mockRisk('Moderate');
        $highlights = $this->payload($this->farmer())['highlights'];

        $this->assertGreaterThanOrEqual(2, count($highlights));
        $this->assertLessThanOrEqual(4, count($highlights));
        $risk = collect($highlights)->firstWhere('key', 'risk');
        $this->assertSame('Moderate Risk in Binubusan', $risk['title']);
        $this->assertSame('watch', $risk['level']);
        $this->assertNotEmpty($risk['todo']);
        foreach ($highlights as $item) {
            $this->assertNotEmpty($item['what'], $item['key'].' says what is happening');
            $this->assertTrue(! empty($item['todo']) || $item['key'] === 'action', $item['key'].' says what to do');   // an action is itself what to do
        }
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
