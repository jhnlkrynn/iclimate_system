<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Analytics\DashboardOverviewService;
use App\Services\Analytics\MaoDashboardService;
use App\Services\TyphoonSafetyService;
use App\Support\DashboardData;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // A migration seeds one rating per barangay; start each test with none so it controls exactly what exists.
        HeatmapArea::query()->delete();
        config([
            'services.weather.latitude' => 14.033, 'services.weather.longitude' => 120.650, 'services.weather.timezone' => 'Asia/Manila',
            'services.weather.cache_minutes' => 5, 'services.weather.forecast_days' => 7, 'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
        ]);
        $this->fakeWeather();
    }

    /** @param  array<int, float>  $rain  rainfall per forecast day (index 0 = today) */
    private function weatherPayload(array $rain, float $currentRain = 0.2, float $temperature = 28.3): array
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = count($rain);

        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $today->addHours(15)->format('Y-m-d\TH:i'), 'temperature_2m' => $temperature, 'relative_humidity_2m' => 82,
                'apparent_temperature' => 33.7, 'precipitation' => $currentRain, 'rain' => $currentRain, 'showers' => 0, 'weather_code' => 53,
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
            'hourly' => ['time' => [$today->format('Y-m-d').'T15:00'], 'temperature_2m' => [$temperature], 'relative_humidity_2m' => [82],
                'precipitation_probability' => [60], 'precipitation' => [$currentRain], 'rain' => [$currentRain]],
        ];
    }

    private function fakeWeather(array $rain = [4.0, 6.0, 3.0, 2.0, 1.0, 5.0, 2.0], float $currentRain = 0.2): void
    {
        $this->replaceWeather(Http::response($this->weatherPayload($rain, $currentRain), 200));
    }

    /** Http::fake stubs match in the order registered, so a new stub must replace the old one, not sit behind it. */
    private function replaceWeather($response): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.open-meteo.com/*' => $response]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'name' => 'MAO Officer']);
    }

    private function page(?User $as = null)
    {
        Cache::flush();
        // Pages never call the provider; the scheduled refresh (iclimate:refresh-weather-cache) stores what they show.
        app(\App\Services\Weather\FarmerDashboardWeatherService::class)->current(force: true);
        app(\App\Services\WeatherApiService::class)->forecast(refresh: true);

        return $this->actingAs($as ?? $this->mao())->get(route('mao.dashboard'))->assertOk();
    }

    /**
     * MaoDashboardService's data for a user. Since 2026-10 the dashboard page is the short MaoHomeService screen (see
     * MaoDashboardHomeTest); this service still answers the live endpoint (mao.dashboard.live), so its rules stay tested.
     */
    private function data(?User $as = null): array
    {
        $service = app(\App\Services\Analytics\MaoDashboardService::class);

        return $service->forUser($service->payload(), $as ?? $this->mao());
    }

    /** The overview body only: from the first KPI to the link to the operations page. */
    private function body(string $html): string
    {
        $start = strpos($html, 'data-mao-kpis');

        return substr($html, $start, strpos($html, 'More MAO tools') - $start);
    }

    private function kpi(array $data, string $key): ?array
    {
        return collect($data['kpis'])->firstWhere('key', $key);
    }

    private function rating(string $barangay, string $level, float $score = 0.5, ?string $reason = null): HeatmapArea
    {
        return HeatmapArea::factory()->create([
            'barangay' => $barangay, 'risk_level' => $level, 'risk_score' => $score, 'main_risk_reason' => $reason, 'risk_computed_at' => now(),
        ]);
    }

    /** Replaces the yield history so the comparison rules can be tested with known numbers. */
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

    // ------------------------------------------------------------ access + structure

    public function test_removed_and_empty_content_is_not_on_the_overview(): void
    {
        $html = $this->page()->getContent();
        $body = $this->body($html);

        foreach (['Office Overview', 'MAO Operational Workflow', 'Predictions by Month', 'Average Yield by Month', 'Not recorded', 'N/A',
            'Season:', 'Planting &amp; Expected Yield', 'Hectares Planted', 'Farmer Weather Feedback', 'Not enough farmer reports', 'dashboard-tab',
            'Climate Records', 'Learn More', 'Barangays to Watch', 'Recent MAO Activity', 'MAO Attention Center'] as $removed) {
            $this->assertStringNotContainsString($removed, $body, $removed);
        }
        foreach (['Heat Map &amp; Forecast', 'Monitoring &amp; Production', 'Field Reports', 'Publishing &amp; Activity'] as $tab) {
            $this->assertStringNotContainsString($tab, $body, $tab);
        }
    }

    // ------------------------------------------------------------ KPIs

    public function test_registered_farmers_kpi_uses_real_profiles_and_organizations(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        FarmerProfile::factory()->count(3)->create();

        $k = $this->kpi($this->data(), 'farmers');

        $this->assertSame('3', $k['value']);
        $this->assertStringContainsString('Farmer Organizations', $k['context']);
        $this->assertStringContainsString(number_format(\App\Models\FarmerOrganization::query()->count()), $k['context']);
    }

    public function test_farmers_kpi_is_hidden_when_there_are_no_profiles(): void
    {
        $this->assertNull($this->kpi($this->data(), 'farmers'));
    }

    public function test_the_summary_has_at_most_four_numbers(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        FarmerProfile::factory()->create();
        $this->rating('San Diego', 'Low', 0.2);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PUBLISHED]);

        $this->assertSame(['farmers', 'yield', 'barangays', 'advisories'], array_column($this->data()['kpis'], 'key'));
    }

    public function test_a_failed_weather_source_hides_live_values_instead_of_showing_zeros(): void
    {
        $this->replaceWeather(Http::response([], 500));
        Cache::flush();

        $data = $this->data();
        $html = $this->page()->getContent();

        $this->assertSame('error', $data['weather']['state']);
        $this->assertNull($this->kpi($data, 'weather'));
        // Nothing saved and the source down: a calm "Walang datos pa", never an error or a Try again (Phase 1 sign-off).
        $this->assertStringContainsString('Walang datos pa', $html);
        $this->assertStringNotContainsString('temporarily unavailable', $html);
        $this->assertStringNotContainsString('Current Rain', $html);
        $this->assertStringNotContainsString('Weather Outlook', $html);
        $this->assertStringNotContainsString('0.0 mm', $html);
    }

    public function test_yield_kpi_is_historical_and_shows_a_like_for_like_comparison(): void
    {
        $rows = [];
        foreach (range(1, 8) as $m) {
            $rows[] = ['year' => 2024, 'month' => $m, 'yield' => 2.0, 'split' => null];
            $rows[] = ['year' => 2025, 'month' => $m, 'yield' => 3.0, 'split' => null];
        }
        foreach (range(9, 12) as $m) {
            $rows[] = ['year' => 2024, 'month' => $m, 'yield' => 9.0, 'split' => null];       // months only 2024 has: excluded from the comparison
        }
        $this->yieldRows($rows);

        $k = $this->kpi($this->data(), 'yield');

        $this->assertSame('3.00', $k['value']);
        $this->assertSame('t/ha', $k['unit']);
        $this->assertStringContainsString('Historical', $k['context']);
        $this->assertStringContainsString('8 monthly records', $k['context']);
        $this->assertSame(50.0, $k['trend']['delta']);                                          // 3.0 vs 2.0 over the SAME 8 months
        $this->assertSame('Based on 8 comparable monthly records', $k['trend']['basis']);
        $this->assertStringContainsString('vs same 8 months of 2024', $k['trend']['label']);
    }

    public function test_yield_comparison_is_dropped_when_too_few_months_overlap(): void
    {
        $this->yieldRows([
            ['year' => 2024, 'month' => 1, 'yield' => 2.0, 'split' => null], ['year' => 2024, 'month' => 2, 'yield' => 2.0, 'split' => null],
            ['year' => 2024, 'month' => 3, 'yield' => 2.0, 'split' => null], ['year' => 2024, 'month' => 4, 'yield' => 2.0, 'split' => null],
            ['year' => 2025, 'month' => 1, 'yield' => 4.0, 'split' => null], ['year' => 2025, 'month' => 2, 'yield' => 4.0, 'split' => null],
            ['year' => 2025, 'month' => 3, 'yield' => 4.0, 'split' => null], ['year' => 2025, 'month' => 4, 'yield' => 4.0, 'split' => null],
        ]);

        $k = $this->kpi($this->data(), 'yield');

        $this->assertSame('4.00', $k['value']);
        $this->assertNull($k['trend']);                       // only 4 comparable months (< 6): no percentage at all
        $this->assertStringNotContainsString('%', $k['context']);
    }

    public function test_barangays_monitored_and_active_advisories_kpis(): void
    {
        $this->rating('San Diego', 'High', 0.7);
        $this->rating('Prenza', 'Low', 0.2);
        PlantingAdvisory::factory()->count(2)->create(['status' => PlantingAdvisory::STATUS_PUBLISHED]);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PENDING_REVIEW]);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_ARCHIVED]);

        $data = $this->data();

        // Monitored = every official barangay in the risk-map dataset (the 19 polygons); unrated ones are "awaiting data".
        $b = $this->kpi($data, 'barangays');
        $this->assertSame('19', $b['value']);
        $this->assertSame('of 19', $b['unit']);
        $this->assertSame('2 rated · 17 awaiting data', $b['context']);
        $this->assertSame('monitored', $b['risk_category']);                                     // opens the Municipal Risk Overview pop-up
        $a = $this->kpi($data, 'advisories');
        $this->assertSame('2', $a['value']);                                                    // published only; pending and archived are separate
        $this->assertSame('1 pending review', $a['context']);
    }

    public function test_advisory_kpi_is_hidden_until_an_advisory_exists_and_barangays_kpi_until_ratings_exist(): void
    {
        $data = $this->data();

        $this->assertNull($this->kpi($data, 'advisories'));
        $this->assertNull($this->kpi($data, 'barangays'));
    }

    // ------------------------------------------------------------ attention center

    public function test_attention_center_lists_only_real_actionable_items_most_urgent_first(): void
    {
        $mao = $this->mao();
        $this->fakeWeather([2.0, 30.0, 3.0, 1.0, 1.0, 1.0, 1.0]);
        PlantingAdvisory::factory()->count(2)->create(['status' => PlantingAdvisory::STATUS_PENDING_REVIEW]);
        $this->rating('San Diego', 'Severe', 0.9);
        $this->rating('Prenza', 'High', 0.7);
        $this->rating('Cumba', 'Low', 0.2);

        $items = collect($this->data($mao)['attention']);

        $this->assertSame('urgent', $items[0]['severity']);
        $this->assertStringContainsString('2 Barangays Under High Farm Risk', $items->firstWhere('icon', 'map')['title']);
        $this->assertSame('2 Advisories Awaiting Review', $items->firstWhere('icon', 'advisory')['title']);
        $this->assertSame('Heavy Rain Expected Tomorrow', $items->firstWhere('icon', 'rain')['title']);
        $this->assertSame(3, $items->count());
        foreach ($items as $item) {
            $this->assertNotEmpty($item['href']);
            $this->assertNotEmpty($item['action']);
        }
    }

    public function test_unread_messages_appear_only_for_the_signed_in_user(): void
    {
        $mao = $this->mao();
        $other = $this->mao();
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $conversation = Conversation::query()->forceCreate(['participant_one_id' => $mao->id, 'participant_two_id' => $farmer->id, 'last_message_at' => now()]);
        ConversationMessage::query()->forceCreate(['conversation_id' => $conversation->id, 'sender_id' => $farmer->id, 'body' => 'Hello']);

        $mine = collect($this->data($mao)['attention'])->firstWhere('icon', 'message');
        $theirs = collect($this->data($other)['attention'])->firstWhere('icon', 'message');

        $this->assertSame('1 Unread Message', $mine['title']);
        $this->assertNull($theirs);                                                             // the shared cache never leaks one user's inbox
    }

    public function test_an_active_typhoon_check_in_is_surfaced_not_hidden(): void
    {
        $this->mock(TyphoonSafetyService::class, function ($mock): void {
            $mock->shouldReceive('activeEvent')->andReturn(['key' => 'typhoon-test', 'title' => 'Test typhoon']);
        });

        $item = collect($this->data()['attention'])->firstWhere('icon', 'storm');

        $this->assertSame('Typhoon Safety Check-In Is Active', $item['title']);
        $this->assertStringContainsString(route('mao.operations'), $item['href']);
    }

    // ------------------------------------------------------------ current conditions + outlook

    // ------------------------------------------------------------ risk + watch

    public function test_risk_sections_and_map_are_hidden_when_no_ratings_exist(): void
    {
        $data = $this->data();
        $html = $this->page()->getContent();

        $this->assertSame('empty', $data['risk']['state']);
        $this->assertSame('hidden', $data['watch']['state']);
        $this->assertStringNotContainsString('maoRiskDonutChart', $html);
        $this->assertStringNotContainsString('id="mtdRiskMap"', $html);
    }

    // ------------------------------------------------------------ coverage, climate, conditional panels

    public function test_risk_counts_use_the_latest_rating_of_each_official_barangay_only(): void
    {
        HeatmapArea::factory()->create(['barangay' => 'Prenza', 'risk_level' => 'Severe', 'risk_score' => 0.9, 'risk_computed_at' => now()->subDays(3)]);
        HeatmapArea::factory()->create(['barangay' => 'PRENZA', 'risk_level' => 'Low', 'risk_score' => 0.2, 'risk_computed_at' => now()]);
        $this->rating('Atlantis', 'Severe', 0.95);                                                  // not a Lian barangay

        $risk = $this->data()['risk'];

        $this->assertSame(19, $risk['total']);                                                    // the 19 official barangays, never Atlantis
        $this->assertSame(1, $risk['rated']);
        $this->assertSame(1, $risk['counts']['Low']);                                             // the newer Prenza rating wins
        $this->assertSame(0, $risk['counts']['Severe']);
        $this->assertSame(18, $risk['no_data']);
    }

    public function test_production_uses_only_the_latest_harvest_year(): void
    {
        $this->partialMock(DashboardData::class, function ($mock): void {
            $mock->shouldReceive('riceRows')->andReturn(collect([
                (object) ['barangay' => 'Prenza', 'year' => 2025, 'area_hectares' => 10.0, 'total_production' => 40.0, 'irrigation_type' => 'Irrigated'],
                (object) ['barangay' => 'Prenza', 'year' => 2024, 'area_hectares' => 10.0, 'total_production' => 20.0, 'irrigation_type' => 'Irrigated'],
            ]));
        });
        Cache::flush();
        $p = $this->data()['production'];

        $this->assertSame(2025, $p['year']);
        $this->assertEqualsWithDelta(10.0, $p['planted'], 0.001);
        $this->assertEqualsWithDelta(4.0, $p['recorded_yield'], 0.001);
    }

    // ------------------------------------------------------------ planting guide

    public function test_the_planting_guide_is_absent_without_ratings(): void
    {
        $this->assertNull($this->data()['planting']);
        $this->assertStringNotContainsString('Planting Guide', $this->page()->getContent());
    }

    // ------------------------------------------------------------ office housekeeping stays off the overview

    public function test_activity_log_and_farmer_feedback_are_not_part_of_the_overview(): void
    {
        $mao = $this->mao();
        \App\Models\SystemLog::create(['user_id' => $mao->id, 'action' => 'Published Advisory', 'details' => '{}']);

        $data = $this->data($mao);
        $html = $this->page($mao)->getContent();

        $this->assertArrayNotHasKey('activity', $data);
        $this->assertArrayNotHasKey('feedback', $data);
        $this->assertStringNotContainsString('data-mao-activity', $html);
        $this->assertStringNotContainsString('Farmer Weather Feedback', $this->body($html));
    }

    // ------------------------------------------------------------ sources, refresh, typography

    public function test_the_page_follows_the_farmer_dashboard_type_system(): void
    {
        // Same system as farmer-dashboard.css: Georgia for page and section titles, Inter for everything else.
        $html = $this->page()->getContent();
        $css = file_get_contents(resource_path('css/mao-dashboard.css'));

        $this->assertStringContainsString('is-mao-dashboard', $html);                               // the page-level font rule applies
        $this->assertStringNotContainsString('<style>', substr($html, strpos($html, 'data-mao-dashboard') - 2000, 2000));  // no page-local stylesheet
        $this->assertMatchesRegularExpression('/body\.is-mao-dashboard, body\.is-mao-dashboard \*:not\(svg\):not\(svg \*\) \{ font-family: "Inter"/', $css);
        $this->assertMatchesRegularExpression('/body\.is-mao-dashboard \.mtd h1, body\.is-mao-dashboard \.mtd h2[^{]*\{\s*font-family: Georgia/', $css);
        $this->assertMatchesRegularExpression('/\.mtd \.mtd-header h1 \{[^}]*font-size: 2rem; font-weight: 700/', $css);                 // page title 32px, as .mfd h1
        $this->assertMatchesRegularExpression('/\.mtd \.mtd-section-title \{[^}]*font-size: 1\.5rem; font-weight: 700/', $css);         // section title 24px, as .mfd h2
        $this->assertMatchesRegularExpression('/\.mtd-kpi-value \{[^}]*font-size: 1\.75rem; font-weight: 700/', $css);                   // KPI value 28px, as .mfd-kpi-value
        $this->assertMatchesRegularExpression('/\.mtd-kpi-label \{[^}]*font-size: \.8125rem; font-weight: 600[^}]*text-transform: uppercase/', $css);  // label 13px, as .mfd-kpi-label
        $this->assertStringContainsString('min-height: 44px', $css);                                // touch targets
        $this->assertStringNotContainsString('DM Mono', $css);
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.75, (float) $rem);                                     // nothing below 12px
        }
    }

    public function test_the_refresh_endpoint_rebuilds_each_live_section(): void
    {
        $this->rating('San Diego', 'High', 0.7, 'Heavy rainfall exposure');
        $json = $this->actingAs($this->mao())->getJson(route('mao.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        // The risk overview and map are not a slot: re-inserting them would rebuild the map, so they reload their own data.
        $this->assertEqualsCanonicalizing(['kpis', 'attention', 'conditions', 'forecast', 'watch', 'planting', 'coverage', 'production'], array_keys($json['slots']));
        $this->assertStringContainsString('data-kpi="yield"', $json['slots']['kpis']);
        $this->assertStringContainsString('Weather Now', $json['slots']['conditions']);
        $this->assertStringContainsString('Planting Guide', $json['slots']['planting']);
        $this->assertSame('', trim($json['slots']['production']));                                  // nothing to show: the section disappears
        $this->assertArrayHasKey('refreshed_at', $json);
    }

    public function test_service_thresholds_are_the_documented_constants(): void
    {
        $this->assertSame(25.0, MaoDashboardService::HEAVY_RAIN_MM);
        $this->assertSame(35.0, MaoDashboardService::STRONG_WIND_KMH);
        $this->assertSame(6, MaoDashboardService::MIN_COMPARABLE_MONTHS);
    }
}
