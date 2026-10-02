<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
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

    /** @param  array<int, float>  $rain  rainfall per forecast day (index 0 = today) */
    private function weatherPayload(array $rain, int $humidity = 82, float $temperature = 28.3): array
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = count($rain);

        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $today->addHours(15)->format('Y-m-d\TH:i'), 'temperature_2m' => $temperature, 'relative_humidity_2m' => $humidity,
                'apparent_temperature' => 33.7, 'precipitation' => 0.2, 'rain' => 0.2, 'showers' => 0, 'weather_code' => 53,
                'cloud_cover' => 90, 'pressure_msl' => 1010, 'surface_pressure' => 1008, 'wind_speed_10m' => 8.4, 'wind_direction_10m' => 90,
                'wind_gusts_10m' => 15, 'is_day' => 1,
            ],
            'daily' => [
                'time' => array_map(fn ($i) => $today->addDays($i)->toDateString(), range(0, $days - 1)),
                'weather_code' => array_fill(0, $days, 53),
                'temperature_2m_max' => array_fill(0, $days, 31.0), 'temperature_2m_min' => array_fill(0, $days, 24.0),
                'apparent_temperature_max' => array_fill(0, $days, 34.0), 'apparent_temperature_min' => array_fill(0, $days, 26.0),
                'sunrise' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T05:42', range(0, $days - 1)),
                'sunset' => array_map(fn ($i) => $today->addDays($i)->format('Y-m-d').'T18:20', range(0, $days - 1)),
                'precipitation_sum' => $rain, 'rain_sum' => $rain, 'showers_sum' => array_fill(0, $days, 0.0),
                'precipitation_probability_max' => array_fill(0, $days, 60), 'wind_speed_10m_max' => array_fill(0, $days, 12.0),
                'wind_gusts_10m_max' => array_fill(0, $days, 20.0),
            ],
            'hourly' => ['time' => [$today->format('Y-m-d').'T15:00'], 'temperature_2m' => [$temperature], 'relative_humidity_2m' => [$humidity],
                'precipitation_probability' => [60], 'precipitation' => [0.2], 'rain' => [0.2]],
        ];
    }

    private function fakeWeather(array $rain = [4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], int $humidity = 82): void
    {
        $this->replaceWeather(Http::response($this->weatherPayload($rain, $humidity), 200));
    }

    /** Http::fake stubs match in registration order, so a new stub must replace the old one. */
    private function replaceWeather($response): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        // Everything else (the ML service, other providers) fails fast instead of waiting on a real network call.
        Http::fake(['api.open-meteo.com/*' => $response, '*' => Http::response([], 500)]);
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

    private function data(?User $as = null): array
    {
        return $this->page($as)->viewData('f');
    }

    /** The overview body only (no sidebar, header or chat widget scripts). */
    private function body(string $html): string
    {
        $start = strpos($html, 'data-farmer-dashboard');

        return substr($html, $start, strpos($html, 'About This Information') - $start + 1800);
    }

    private function kpi(array $data, string $key): ?array
    {
        return collect($data['kpis'])->firstWhere('key', $key);
    }

    private function mockPrediction(float $yield, string $areaSource): void
    {
        $this->mock(RiceYieldPredictionService::class, function ($mock) use ($yield, $areaSource): void {
            $mock->shouldReceive('predictForUser')->andReturn([
                'success' => true,                                                                  // the real service always reports whether a prediction was made
                'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield, 'target_month' => now()->format('Y-m'),
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

    // ------------------------------------------------------------ access + the chart bug

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

    public function test_the_chart_library_loader_recognises_charts_drawn_without_an_id(): void
    {
        // Root cause of the old "Chart unavailable" boxes: canvases from the shared overview component have no id, so nothing
        // matched the selector that decides whether Chart.js is loaded at all.
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("'canvas[data-dash-chart]'", $js);
        $this->assertStringContainsString('canvas[id*="Chart"]', $js);
    }

    public function test_no_technical_or_broken_component_text_reaches_the_farmer(): void
    {
        $html = $this->page()->getContent();
        $body = $this->body($html);

        foreach (['Chart unavailable', 'library could not be loaded', 'API unavailable', 'Model unavailable', 'Service unavailable', 'Prediction service',
            'Database error', 'N/A', 'Undefined', 'Unknown', 'Not recorded', 'No data available', 'undefined', '>null<'] as $technical) {
            $this->assertStringNotContainsString($technical, $body, $technical);
        }
        $this->assertStringNotContainsString('Chart unavailable', $html);
    }

    public function test_the_old_sections_and_duplicates_are_gone(): void
    {
        $body = $this->body($this->page()->getContent());

        foreach (['My Farm at a Glance', 'More for Your Farm', 'Data sources &amp; last updated', 'Rain Chance This Week', 'Temperature &amp; Humidity',
            'Upcoming Weather', 'Farm Status Summary', 'Recommended Action', 'Average Historical Yield', 'Monthly Rainfall', 'Rain Outlook', 'data-dash-chart'] as $removed) {
            $this->assertStringNotContainsString($removed, $body, $removed);
        }
    }

    public function test_page_follows_the_revised_structure(): void
    {
        $this->rainyDay();
        $this->mockRisk('Low');
        $farmer = $this->farmer();
        $html = $this->page($farmer)->getContent();

        $order = ['data-mfd-hero', 'data-mfd-alerts', 'What You Should Do Today', 'My Farm Today', 'Weather This Week', 'My Farm Status', 'Quick Actions', 'About This Information'];
        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker);
            $this->assertNotFalse($pos, "Missing: $marker");
            $this->assertGreaterThan($last, $pos, "Out of order: $marker");
            $last = $pos;
        }
    }

    private function rainyDay(): void
    {
        $this->fakeWeather([2.0, 30.0, 3.0, 1.0, 1.0, 1.0, 1.0]);
    }

    // ------------------------------------------------------------ hero + weather

    public function test_hero_greets_by_time_of_day_and_shows_real_weather_without_seconds(): void
    {
        $data = $this->data($this->farmer());
        $html = $this->page($this->farmer(['name' => 'Ana Cruz', 'email' => 'ana@example.test']))->getContent();
        $hero = substr($html, strpos($html, 'data-mfd-hero'), 3500);

        $this->assertMatchesRegularExpression('/^Good (morning|afternoon|evening), John Luke$/', $data['greeting']);
        $this->assertStringContainsString('Good ', $hero);
        $this->assertStringContainsString(', Ana', $hero);                                     // two-word name: given name only
        foreach (['Today in Lian, Batangas', 'Moderate Rain • 28.3°C', 'Air moisture', '82%', 'Rain expected today', 'Wind', '8.4 km/h', 'Last updated', 'Weather for Lian, Batangas', 'View Forecast', 'Refresh'] as $text) {
            $this->assertStringContainsString($text, $hero, $text);
        }
        $this->assertDoesNotMatchRegularExpression('/\d{1,2}:\d{2}:\d{2}/', $hero);          // no seconds anywhere in the hero
        $this->assertStringNotContainsString('Your farm weather', $hero);                      // Lian-wide, never presented as measured on the farm
    }

    public function test_the_greeting_follows_the_manila_clock(): void
    {
        foreach ([[8, 'Good morning'], [14, 'Good afternoon'], [20, 'Good evening']] as [$hour, $expected]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-30 '.sprintf('%02d', $hour).':00:00', 'Asia/Manila'));
            $this->assertStringStartsWith($expected, $this->data($this->farmer(['email' => "f{$hour}@example.test"]))['greeting']);
        }
    }

    public function test_a_failed_weather_source_shows_plain_wording_and_hides_live_values(): void
    {
        $this->replaceWeather(Http::response([], 500));
        Cache::flush();

        $data = $this->data($this->farmer());
        $html = $this->page($this->farmer(['email' => 'x@example.test']))->getContent();
        $body = $this->body($html);

        $this->assertSame('error', $data['weather']['state']);
        $this->assertStringContainsString("We could not load today's weather just now.", $body);
        $this->assertStringContainsString('data-mfd-retry', $body);
        $this->assertStringNotContainsString('Weather This Week', $body);
        $this->assertStringNotContainsString('0.0 mm', $body);
        $visible = strip_tags(substr($body, 0, strpos($body, 'Quick Actions')));            // words the farmer can actually read, not URLs or attributes
        foreach (['unavailable', 'API', 'error', 'failed'] as $technical) {
            $this->assertStringNotContainsStringIgnoringCase($technical, $visible, $technical);
        }
    }

    // ------------------------------------------------------------ KPIs

    public function test_expected_yield_appears_only_with_a_valid_prediction_and_states_units_correctly(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->assertNull($this->kpi($this->data($farmer), 'yield'));                          // no valid prediction: no card, no "N/A"

        $this->mockPrediction(4.56, 'farmer_profile');
        $k = $this->kpi($this->data($farmer), 'yield');

        $this->assertSame('4.56', $k['value']);
        $this->assertSame('t/ha', $k['unit']);
        $this->assertStringContainsString(now()->format('F Y').' best guess', $k['context']);
        $this->assertStringContainsString('Best guess — not a real harvest record', $k['note']);
        $this->assertStringContainsString('about 9.12 tons on your 2.00 ha', $k['note']);      // 4.56 t/ha x 2 ha, never "4.56 tons"
        $this->assertStringNotContainsString('4.56 tons', $k['note']);
    }

    public function test_production_in_tons_is_never_shown_from_a_default_farm_area(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.56, 'documented_default');                                     // the model guessed the area, not the farmer

        $k = $this->kpi($this->data($farmer), 'yield');

        $this->assertStringNotContainsString('tons', $k['note']);
        $this->assertNull($this->data($farmer)['prediction']['production']);
    }

    public function test_production_needs_a_real_farm_area_on_the_profile(): void
    {
        $farmer = $this->farmer([], null);                                                     // profile without a farm area
        $this->mockPrediction(4.56, 'farmer_profile');

        $this->assertNull($this->data($farmer)['prediction']['production']);
    }

    public function test_farm_alerts_kpi_is_calm_when_clear_and_counts_real_alerts(): void
    {
        $farmer = $this->farmer();
        $clear = $this->kpi($this->data($farmer), 'alerts');
        $this->assertSame('0', $clear['value']);
        $this->assertSame('✓ No urgent alerts', $clear['context']);

        $this->rainyDay();
        $rain = $this->kpi($this->data($farmer), 'alerts');
        $this->assertSame('1', $rain['value']);
        $this->assertStringContainsString('Heavy rain expected', $rain['note']);
        $this->assertSame('warning', $rain['level']);
    }

    public function test_planting_guidance_follows_the_barangay_risk_rating_and_uses_its_advice_text(): void
    {
        $farmer = $this->farmer();
        $this->assertNull($this->kpi($this->data($farmer), 'guidance'));                       // no rating: the card is left out

        $this->mockRisk('Low', 'Maintain normal irrigation monitoring.');
        $low = $this->kpi($this->data($farmer), 'guidance');
        $this->assertSame('Good conditions', $low['value']);
        $this->assertSame('Maintain normal irrigation monitoring.', $low['context']);
        $this->assertSame('View Advisory', $low['cta']);

        $this->mockRisk('High', 'Delay planting until rainfall stabilizes.');
        $high = $this->kpi($this->data($farmer), 'guidance');
        $this->assertSame('Be careful', $high['value']);
    }

    public function test_next_farm_activity_is_a_real_schedule_or_an_actionable_prompt(): void
    {
        $farmer = $this->farmer();
        $none = $this->kpi($this->data($farmer), 'activity');
        $this->assertSame('Set your planting schedule', $none['value']);
        $this->assertSame('Add Schedule', $none['cta']);
        $this->assertStringContainsString(route('farmer.planting-schedules.create'), $none['href']);

        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => now('Asia/Manila')->addDays(5)->toDateString(), 'scheduled_harvest_date' => now('Asia/Manila')->addDays(125)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2,
        ]);
        Cache::flush();
        $next = $this->kpi($this->data($farmer), 'activity');

        $this->assertSame('Planting in 5 days', $next['value']);
        $this->assertSame(now('Asia/Manila')->addDays(5)->format('l, F j'), $next['context']);
    }

    public function test_no_average_historical_yield_or_duplicate_weather_kpi(): void
    {
        $keys = array_column($this->data($this->farmer())['kpis'], 'key');

        $this->assertNotContains('historical', $keys);
        $this->assertNotContains('weather', $keys);
        $this->assertLessThanOrEqual(4, count($keys));
    }

    // ------------------------------------------------------------ what you should do today

    public function test_the_action_comes_from_defined_rules_and_offers_climora_and_the_advisory(): void
    {
        $farmer = $this->farmer();
        $this->fakeWeather([4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], humidity: 90);
        PlantingAdvisory::factory()->create(['title' => 'Heavy Rain Farming Reminder', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDay()]);

        $html = $this->page($farmer)->getContent();
        $section = substr($html, strpos($html, 'data-mfd-action'), 3000);

        $this->assertStringContainsString('Very moist air', $section);
        $this->assertStringContainsString('The air is very moist, so rice diseases can spread. Look at your plants often for spots or mold.', $section);   // the weather rule's advice, in plain words
        $this->assertStringContainsString('Heavy Rain Farming Reminder', $section);
        $this->assertStringContainsString('View Full Advisory', $section);
        $this->assertStringContainsString('Ask Climora AI', $section);
        $this->assertStringContainsString('data-ai-prefill=', $section);
    }

    // ------------------------------------------------------------ forecast

    public function test_the_outlook_shows_seven_cards_and_flags_only_real_risks(): void
    {
        $this->rainyDay();

        $html = $this->page($this->farmer())->getContent();
        $section = substr($html, strpos($html, 'data-mfd-forecast'), strpos($html, 'Forecast for Lian, Batangas.') - strpos($html, 'data-mfd-forecast'));

        $this->assertSame(7, substr_count($section, '<article class="mfd-day'));
        $this->assertSame(1, substr_count($section, 'mfd-day is-flagged'));
        $this->assertStringContainsString('HEAVY RAIN', $section);
        $this->assertStringContainsString('% chance of rain', $section);
        $this->assertStringContainsString('30 mm expected', $section);
        $this->assertStringNotContainsStringIgnoringCase('drizzle', $html);
    }

    // ------------------------------------------------------------ farm status

    public function test_planting_schedule_prompt_and_only_real_farm_fields_are_shown(): void
    {
        $farmer = $this->farmer([], 2.0);
        $html = $this->page($farmer)->getContent();
        $status = substr($html, strpos($html, 'data-mfd-status'), 3500);

        $this->assertStringContainsString('No planting schedule yet.', $status);
        $this->assertStringContainsString('Set Planting Schedule', $status);
        foreach (['Farm Area', '2.00 ha', 'Barangay', 'Binubusan', 'Farm Type', 'Irrigated'] as $text) {
            $this->assertStringContainsString($text, $status, $text);
        }
        $this->assertStringNotContainsString('Crop Stage', $status);                           // no schedule: no invented stage
        $this->assertStringNotContainsString('Expected Harvest', $status);
    }

    public function test_farm_fields_without_data_are_left_out(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'Ben Farmer', 'barangay' => null]);   // no profile at all

        $this->assertSame([], $this->data($farmer)['farm']);
        $this->assertStringNotContainsString('Farm / Crop Information', $this->body($this->page($farmer)->getContent()));
    }

    public function test_farm_risk_status_appears_only_with_a_rating_and_high_risk_is_a_visible_alert(): void
    {
        $farmer = $this->farmer();
        $this->assertStringNotContainsString('Farm Risk in Your Barangay', $this->body($this->page($farmer)->getContent()));

        $this->mockRisk('High', 'Delay planting until rainfall stabilizes.');
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame('Farm risk in your barangay is High', collect($data['alerts'])->firstWhere('icon', 'map')['title']);
        $this->assertStringContainsString('Farm Risk in Your Barangay', $html);
        $this->assertStringContainsString('A planning guide only, not an official hazard warning.', $html);
    }

    // ------------------------------------------------------------ advisories + notifications

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
        $this->assertStringNotContainsString('Latest Farm Advisories', $this->body($this->page($this->farmer(['email' => 'q@example.test']))->getContent()));
    }

    public function test_unread_notifications_drive_the_bell_and_update_when_one_is_read(): void
    {
        $farmer = $this->farmer();
        $first = Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Announcement']);
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => true, 'type' => 'Announcement']);

        $html = $this->page($farmer)->getContent();
        $this->assertStringContainsString('aria-label="Notifications, 2 unread"', $html);
        $this->assertSame(2, $this->data($farmer)['unread']);

        $this->actingAs($farmer)->patch(route('notifications.mark-read', $first->id))->assertRedirect();
        $after = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Notifications, 1 unread"', $after);      // no stale count after reading
    }

    public function test_an_open_typhoon_check_in_is_a_critical_alert_until_the_farmer_answers(): void
    {
        $this->mock(TyphoonSafetyService::class, function ($mock): void {
            $mock->shouldReceive('activeEvent')->andReturn(['key' => 'typhoon-test']);
        });

        $alert = collect($this->data($this->farmer())['alerts'])->firstWhere('icon', 'storm');

        $this->assertSame('critical', $alert['severity']);
        $this->assertSame('Typhoon safety check-in is open', $alert['title']);
    }

    // ------------------------------------------------------------ analytics

    public function test_my_yield_outlook_compares_lian_history_with_the_farmers_estimate(): void
    {
        $farmer = $this->farmer([], 2.0);
        $rows = [];
        foreach ([2023, 2024] as $year) {
            foreach (range(1, 12) as $m) {
                $rows[] = ['year' => $year, 'month' => $m, 'yield' => 3.82, 'split' => null];
            }
        }
        $this->yieldRows($rows);
        $this->assertNull($this->data($farmer)['outlook']);                                     // no prediction: no comparison

        $this->mockPrediction(4.56, 'farmer_profile');
        $o = $this->data($farmer)['outlook'];
        $html = $this->page($farmer)->getContent();

        $this->assertSame(3.82, $o['historical']);
        $this->assertSame(4.56, $o['predicted']);
        $this->assertSame(0.74, $o['difference']);
        $this->assertStringContainsString('Lian average for '.now()->format('F'), $html);         // same calendar month as the estimate
        $this->assertStringContainsString('only for comparing', $html);
        $this->assertStringContainsString('Your expected harvest', $html);
        $this->assertStringContainsString('+0.74 t/ha', $html);
        $this->assertStringContainsString('This is a best guess and may change with the weather and your farm.', $html);
        $this->assertStringContainsString('not your own past harvests', $html);                // municipal history is never passed off as personal
    }

    public function test_the_outlook_compares_the_same_calendar_month_and_falls_back_to_all_months(): void
    {
        $farmer = $this->farmer([], 2.0);
        $target = (int) now()->format('n');
        $other = $target === 1 ? 2 : 1;
        $this->yieldRows([
            ['year' => 2023, 'month' => $target, 'yield' => 5.0, 'split' => null],
            ['year' => 2024, 'month' => $target, 'yield' => 4.0, 'split' => null],
            ['year' => 2024, 'month' => $other, 'yield' => 1.0, 'split' => null],           // a different month must not drag the average
        ]);
        $this->mockPrediction(4.56, 'farmer_profile');

        $o = $this->data($farmer)['outlook'];

        $this->assertTrue($o['same_month']);
        $this->assertSame(4.5, $o['historical']);
        $this->assertSame(2, $o['months']);

        $this->yieldRows([['year' => 2024, 'month' => $other, 'yield' => 3.0, 'split' => null]]);    // no record for the target month
        $o = $this->data($farmer)['outlook'];

        $this->assertFalse($o['same_month']);
        $this->assertSame(3.0, $o['historical']);
        $this->assertStringContainsString('Lian average, all months', $this->page($farmer)->getContent());
    }

    // ------------------------------------------------------------ Phase 1: reliability

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
        $key = \App\Services\Analytics\FarmerDashboardService::predictionCacheKey($farmer->id);

        $this->page($farmer);
        $this->assertSame(1, $calls);
        $this->assertTrue(Cache::has($key));

        $farmer->farmerProfile->update(['farm_area' => 3.5]);
        $this->assertFalse(Cache::has($key), 'editing the farm area must drop the cached estimate');

        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();
        $this->assertTrue(Cache::has($key));

        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => now()->addDays(5)->toDateString(), 'scheduled_harvest_date' => now()->addDays(125)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2,
        ]);
        $this->assertFalse(Cache::has($key), 'a new planting schedule must drop the cached estimate');
    }

    public function test_only_notifications_marked_as_weather_show_as_weather_alerts(): void
    {
        $farmer = $this->farmer();
        Notification::factory()->create(['user_id' => $farmer->id, 'type' => 'Warning', 'is_read' => false, 'title' => 'Fertilizer pickup schedule', 'message' => 'Bring a heat-sealed bag.', 'category' => null]);
        $this->assertSame([], $this->data($farmer)['alerts']);                                  // "heat" in the wording is not enough

        Notification::factory()->create(['user_id' => $farmer->id, 'type' => 'Warning', 'is_read' => false, 'title' => 'Storm signal raised', 'category' => Notification::CATEGORY_WEATHER]);
        $alerts = $this->data($farmer)['alerts'];

        $this->assertCount(1, $alerts);
        $this->assertSame('Storm signal raised', $alerts[0]['title']);
    }

    public function test_the_farmer_is_told_when_parts_of_the_dashboard_could_not_load(): void
    {
        $farmer = $this->farmer([], 2.0);                                                       // setUp: no prediction, no risk data
        $data = $this->data($farmer);

        $this->assertContains('expected rice yield', $data['unavailable']);

        $html = $this->page($farmer)->getContent();
        $this->assertStringContainsString("Some information isn't available right now", $html);
        $this->assertStringContainsString('data-mfd-refresh-error', $html);                     // shown by the script if a refresh fails
    }

    public function test_no_notice_when_everything_loaded(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->mockPrediction(4.56, 'farmer_profile');
        $this->mockRisk('Low');

        $data = $this->data($farmer);

        $this->assertNotContains('expected rice yield', $data['unavailable']);
        $this->assertNotContains('farm risk for your barangay', $data['unavailable']);
    }

    public function test_the_strong_wind_threshold_is_not_below_the_wind_a_forecast_day_keeps(): void
    {
        // A forecast day only keeps wind at or above RELEVANT_WIND_KMH (20); the alert needs STRONG_WIND_KMH, so it must be higher.
        $this->assertGreaterThan(20.0, \App\Services\Analytics\FarmerDashboardService::STRONG_WIND_KMH);
    }

    public function test_the_rainfall_chart_is_optional_and_never_shows_an_unavailable_box(): void
    {
        $farmer = $this->farmer();
        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        if ($data['rainfall'] === null) {
            $this->assertStringNotContainsString('farmerRainfallTrendChart', $html);
        } else {
            $this->assertStringContainsString('id="farmerRainfallTrendChart"', $html);
            $this->assertStringContainsString('Rainfall (mm)', file_get_contents(resource_path('js/farmer-dashboard.js')));
            $this->assertStringContainsString('data-chart-info-style="icon"', $html);
        }
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));
        $this->assertStringContainsString('if (card) card.hidden = true', $js);                 // a chart that cannot draw removes itself quietly
        $this->assertStringNotContainsString('Rain Chance This Week', $html);
    }

    // ------------------------------------------------------------ shell: header, sidebar, fonts, responsive

    public function test_the_header_is_simple_for_farmers(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $nav = substr($html, strpos($html, '<nav class="topbar'), 3500);

        $this->assertStringContainsString('John Luke Reynon', $nav);
        $this->assertStringContainsString('Farmer • Binubusan', $nav);
        $this->assertStringNotContainsString('Signed in as', $nav);
        $this->assertStringNotContainsString('topbar-role-badge', $nav);
        $this->assertStringContainsString(route('profile.edit'), $nav);
        $this->assertStringContainsString('Logout', $nav);
        $this->assertStringContainsString('aria-label="Notifications', $nav);
    }

    public function test_other_roles_keep_their_original_header(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Signed in as', $html);
        $this->assertStringContainsString('topbar-role-badge', $html);
    }

    public function test_the_farmer_sidebar_has_a_my_farm_group_and_keeps_all_links(): void
    {
        $html = $this->page($this->farmer())->getContent();

        $this->assertStringContainsString('>My Farm<', $html);
        $this->assertStringNotContainsString('>More Tools<', $html);
        foreach (['Dashboard', 'Advisories', 'Calendar', 'Community', 'Messages', 'Notifications', 'Climate Records', 'Predictions', 'Rice Production'] as $link) {
            $this->assertStringContainsString($link, $html, $link);
        }
    }

    public function test_one_font_family_and_a_readable_type_scale(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("font-family: Inter, 'Segoe UI', Roboto, Arial, sans-serif", $css);
        $this->assertStringNotContainsString('DM Serif', $css);
        $this->assertStringNotContainsString('DM Mono', $css);
        $this->assertStringContainsString('body[data-user-role="Farmer"] *', $appCss);         // sidebar, header and titles too
        $this->assertMatchesRegularExpression('/\.mfd-hero h1 \{[^}]*font-size: 1\.875rem; font-weight: 700; line-height: 1\.2/', $css);          // greeting 30px
        $this->assertMatchesRegularExpression('/\.mfd-temp \{[^}]*font-size: 2\.125rem; font-weight: 700/', $css);                                // temperature 34px
        $this->assertMatchesRegularExpression('/\.mfd-kpi-value \{[^}]*font-size: 2rem; font-weight: 700/', $css);                                // KPI value 32px
        $this->assertMatchesRegularExpression('/\.mfd-card-head h2 \{[^}]*font-size: 1\.125rem; font-weight: 600/', $css);                        // section title 18px
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.68, (float) $rem);
        }
    }

    public function test_layout_is_responsive_and_the_reading_order_is_the_page_order(): void
    {
        $css = file_get_contents(resource_path('css/farmer-dashboard.css'));

        foreach (['.mfd-kpis { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }',
            'grid-template-columns: repeat(var(--mfd-kpi-count, 4), minmax(0, 1fr))', 'overflow-x: auto'] as $rule) {
            $this->assertStringContainsString($rule, $css, $rule);
        }
        // Screen readers and keyboards follow the page order, so it is never rearranged by CSS `order`.
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])order:\s*\d/', $css);

        $html = $this->page($this->farmer())->getContent();
        $positions = array_map(fn ($slot) => strpos($html, 'data-slot="'.$slot.'"'), ['hero', 'alerts', 'action', 'kpis', 'forecast', 'status']);
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), 'sections are in reading order: hero, alerts, action, then the numbers');
    }

    public function test_the_page_has_no_inline_styles_or_scripts_for_the_dashboard(): void
    {
        $html = $this->page($this->farmer())->getContent();
        $body = $this->body($html);

        $this->assertStringNotContainsString('.mfd-hero {', $html);                             // the stylesheet is a file, not inline
        $this->assertStringNotContainsString('async function refresh', $html);                  // so is the script
        $this->assertFileExists(resource_path('css/farmer-dashboard.css'));
        $this->assertStringContainsString("import { initFarmerDashboard } from './farmer-dashboard';", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString("@import './farmer-dashboard.css';", file_get_contents(resource_path('css/app.css')));
        $this->assertStringContainsString('data-farmer-dashboard', $body);
    }

    public function test_accessibility_hooks_are_present(): void
    {
        $farmer = $this->farmer();
        $this->fakeWeather([2.0, 30.0, 3.0, 1.0, 1.0, 1.0, 1.0]);
        $html = $this->page($farmer)->getContent();

        $this->assertStringContainsString('role="status" aria-live="polite" data-mfd-live', $html);        // refresh result is announced
        $this->assertMatchesRegularExpression('/data-slot="alerts"[^>]*aria-live="polite"/', $html);       // a new alert is announced
        $this->assertMatchesRegularExpression('/class="mfd-days"[^>]*tabindex="0"[^>]*aria-label="Daily forecast/', $html);   // the sideways strip can be scrolled by keyboard
        $this->assertStringContainsString('id="farmerForecastChart"', $html);                     // the 7-day rain and temperature chart
    }

    public function test_the_full_width_data_sources_bar_is_replaced_by_a_small_data_information_link(): void
    {
        $body = $this->body($this->page($this->farmer())->getContent());

        $this->assertStringContainsString('ⓘ About This Information', $body);
        $this->assertStringContainsString('It is not measured on your own farm.', $body);
        $this->assertStringContainsString('It is not a real harvest record.', $body);
    }

    public function test_the_live_endpoint_rebuilds_each_section(): void
    {
        $json = $this->actingAs($this->farmer())->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertEqualsCanonicalizing(['hero', 'notice', 'alerts', 'action', 'plan', 'kpis', 'forecast', 'status', 'setup', 'advisories'], array_keys($json['slots']));
        $this->assertStringContainsString('Today in Lian, Batangas', $json['slots']['hero']);
        $this->assertStringContainsString('My Farm Today', $json['slots']['kpis']);
        $this->assertSame('', trim($json['slots']['advisories']));                             // nothing to show: the section disappears
        $this->assertArrayHasKey('updated', $json);
    }

    // ------------------------------------------------------------ Phase 3: crop plan, own harvests, setup prompts

    private function openSchedule(User $farmer, array $extra = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($extra + [
            'user_id' => $farmer->id, 'planting_date' => now('Asia/Manila')->subDays(5)->toDateString(), 'scheduled_harvest_date' => now('Asia/Manila')->addDays(110)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2,
        ]);
    }

    private function lianYields(float $yield = 3.8): void
    {
        $this->yieldRows(array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => $yield, 'split' => null], range(1, 12)));
    }

    public function test_the_crop_plan_shows_a_calendar_for_every_open_plot(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);
        $this->openSchedule($farmer, ['planting_date' => now()->addDays(20)->toDateString(), 'scheduled_harvest_date' => now()->addDays(130)->toDateString()]);
        $harvested = $this->openSchedule($farmer, ['progress_status' => 'Harvested']);       // a finished plot is not part of the plan

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertCount(2, $data['plan']['plots']);
        $this->assertStringContainsString('My Crop Plan', $html);
        $this->assertStringContainsString('Fertilizer at planting', $html);
        $this->assertStringContainsString('Plot 1', $html);
        $this->assertStringContainsString('Plot 2', $html);
        $this->assertStringContainsString('aria-current="step"', $html);                       // the step under way is announced, not just coloured
        $this->assertStringContainsString('Follow the Municipal Agriculture Office advisories first', $html);
        $this->assertNotNull($harvested->id);
    }

    public function test_there_is_no_crop_plan_without_an_open_schedule(): void
    {
        $farmer = $this->farmer([], 2.0);

        $this->assertNull($this->data($farmer)['plan']);
        $this->assertStringNotContainsString('data-mfd-plan', $this->page($farmer)->getContent());
    }

    public function test_heavy_rain_ahead_becomes_advice_for_the_current_stage(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);                                                          // planted 5 days ago: the fertilizer-at-planting window
        $this->rainyDay();                                                                     // 30 mm forecast tomorrow

        $advice = $this->data($farmer)['plan']['advice'];

        $this->assertNotEmpty($advice);
        $this->assertStringContainsString('Hold off on the fertilizer at planting', $advice[0]['text']);
        $this->assertStringContainsString('Hold off on the fertilizer at planting', $this->page($farmer)->getContent());
    }

    public function test_the_farmers_own_harvests_are_compared_with_their_estimate(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->lianYields();
        $this->mockPrediction(4.56, 'farmer_profile');
        $this->assertNull($this->data($farmer)['outlook']['own']);                             // nothing recorded yet: no personal comparison

        $this->openSchedule($farmer, ['progress_status' => 'Harvested', 'actual_yield' => 3.0, 'harvested_on' => now()->subMonths(6)->toDateString()]);
        $this->openSchedule($farmer, ['progress_status' => 'Harvested', 'actual_yield' => 4.0, 'harvested_on' => now()->subMonths(1)->toDateString()]);
        $own = $this->data($farmer)['outlook']['own'];

        $this->assertSame(2, $own['count']);
        $this->assertSame(3.5, $own['average']);
        $this->assertSame(4.0, $own['latest']);
        $this->assertSame(1.06, $own['difference']);                                            // 4.56 estimate - 3.50 own average

        $html = $this->page($farmer)->getContent();
        $this->assertStringContainsString('Your own past harvests', $html);
        $this->assertStringContainsString('2 recorded', $html);
        $this->assertStringContainsString('compared with your own average harvest', $html);
    }

    public function test_setup_prompts_list_only_what_is_missing_and_say_what_it_unlocks(): void
    {
        $farmer = $this->farmer([], null);                                                     // profile with a barangay and type, but no farm area
        $keys = array_column($this->data($farmer)['setup']['items'], 'key');

        $this->assertContains('farm_area', $keys);
        $this->assertContains('schedule', $keys);
        $this->assertNotContains('barangay', $keys);
        $this->assertNotContains('farm_type', $keys);

        $html = $this->page($farmer)->getContent();
        $this->assertStringContainsString('Finish Setting Up Your Farm', $html);
        $this->assertStringContainsString('to see how many tons your farm may produce', $html);

        $complete = $this->farmer(['email' => 'complete@example.test'], 2.0);
        $this->openSchedule($complete);
        $this->assertNotContains('farm_area', array_column($this->data($complete)['setup']['items'], 'key'));
        $this->assertNotContains('schedule', array_column($this->data($complete)['setup']['items'], 'key'));
    }

    public function test_a_harvested_plot_without_a_yield_asks_the_farmer_to_record_it(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer, ['progress_status' => 'Harvested']);

        $item = collect($this->data($farmer)['setup']['items'])->firstWhere('key', 'harvest');

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

    public function test_the_script_keeps_the_last_information_and_respects_low_data_mode(): void
    {
        $js = file_get_contents(resource_path('js/farmer-dashboard.js'));

        $this->assertStringContainsString("window.addEventListener('offline'", $js);           // offline: say so and keep what is on the page
        $this->assertStringContainsString("window.addEventListener('online'", $js);            // back online: refresh
        $this->assertStringContainsString('You are seeing the information from', $js);         // and say when that information is from
        $this->assertStringContainsString('navigator.connection', $js);                        // Data Saver / 2G: skip charts and background refresh
        $this->assertStringContainsString('data-mfd-refresh-error-text', $this->page($this->farmer())->getContent());
    }

    // ------------------------------------------------------------ Phase 4: failure paths, empty states, cache, sample data

    /** What a farmer must never see on the page, whatever failed behind it. */
    private function assertNoTechnicalLeak(string $html, array $secrets = []): void
    {
        $body = strip_tags(preg_replace(['#<script\b.*?</script>#is', '#<style\b.*?</style>#is'], '', $this->body($html)));
        foreach (array_merge(['Exception', 'Stack trace', 'Undefined', 'SQLSTATE', 'NaN', 'undefined', 'null', 'N/A'], $secrets) as $needle) {
            $this->assertStringNotContainsString($needle, $body, "The farmer would see: $needle");
        }
    }

    public function test_a_new_farmer_with_nothing_set_up_sees_a_helpful_page_not_an_empty_or_broken_one(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'Ana Cruz', 'barangay' => null]);       // no profile, no schedule, no notifications, no barangay

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertNull($data['plan']);
        $this->assertNull($data['risk']);
        $this->assertNull($data['prediction']);
        $this->assertSame([], $data['advisories']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame(0, $data['unread']);
        $this->assertGreaterThanOrEqual(4, count($data['setup']['items']));                        // barangay, type, area, plan, outline: plenty to guide them
        $this->assertStringContainsString('Finish Setting Up Your Farm', $html);
        $this->assertStringContainsString('Set your planting schedule', $html);                    // the schedule KPI becomes a prompt, not a blank
        $this->assertStringContainsString('No urgent alerts', $html);
        $this->assertStringNotContainsString('farm risk for your barangay', $html);               // no barangay on file is a profile gap, not an outage
        $this->assertNoTechnicalLeak($html);
    }

    public function test_when_every_source_fails_the_page_still_opens_and_says_what_is_missing(): void
    {
        $this->replaceWeather(Http::response([], 500));
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new \RuntimeException('secret-model-failure')));
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andThrow(new \RuntimeException('secret-risk-failure')));
        $this->yieldRows([]);
        $farmer = $this->farmer([], 2.0);
        $this->openSchedule($farmer);

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertSame('error', $data['weather']['state']);
        $this->assertNull($data['prediction']);
        $this->assertNull($data['risk']);
        $this->assertNull($data['outlook']);
        $this->assertEqualsCanonicalizing(['live weather', 'farm risk for your barangay', 'expected rice yield'], array_diff($data['unavailable'], ['the rainfall chart']));
        $this->assertStringContainsString("Some information isn't available right now", $html);
        $this->assertStringContainsString('We could not load today', $html);                       // plain wording and a way to retry
        $this->assertStringContainsString('data-mfd-retry', $html);
        $this->assertNotNull($data['plan']);                                                       // the crop calendar needs no live source, so it stays
        $this->assertSame([], $data['plan']['advice']);                                            // and gives no weather advice without a forecast
        $this->assertNoTechnicalLeak($html, ['secret-model-failure', 'secret-risk-failure']);
    }

    public function test_the_live_refresh_still_answers_when_the_weather_source_is_down(): void
    {
        $this->replaceWeather(Http::response([], 500));
        $farmer = $this->farmer([], 2.0);

        $json = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertStringContainsString('We could not load', $json['slots']['hero']);
        $this->assertStringContainsString('data-mfd-retry', $json['slots']['hero']);
        $this->assertSame('', trim($json['slots']['forecast']));                                   // no invented forecast
        $this->assertStringContainsString("isn't available right now", $json['slots']['notice']);
    }

    public function test_a_prediction_failure_removes_only_the_yield_pieces(): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andThrow(new \RuntimeException('secret-model-failure')));
        $this->lianYields();
        $farmer = $this->farmer([], 2.0);

        $data = $this->data($farmer);
        $html = $this->page($farmer)->getContent();

        $this->assertNull($this->kpi($data, 'yield'));
        $this->assertNull($data['outlook']);
        $this->assertStringNotContainsString('My Expected Harvest', $html);
        $this->assertNotNull($this->kpi($data, 'alerts'));                                         // the rest of the row is untouched
        $this->assertNotNull($this->kpi($data, 'activity'));
        $this->assertNoTechnicalLeak($html, ['secret-model-failure']);
    }

    public function test_no_yield_history_file_means_no_outlook_and_no_crash(): void
    {
        $this->mockPrediction(4.56, 'farmer_profile');
        $this->yieldRows([]);
        $farmer = $this->farmer([], 2.0);

        $this->assertNull($this->data($farmer)['outlook']);
        $this->assertNotNull($this->kpi($this->data($farmer), 'yield'));                           // the estimate itself does not need the history
        $this->assertStringNotContainsString('My Expected Harvest', $this->page($farmer)->getContent());
    }

    public function test_a_missing_schedule_table_does_not_break_the_page(): void
    {
        $farmer = $this->farmer([], 2.0);
        \Illuminate\Support\Facades\Schema::drop('farmer_planting_schedules');
        Cache::flush();

        $data = $this->data($farmer);

        $this->assertNull($data['plan']);
        $this->assertNull($data['schedule']);
        $this->assertNoTechnicalLeak($this->page($farmer)->getContent());
    }

    public function test_an_unreadable_notification_source_does_not_take_the_dashboard_down(): void
    {
        $farmer = $this->farmer([], 2.0);
        \Illuminate\Support\Facades\Schema::drop('notifications');
        Cache::flush();

        $data = app(\App\Services\Analytics\FarmerDashboardService::class)->payload($farmer);   // the service itself, without the page layout

        $this->assertSame(0, $data['unread']);
        $this->assertSame([], $data['alerts']);
        $this->assertNotNull($data['weather']);
    }

    public function test_a_planting_only_farmer_has_a_plan_before_anything_else_is_known(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);                          // no profile at all
        $this->openSchedule($farmer);

        $data = $this->data($farmer);

        $this->assertNotNull($data['plan']);
        $this->assertContains('farm_type', array_column($data['setup']['items'], 'key'));
        $this->assertNotContains('schedule', array_column($data['setup']['items'], 'key'));
    }

    // ---- the cache is cleared when the farmer's inputs change

    public function test_the_cached_estimate_is_dropped_for_every_kind_of_profile_or_schedule_change(): void
    {
        $farmer = $this->farmer([], 2.0);
        $other = $this->farmer(['email' => 'other@example.test'], 1.0);
        $key = \App\Services\Analytics\FarmerDashboardService::predictionCacheKey($farmer->id);
        $otherKey = \App\Services\Analytics\FarmerDashboardService::predictionCacheKey($other->id);
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

    public function test_a_new_schedule_shows_on_the_dashboard_at_once_not_after_the_cache_expires(): void
    {
        $farmer = $this->farmer([], 2.0);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertDontSee('data-mfd-plan', false);     // page is now cached

        $this->openSchedule($farmer);                                                              // no Cache::flush(): the model event must do it
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('My Crop Plan');

        $farmer->farmerProfile->update(['farm_area' => 5.5]);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('5.50 ha');
    }

    // ---- sample rows never look real

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

        $data = $this->data($farmer);

        $this->assertSame(1, $data['unread']);                                                      // the dashboard bell
        $this->assertSame([], $data['alerts']);                                                     // a seeded "Warning" is not an alert
        $this->assertSame(1, Cache::get('sidebar:unread-notifications:'.$farmer->id));              // the sidebar and top bar agree with it
        $list = $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Seed distribution on Friday', $list);
        $this->assertStringNotContainsString('Adipisci non sunt', $list);
        $this->assertStringNotContainsString('Nulla magni nihil', $list);
    }

    public function test_seeded_placeholder_advisories_never_reach_the_farmer(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        $farmer = $this->farmer([], 2.0);
        $seeded = PlantingAdvisory::factory()->create(['title' => 'Molestiae sit voluptatem consequatur dolorem nihil est.', 'content' => 'Quia voluptas aut nihil ipsum dolorem eveniet distinctio.', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDay()]);
        $seeded->forceFill(['created_at' => '2026-07-15 13:56:13', 'updated_at' => '2026-07-15 13:56:13'])->saveQuietly();
        PlantingAdvisory::factory()->create(['title' => 'Heavy Rain Farming Reminder', 'content' => 'Check field drainage before the rain.', 'target_barangay' => null, 'status' => PlantingAdvisory::STATUS_PUBLISHED, 'valid_from' => now()->subDay()]);
        Cache::flush();

        $titles = array_column($this->data($farmer)['advisories'], 'title');

        $this->assertSame(['Heavy Rain Farming Reminder'], $titles);
    }

    public function test_a_cheerful_planting_line_is_never_shown_next_to_a_warning_rating(): void
    {
        $farmer = $this->farmer();

        $this->mockRisk('Moderate', 'Ideal planting period.');                                  // the rain-only rule says "ideal"; the rating says watch out
        $moderate = $this->kpi($this->data($farmer), 'guidance');
        $this->assertSame('Go with care', $moderate['value']);
        $this->assertSame('Some conditions in your barangay need watching.', $moderate['context']);
        $this->assertStringNotContainsString('Ideal', $moderate['context']);

        $this->mockRisk('Low', 'Ideal planting period.');                                       // agreeing rating and rule: the rule's line is kept
        $this->assertSame('Ideal planting period.', $this->kpi($this->data($farmer), 'guidance')['context']);

        $this->mockRisk('Moderate', 'Plant with caution and monitor field drainage.');          // a careful line next to a careful rating is kept too
        $this->assertSame('Plant with caution and monitor field drainage.', $this->kpi($this->data($farmer), 'guidance')['context']);

        $this->mockRisk('High', 'Ideal planting period.');
        $alert = collect($this->data($farmer)['alerts'])->firstWhere('icon', 'map');
        $this->assertStringNotContainsString('Ideal', $alert['message']);                       // the alert for a high rating does not cheer either
    }
}
