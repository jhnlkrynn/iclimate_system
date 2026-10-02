<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Analytics\DashboardOverviewService;
use App\Services\Analytics\MaoDashboardService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\WeatherFeedbackSummaryService;
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

        return $this->actingAs($as ?? $this->mao())->get(route('mao.dashboard'))->assertOk();
    }

    private function data(?User $as = null): array
    {
        return $this->page($as)->viewData('mao');
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

    public function test_only_mao_personnel_can_open_the_dashboard_operations_and_live_endpoint(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->get(route('mao.dashboard'))->assertRedirect(route('login'));      // guest first: actingAs() persists
        foreach (['mao.dashboard', 'mao.dashboard.live', 'mao.operations'] as $route) {
            $this->actingAs($it)->get(route($route))->assertForbidden();
            $this->actingAs($farmer)->get(route($route))->assertForbidden();
        }
        $this->actingAs($this->mao())->get(route('mao.operations'))->assertOk();
    }

    public function test_page_follows_the_revised_row_order(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        FarmerProfile::factory()->create();
        $this->rating('San Diego', 'High', 0.7, 'Heavy rainfall exposure');
        $this->rating('Prenza', 'Low', 0.2);
        $html = $this->page()->getContent();

        $order = ['MAO Dashboard', 'data-mao-kpis', 'data-mao-attention', 'data-mao-conditions', 'data-mao-yield', 'data-mao-risk',
            'data-mao-climate', 'data-mao-coverage', 'data-mao-forecast', 'data-mao-watch', 'id="mtdRiskMap"', 'data-mao-activity'];
        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker);
            if ($marker === 'data-mao-climate' && $pos === false) {
                continue;                      // no Ambulong history in this environment
            }
            if ($marker === 'data-mao-activity' && $pos === false) {
                continue;                      // nothing but logins recorded: the section is hidden by design
            }
            $this->assertNotFalse($pos, "Missing section: $marker");
            $this->assertGreaterThan($last, $pos, "Section out of order: $marker");
            $last = $pos;
        }
        foreach (['Municipal Agriculture Monitoring — Lian, Batangas', 'Monitor farmers, weather conditions, farm risk, rice yield, and agricultural advisories.', 'Refresh', 'Create Advisory', 'Open Reports', 'Data Sources'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_removed_and_empty_content_is_not_on_the_overview(): void
    {
        $html = $this->page()->getContent();
        $body = $this->body($html);

        foreach (['Office Overview', 'MAO Operational Workflow', 'Predictions by Month', 'Average Yield by Month', 'Not recorded', 'N/A',
            'Season:', 'Planting &amp; Expected Yield', 'Hectares Planted', 'Farmer Weather Feedback', 'Not enough farmer reports', 'dashboard-tab',
            'Climate Records', 'Learn More', 'Barangays to Watch'] as $removed) {
            $this->assertStringNotContainsString($removed, $body, $removed);
        }
        foreach (['Risk Map &amp; Forecast', 'Monitoring &amp; Production', 'Field Reports', 'Publishing &amp; Activity'] as $tab) {
            $this->assertStringNotContainsString($tab, $body, $tab);
        }
    }

    public function test_main_cards_show_a_proper_empty_state_instead_of_disappearing(): void
    {
        $this->yieldRows([]);
        $html = $this->page()->getContent();

        foreach (['data-mao-yield-empty', 'data-mao-risk-empty', 'data-mao-coverage-empty'] as $marker) {
            $this->assertStringContainsString($marker, $html, $marker);
        }
        $this->assertStringContainsString('No data available.', $html);
        $this->assertStringNotContainsString('id="mtdRiskMap"', $html);                            // the map itself still waits for ratings
    }

    public function test_the_old_dashboard_still_exists_as_mao_operations_with_its_features(): void
    {
        $html = $this->actingAs($this->mao())->get(route('mao.operations'))->assertOk()->getContent();

        foreach (['Risk Map &amp; Forecast', 'Monitoring &amp; Production', 'Field Reports', 'Publishing &amp; Activity', 'MAO Operational Workflow', 'id="activity-log"'] as $kept) {
            $this->assertStringContainsString($kept, $html, $kept);
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

    public function test_weather_kpi_shows_the_live_reading_and_keeps_a_real_zero_rain(): void
    {
        $this->fakeWeather([1, 1, 1, 1, 1, 1, 1], currentRain: 0.0);

        $k = $this->kpi($this->data(), 'weather');
        $html = $this->page()->getContent();

        $this->assertSame('28.3', $k['value']);
        $this->assertStringContainsString('82% humidity', $k['context']);
        $this->assertStringContainsString('0.0 mm rain', $k['context']);                 // a genuine zero is displayed, not hidden
        $this->assertStringContainsString('Current Rain', $html);
    }

    public function test_a_failed_weather_source_hides_live_values_instead_of_showing_zeros(): void
    {
        $this->replaceWeather(Http::response([], 500));
        Cache::flush();

        $data = $this->data();
        $html = $this->page()->getContent();

        $this->assertSame('error', $data['weather']['state']);
        $this->assertNull($this->kpi($data, 'weather'));
        $this->assertStringContainsString('Live weather is temporarily unavailable.', $html);
        $this->assertStringContainsString('data-mao-retry', $html);
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

    public function test_yield_trend_needs_two_years_and_flags_part_years(): void
    {
        $this->yieldRows([['year' => 2025, 'month' => 1, 'yield' => 3.0, 'split' => null]]);
        $one = $this->page()->getContent();
        $this->assertStringNotContainsString('id="riceYieldTrendChart"', $one);                    // one point is not a trend
        $this->assertNotNull($this->kpi($this->data(), 'yield'));                             // the latest average can still be shown

        $rows = array_merge(
            array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => 3.0, 'split' => null], range(1, 12)),
            array_map(fn ($m) => ['year' => 2025, 'month' => $m, 'yield' => 4.0, 'split' => null], range(1, 8)),
        );
        $this->yieldRows($rows);
        $two = $this->page()->getContent();

        $this->assertStringContainsString('id="riceYieldTrendChart"', $two);
        $this->assertStringContainsString('Years with fewer than 12 monthly records: 2025 (8 months)', $two);
        $this->assertStringContainsString('data-chart-info-style="icon"', $two);              // small info icon, not a text badge
    }

    public function test_barangays_monitored_and_active_advisories_kpis(): void
    {
        $this->rating('San Diego', 'High', 0.7);
        $this->rating('Prenza', 'Low', 0.2);
        PlantingAdvisory::factory()->count(2)->create(['status' => PlantingAdvisory::STATUS_PUBLISHED]);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PENDING_REVIEW]);
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_ARCHIVED]);

        $data = $this->data();

        $b = $this->kpi($data, 'barangays');
        $this->assertSame('2', $b['value']);
        $this->assertSame('of 19', $b['unit']);
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

    public function test_kpi_cards_carry_an_icon_a_tooltip_and_no_paragraphs(): void
    {
        $this->rating('San Diego', 'Low', 0.2);
        $html = $this->page()->getContent();
        $section = substr($html, strpos($html, 'data-mao-kpis'), strpos($html, 'data-mao-attention') - strpos($html, 'data-mao-kpis'));

        $this->assertGreaterThanOrEqual(2, substr_count($section, 'mtd-kpi-icon'));
        $this->assertSame(substr_count($section, 'mtd-kpi-icon'), substr_count($section, 'class="mtd-info"'));
        $this->assertDoesNotMatchRegularExpression('/<p[\s>]/', $section);
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

    public function test_attention_center_shows_a_compact_positive_state_when_nothing_needs_action(): void
    {
        $html = $this->page()->getContent();

        $this->assertStringContainsString('No urgent agricultural actions at this time.', $html);
        $this->assertStringContainsString('mtd-attention is-clear', $html);
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

    public function test_current_farm_conditions_show_four_readings_with_a_timestamp(): void
    {
        $html = $this->page()->getContent();
        $section = substr($html, strpos($html, 'data-mao-conditions'), 3500);

        foreach (['Current Farm Conditions', 'Live weather conditions for Lian, Batangas.', 'Current Rain', 'Temperature', 'Humidity', 'Wind Speed', 'Last updated:', 'View Weather Forecast'] as $text) {
            $this->assertStringContainsString($text, $section, $text);
        }
        $this->assertStringContainsString('28.3', $section);
        $this->assertStringNotContainsString('progress', $section);                               // no decorative bars
        $this->assertStringNotContainsString('current reading', strtolower($section));
    }

    public function test_the_outlook_shows_every_forecast_day_and_highlights_only_days_past_the_thresholds(): void
    {
        $this->fakeWeather([1.0, 2.0, 30.0, 3.0, 4.0, 5.0, 6.0]);

        $html = $this->page()->getContent();
        $section = substr($html, strpos($html, 'data-mao-forecast'), strpos($html, 'Days are highlighted') - strpos($html, 'data-mao-forecast'));

        $this->assertSame(7, substr_count($section, '<article class="mtd-day'));
        $this->assertSame(1, substr_count($section, 'mtd-day is-flagged'));
        $this->assertStringContainsString('Heavy rain', $section);
        $this->assertStringContainsString('Weather Outlook — Next 7 Days', $html);
        $this->assertStringNotContainsStringIgnoringCase('drizzle', $html);
        $this->assertStringContainsString('25 mm of rain', $html);                                // the documented threshold is stated
    }

    // ------------------------------------------------------------ risk + watch

    public function test_farm_risk_counts_reconcile_and_the_watch_list_only_lists_high_and_severe(): void
    {
        $this->rating('San Diego', 'Severe', 0.9, 'Flood-related farm risk');
        $this->rating('Matabungkay', 'High', 0.75, 'Heavy rainfall exposure');
        $this->rating('Prenza', 'Moderate', 0.5);
        $this->rating('Cumba', 'Low', 0.2);

        $data = $this->data();

        $this->assertSame(['Low' => 1, 'Moderate' => 1, 'High' => 1, 'Severe' => 1], $data['risk']['counts']);
        $this->assertSame($data['risk']['total'], array_sum($data['risk']['counts']));
        $this->assertSame(['San Diego', 'Matabungkay'], array_column($data['watch']['items'], 'barangay'));   // most severe first
        $html = $this->page()->getContent();
        $this->assertStringContainsString('BARANGAYS', $html);
        $this->assertStringContainsString('Farm risk is a planning indicator based on available weather and agricultural data.', $html);
        $this->assertStringContainsString('data-chart-note=', $html);                            // the methodology caveat travels with the chart
    }

    public function test_no_high_risk_barangays_gives_a_small_positive_card_not_an_empty_panel(): void
    {
        $this->rating('Prenza', 'Moderate', 0.5);
        $this->rating('Cumba', 'Low', 0.2);

        $html = $this->page()->getContent();

        $this->assertSame('clear', $this->data()['watch']['state']);
        $this->assertStringContainsString('No high-risk barangays detected', $html);
        $this->assertStringContainsString('All monitored barangays are currently below the high-risk threshold.', $html);
        $this->assertStringNotContainsString('No High or Severe farm risk right now', $html);
        $this->assertStringContainsString('mtd-clear', $html);
    }

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

    public function test_farmer_coverage_summarises_real_registrations(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        FarmerProfile::factory()->create(['barangay' => 'Binubusan', 'farm_area' => 2.0, 'farmer_organization_id' => null]);
        FarmerProfile::factory()->create(['barangay' => 'Prenza', 'farm_area' => 3.0, 'farmer_organization_id' => null]);

        $f = $this->data()['farmers'];
        $html = $this->page()->getContent();

        $this->assertSame(2, $f['farmers']);
        $this->assertSame(2, $f['barangays_covered']);
        $this->assertSame(19, $f['barangays_total']);
        $this->assertSame(2, $f['without_organization']);
        $this->assertEqualsWithDelta(5.0, $f['farm_area'], 0.001);
        $this->assertStringContainsString('2 / 19', $html);
        $this->assertStringContainsString('View Farmer Analytics', $html);
    }

    public function test_the_climate_chart_reports_annual_rainfall_with_the_months_it_is_based_on(): void
    {
        $snapshot = app(DashboardOverviewService::class)->climateSnapshot();
        $html = $this->page()->getContent();

        if ($snapshot === null) {
            $this->assertStringNotContainsString('maoRainTempChart', $html);

            return;
        }
        $this->assertStringContainsString('maoRainTempChart', $html);
        $this->assertStringContainsString('Rainfall &amp; Temperature — '.$snapshot['year'], $html);
        $this->assertStringContainsString('Solid lines are historical records; dashed lines are model forecasts.', $html);
        $this->assertStringContainsString('total of '.$snapshot['rainfall_months'].' recorded', $html);
        if ($snapshot['rainfall_months'] < 12) {
            $this->assertStringContainsString('not a full year', $html);                          // a part-year is never called an annual total
        }
    }

    public function test_planting_and_production_appears_only_when_real_production_records_exist(): void
    {
        $this->assertNull($this->data()['production']);
        $this->assertStringNotContainsString('Planting &amp; Production', $this->page()->getContent());

        $this->partialMock(DashboardData::class, function ($mock): void {
            $mock->shouldReceive('riceRows')->andReturn(collect([
                (object) ['barangay' => 'Prenza', 'area_hectares' => 10.0, 'total_production' => 40.0, 'irrigation_type' => 'Irrigated'],
                (object) ['barangay' => 'Cumba', 'area_hectares' => 5.0, 'total_production' => 15.0, 'irrigation_type' => 'Rainfed'],
            ]));
        });
        Cache::flush();
        $p = $this->data()['production'];

        $this->assertEqualsWithDelta(15.0, $p['planted'], 0.001);
        $this->assertEqualsWithDelta(10.0, $p['irrigated'], 0.001);
        $this->assertEqualsWithDelta(5.0, $p['rainfed'], 0.001);
        $this->assertEqualsWithDelta(55 / 15, $p['recorded_yield'], 0.001);
        $this->assertStringContainsString('Planting &amp; Production', $this->page()->getContent());
    }

    public function test_risk_counts_use_the_latest_rating_of_each_official_barangay_only(): void
    {
        HeatmapArea::factory()->create(['barangay' => 'Prenza', 'risk_level' => 'Severe', 'risk_score' => 0.9, 'risk_computed_at' => now()->subDays(3)]);
        HeatmapArea::factory()->create(['barangay' => 'PRENZA', 'risk_level' => 'Low', 'risk_score' => 0.2, 'risk_computed_at' => now()]);
        $this->rating('Atlantis', 'Severe', 0.95);                                                  // not a Lian barangay

        $risk = $this->data()['risk'];

        $this->assertSame(1, $risk['total']);
        $this->assertSame(1, $risk['counts']['Low']);
        $this->assertSame(0, $risk['counts']['Severe']);
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

    public function test_farmer_feedback_appears_only_with_enough_reports(): void
    {
        $this->assertNull($this->data()['feedback']);                                              // default: not enough data, so no panel

        $this->mock(WeatherFeedbackSummaryService::class, function ($mock): void {
            $mock->shouldReceive('summarize')->andReturn(['total' => 9, 'counts' => [], 'percentages' => ['accurate' => 60, 'partly' => 30, 'different' => 10],
                'enough_data' => true, 'min_reports' => 5, 'message' => 'Most reports say the forecast was accurate.', 'groups' => [], 'barangays' => []]);
        });
        Cache::flush();

        $this->assertSame(9, $this->data()['feedback']['total']);
        $this->assertStringContainsString('Farmer Weather Feedback', $this->page()->getContent());
    }

    // ------------------------------------------------------------ activity, sources, refresh, typography

    public function test_recent_activity_lists_agricultural_work_not_sign_ins(): void
    {
        $mao = $this->mao();
        SystemLog::create(['user_id' => $mao->id, 'action' => 'User Login', 'details' => '{}']);
        $this->assertSame('empty', $this->data($mao)['activity']['state']);                        // only a sign-in: the section is hidden
        $this->assertStringNotContainsString('data-mao-activity', $this->page($mao)->getContent());

        SystemLog::create(['user_id' => $mao->id, 'action' => 'Published Advisory', 'details' => json_encode(['context' => ['record_type' => 'PlantingAdvisory', 'record_id' => 7]])]);
        $rows = $this->data($mao)['activity']['rows'];

        $this->assertSame(['Published Advisory'], array_column($rows, 'action'));
        $this->assertStringContainsString('Recent MAO Activity', $this->page($mao)->getContent());
    }

    public function test_data_sources_label_each_kind_of_data_and_only_list_sources_in_use(): void
    {
        $this->rating('San Diego', 'Low', 0.2);
        $html = $this->page()->getContent();
        $panel = substr($html, strpos($html, 'class="mtd-sources-panel"'), 2800);

        foreach (['LIVE', 'FORECAST', 'CALCULATED', 'Last synchronized:'] as $label) {
            $this->assertStringContainsString($label, $panel, $label);
        }
        $this->assertStringNotContainsString('RECORDED', $panel);                                  // no farmer profiles here, so no such source
    }

    public function test_the_page_uses_one_font_family_and_the_specified_type_scale(): void
    {
        $html = $this->page()->getContent();
        $css = substr($html, strpos($html, '.mtd {'), strpos($html, '</style>', strpos($html, '.mtd {')) - strpos($html, '.mtd {'));

        $this->assertStringContainsString("--mtd-font: Georgia, 'Times New Roman', 'Noto Serif', serif", $css);
        $this->assertStringContainsString('font-family: var(--mtd-font)', $css);
        $this->assertStringNotContainsString('Inter', $css);
        $this->assertStringNotContainsString('DM Mono', $css);
        $this->assertMatchesRegularExpression('/\.mtd-header h1 \{[^}]*font-size: 1\.75rem; font-weight: 700; line-height: 1\.2/', $css);       // page title 28px / 700
        $this->assertMatchesRegularExpression('/\.mtd-kpi-value \{[^}]*font-size: 2rem; font-weight: 400/', $css);                                // KPI value 32px / 700
        $this->assertMatchesRegularExpression('/\.mtd-kpi-label \{[^}]*font-size: \.75rem; font-weight: 400; text-transform: uppercase/', $css);  // KPI label 12px, regular
        $this->assertMatchesRegularExpression('/\.mtd-card-head h2 \{[^}]*font-size: 1\.125rem; font-weight: 700/', $css);                        // section title 18px / 600
        $this->assertMatchesRegularExpression('/\.mtd h1, \.mtd h2, \.mtd h3, \.mtd th[^{]*\{[^}]*font-weight: 700/', $css);                          // every heading is Georgia Bold
        preg_match_all('/font-weight: (\d+)/', $css, $weights);
        foreach (array_unique($weights[1]) as $w) {
            $this->assertContains($w, ['400', '700'], 'only regular and bold are used');
        }
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.68, (float) $rem);                                     // nothing important below ~11px
        }
    }

    public function test_the_refresh_endpoint_rebuilds_each_live_section(): void
    {
        $this->rating('San Diego', 'High', 0.7, 'Heavy rainfall exposure');
        $json = $this->actingAs($this->mao())->getJson(route('mao.dashboard.live', ['refresh' => 1]))->assertOk()->json();

        $this->assertEqualsCanonicalizing(['kpis', 'attention', 'conditions', 'risk', 'forecast', 'watch', 'coverage', 'production', 'feedback', 'activity'], array_keys($json['slots']));
        $this->assertStringContainsString('data-kpi="weather"', $json['slots']['kpis']);
        $this->assertStringContainsString('Current Farm Conditions', $json['slots']['conditions']);
        $this->assertSame('', trim($json['slots']['production']));                                  // nothing to show: the section disappears
        $this->assertArrayHasKey('refreshed_at', $json);
    }

    public function test_sidebar_navigation_is_unchanged_for_mao(): void
    {
        $html = $this->page()->getContent();

        foreach (['Farm Calendar', 'Climate Records', 'Planting Advisories', 'Messages', 'Farmer Profiles', 'Predictions', 'Chatbot Knowledge', 'Rice Production', 'Community Feed', 'Reports'] as $link) {
            $this->assertStringContainsString($link, $html, $link);
        }
    }

    public function test_service_thresholds_are_the_documented_constants(): void
    {
        $this->assertSame(25.0, MaoDashboardService::HEAVY_RAIN_MM);
        $this->assertSame(35.0, MaoDashboardService::STRONG_WIND_KMH);
        $this->assertSame(6, MaoDashboardService::MIN_COMPARABLE_MONTHS);
    }
}
