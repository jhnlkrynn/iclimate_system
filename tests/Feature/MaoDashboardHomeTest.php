<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MAO Dashboard (2026-10): four KPI tiles, the "Kailangan ng Aksyon" list, a small Heat Map preview and one bar chart,
 * all from saved data. The Operations page is gone (its parts moved to Advisories, Calendar, Field Reports and
 * Reports), the MAO sidebar is one list of eleven pages, and Chatbot Knowledge belongs to IT.
 */
class MaoDashboardHomeTest extends TestCase
{
    use RefreshDatabase;

    /** The barangays given to storeForecasts(), so html() can put the saved forecasts back after dropping the cache. */
    private ?array $forecasts = null;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));   // Wet season 2026 (May–October)
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function farmer(string $barangay = 'Prenza'): User
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay, 'status' => User::STATUS_ACTIVE]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);

        return $user;
    }

    /** The dashboard as a fresh visit (its prepared copy is dropped; saved forecasts are put back). */
    private function html(?User $as = null): string
    {
        Cache::flush();
        if ($this->forecasts !== null) {
            $this->storeForecasts($this->forecasts);
        }

        return $this->actingAs($as ?? $this->mao())->get(route('mao.dashboard'))->assertOk()->getContent();
    }

    /** The visible words of one KPI tile. */
    private function tile(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('#<a class="mhd-tile"[^>]*data-kpi="'.$key.'">(.*?)</a>#s', $html, $m), "tile {$key}");

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    /** Replaces the yield history with known monthly rows. */
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

    /** Saves a calm 16-day forecast for every barangay, with very heavy rain tomorrow in the given ones (High this week). */
    private function storeForecasts(array $highRisk = []): void
    {
        $this->forecasts = $highRisk;
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $records = [];
        foreach (LianBarangays::all() as $barangay) {
            $records[$barangay] = ['barangay' => $barangay, 'temperature_c' => 28.0, 'fetched_at' => now()->toIso8601String(), 'observed_at' => now()->toIso8601String(),
                'daily' => array_map(fn (int $i) => ['date' => $today->addDays($i)->toDateString(), 'rainfall' => in_array($barangay, $highRisk, true) && $i === 1 ? 60.0 : 4.0,
                    'wind_speed_max' => 12.0, 'temperature_max' => 31.0], range(0, 15))];
        }
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => $records, 'status' => []]);
    }

    // ------------------------------------------------------------ the page

    public function test_the_dashboard_is_four_tiles_the_action_list_the_map_preview_and_one_chart(): void
    {
        $this->yieldRows([['year' => 2024, 'month' => 6, 'yield' => 3.8, 'split' => null]]);
        $html = $this->html();

        preg_match_all('#data-kpi="([a-z]+)"#', $html, $tiles);
        $this->assertSame(['farmers', 'area', 'yield', 'risk'], $tiles[1]);
        foreach (['Registered farmers', 'Area planted this season', 'Expected yield vs last season', 'High-risk barangays'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
        // In reading order: tiles, Kailangan ng Aksyon, the Heat Map preview, the one chart.
        $order = array_map(fn (string $marker) => strpos($html, $marker), ['data-mao-kpis', 'Kailangan ng Aksyon', 'id="mhdMapTitle"', 'Rice Yield by Year 2020–2025']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        $this->assertSame(1, substr_count($html, '<canvas'));

        // What the old dashboard carried is not here any more: weather cards, the forecast row, the full map and its
        // overview, the climate chart, the planting guide, the data-source pop-up, the Refresh button, the Operations link.
        foreach (['Weather Now', 'mtd-day', 'id="barangayRiskMap"', 'data-risk-overview', 'maoRainTempChart', 'data-mao-planting', 'mtdDataSources', 'data-mao-refresh', 'More MAO tools', 'mao/operations'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
    }

    public function test_only_mao_personnel_open_the_dashboard(): void
    {
        $this->get(route('mao.dashboard'))->assertRedirect(route('login'));
        foreach ([User::ROLE_IT_EXPERT, User::ROLE_FARMER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['mao.dashboard', 'mao.dashboard.live', 'mao.operations'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_the_page_reads_saved_data_only_and_empty_figures_say_no_data_never_zero(): void
    {
        $this->yieldRows([]);
        $html = $this->html();

        Http::assertNothingSent();
        $this->assertStringContainsString('0 Farmer profiles on file', $this->tile($html, 'farmers'));   // a real count of zero
        foreach (['area', 'yield', 'risk'] as $key) {
            $this->assertStringContainsString('Walang datos pa', $this->tile($html, $key), $key);
            $this->assertDoesNotMatchRegularExpression('/\b0\b/', $this->tile($html, $key), $key);
        }
        // No saved forecast: no map preview; no yield records: no chart. Each says so.
        $this->assertStringNotContainsString('data-heat-map-preview', $html);
        $this->assertStringNotContainsString('<canvas', $html);
        $this->assertSame(5, substr_count($html, 'Walang datos pa'));
    }

    // ------------------------------------------------------------ the four tiles

    public function test_registered_farmers_counts_farmer_profiles_and_opens_farmer_profiles(): void
    {
        $this->farmer();
        $this->farmer('Cumba');
        User::factory()->farmer()->create();                                   // an account without a farm profile is not a registered farmer

        $html = $this->html();

        $this->assertStringStartsWith('Registered farmers 2', $this->tile($html, 'farmers'));
        $this->assertStringContainsString('<a class="mhd-tile" href="'.route('farmer-profiles.index').'" data-kpi="farmers">', $html);
    }

    public function test_area_planted_adds_up_this_seasons_planting_plans_only(): void
    {
        $farmer = $this->farmer();
        $plan = fn (string $date, float $area) => FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'planting_date' => $date,
            'scheduled_harvest_date' => CarbonImmutable::parse($date)->addDays(110)->toDateString(), 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => $area]);
        $plan('2026-06-10', 1.5);                                              // this Wet season (May–October 2026)
        $plan('2026-10-20', 2.25);
        $plan('2026-04-20', 9.0);                                              // last Dry season
        $plan('2026-11-05', 9.0);                                              // next Dry season

        $tile = $this->tile($this->html(), 'area');

        $this->assertStringStartsWith('Area planted this season 3.8 ha', $tile);
        $this->assertStringContainsString('From 2 planting plans · Wet season 2026', $tile);
    }

    public function test_expected_yield_is_the_average_of_saved_estimates_beside_the_last_recorded_season(): void
    {
        // Wet 2025 (May–Oct) averages 3.50; Dry 2025–2026 (Nov–Apr) averages 4.00 and is the last season before now.
        $this->yieldRows([
            ['year' => 2025, 'month' => 6, 'yield' => 3.4, 'split' => null], ['year' => 2025, 'month' => 9, 'yield' => 3.6, 'split' => null],
            ['year' => 2025, 'month' => 12, 'yield' => 3.8, 'split' => null], ['year' => 2026, 'month' => 2, 'yield' => 4.2, 'split' => null],
            ['year' => 2026, 'month' => 7, 'yield' => 9.9, 'split' => null],    // the current season is never "last season"
        ]);
        foreach ([[$this->farmer(), 4.2], [$this->farmer('Cumba'), 4.6]] as [$farmer, $yield]) {
            Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id), ['success' => true, 'yield_tons_per_hectare' => $yield, 'target_month' => '2026-10', 'ran_at' => now()->toIso8601String()], now()->addHours(6));
        }

        $html = $this->actingAs($this->mao())->get(route('mao.dashboard'))->assertOk()->getContent();
        $tile = $this->tile($html, 'yield');

        $this->assertStringStartsWith('Expected yield vs last season 4.40 t/ha', $tile);
        $this->assertStringContainsString('+10% vs last season', $tile);
        $this->assertStringContainsString('Dry season 2025–2026: 4.00 t/ha', $tile);
        Http::assertNothingSent();                                             // saved estimates only: no model is run
    }

    public function test_high_risk_barangays_is_the_heat_maps_this_week_count(): void
    {
        $this->storeForecasts(['Matabungkay', 'San Diego']);
        $html = $this->html();

        $tile = $this->tile($html, 'risk');
        $this->assertStringStartsWith('High-risk barangays 2 of 19', $tile);
        $this->assertStringContainsString('Matabungkay, San Diego', $tile);
        $this->assertStringContainsString('<a class="mhd-tile" href="'.route('heat-map.index').'" data-kpi="risk">', $html);

        // The same ratings colour the preview, and the Heat Map page opens with the same count.
        $this->assertSame(1, preg_match('#<script type="application/json" data-heat-map-preview-levels>(.*?)</script>#s', $html, $m));
        $levels = json_decode($m[1], true)['levels'];
        $this->assertCount(19, $levels);
        $this->assertSame(['Matabungkay', 'San Diego'], array_keys(array_filter($levels, fn ($l) => $l === 'high')));
        $this->actingAs($this->mao())->get(route('heat-map.index'))->assertSee('2 barangays are at high risk');

        // A calm week is a real zero, not "no data".
        $this->storeForecasts();
        $this->assertStringStartsWith('High-risk barangays 0 of 19 None this week', $this->tile($this->html(), 'risk'));
    }

    // ------------------------------------------------------------ Kailangan ng Aksyon

    public function test_kailangan_ng_aksyon_counts_what_waits_and_links_to_the_page_that_handles_it(): void
    {
        $mao = $this->mao();
        $advisory = fn (string $status) => PlantingAdvisory::query()->create(['title' => 'A', 'content' => 'M', 'message' => 'M', 'type' => 'Planting', 'advisory_type' => 'planting',
            'severity' => 'low', 'target_scope' => 'municipality', 'status' => $status, 'valid_from' => now()->subDay(), 'valid_until' => now()->addWeek(), 'posted_by' => $mao->id]);
        $advisory('pending_review');
        $advisory('pending_review');
        $advisory('published');
        foreach ([FieldReport::STATUS_PENDING, FieldReport::STATUS_VERIFIED, FieldReport::STATUS_RESOLVED] as $status) {
            FieldReport::query()->create(['title' => 'Report '.$status, 'barangay' => 'Prenza', 'issue_type' => 'Flooding', 'description' => 'Water in the field.', 'status' => $status, 'reported_by' => $mao->id]);
        }
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn(['key' => 'typhoon-test', 'title' => 'Typhoon Test', 'severity' => 'Signal No. 2']));
        TyphoonSafetyResponse::query()->create(['user_id' => $this->farmer()->id, 'event_key' => 'typhoon-test', 'event_title' => 'Typhoon Test', 'status' => TyphoonSafetyResponse::STATUS_NEEDS_HELP, 'note' => 'Roof damaged', 'responded_at' => now()]);
        TyphoonSafetyResponse::query()->create(['user_id' => $this->farmer('Cumba')->id, 'event_key' => 'typhoon-test', 'event_title' => 'Typhoon Test', 'status' => TyphoonSafetyResponse::STATUS_SAFE, 'responded_at' => now()]);
        TyphoonSafetyResponse::query()->create(['user_id' => $this->farmer('Kapito')->id, 'event_key' => 'an-older-typhoon', 'event_title' => 'Older', 'status' => TyphoonSafetyResponse::STATUS_NEEDS_HELP, 'responded_at' => now()]);

        $html = $this->html($mao);

        $expected = [
            'advisories' => ['2', 'Advisories to approve', route('planting-advisories.index', ['show' => 'pending'])],
            'field-reports' => ['2', 'Unresolved field reports', route('field-reports.index', ['unresolved' => 1])],
            'check-ins' => ['1', '"Needs Help" check-ins', route('planting-advisories.index').'#check-ins'],
        ];
        foreach ($expected as $key => [$count, $label, $href]) {
            $this->assertSame(1, preg_match('#<li class="mhd-action[^"]*"\s+data-mao-action="'.$key.'">(.*?)</li>#s', $html, $m), $key);
            $this->assertSame($count.' '.$label, trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) preg_replace('#<a .*?</a>#s', '', $m[1]))))), $key);
            $this->assertStringContainsString('href="'.e($href).'"', $m[1], $key);
        }
        $this->assertStringContainsString('<span class="mtd-count">5</span>', $html);

        // Each count's page shows exactly those items.
        $this->actingAs($mao)->get(route('field-reports.index', ['unresolved' => 1]))->assertOk()
            ->assertSee('Report Pending')->assertSee('Report Verified')->assertDontSee('Report Resolved');
        $this->actingAs($mao)->get(route('field-reports.index'))->assertSee('Report Resolved');
        $checkIns = $this->actingAs($mao)->get(route('planting-advisories.index'))->assertOk();
        $checkIns->assertSee('id="check-ins"', false)->assertSee('Typhoon Test')->assertSee('Roof damaged')
            ->assertSee('<dd data-adv-needs-help>1</dd>', false);
    }

    public function test_with_nothing_waiting_the_list_shows_zeros_and_says_so(): void
    {
        $html = $this->html();

        $this->assertSame(3, preg_match_all('#<strong class="mhd-action-count">0</strong>#', $html));
        $this->assertStringContainsString('Nothing is waiting right now.', $html);
        $this->assertStringNotContainsString('class="mtd-count"', $html);
        // Outside a typhoon the check-ins section on Advisories says no check-in is open.
        $this->actingAs($this->mao())->get(route('planting-advisories.index'))->assertSee('data-adv-checkins-closed', false);
        // Farmers never see the staff check-ins section.
        $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->assertDontSee('data-adv-checkins', false);
    }

    // ------------------------------------------------------------ the chart

    public function test_the_chart_is_rice_yield_by_year_2020_to_2025(): void
    {
        $rows = [];
        foreach ([2019 => 9.0, 2020 => 3.2, 2021 => 3.4, 2023 => 3.9, 2025 => 4.1, 2026 => 9.0] as $year => $yield) {
            foreach ([3, 9] as $month) {
                $rows[] = ['year' => $year, 'month' => $month, 'yield' => $yield, 'split' => null];
            }
        }
        $this->yieldRows($rows);

        $html = $this->html();

        $this->assertSame(1, preg_match('#<canvas id="riceYieldTrendChart" data-yield="([^"]+)"#', $html, $m));
        $chart = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(['2020', '2021', '2022', '2023', '2024', '2025'], $chart['labels']);
        $this->assertSame([3.2, 3.4, null, 3.9, null, 4.1], $chart['values']);          // a year without records is a gap, not zero
        $this->assertSame([2, 2, 0, 2, 0, 2], $chart['months']);
        $this->assertStringContainsString("type: 'bar'", $html);
    }

    // ------------------------------------------------------------ moved pages, sidebar, Chatbot Knowledge

    public function test_the_operations_page_and_advisory_management_open_their_replacements(): void
    {
        $mao = $this->mao();

        $this->actingAs($mao)->get(route('mao.operations', ['schedule_season' => 'Wet']))->assertRedirect(route('mao.dashboard'));
        $this->actingAs($mao)->get(route('management.advisories.index'))->assertRedirect(route('planting-advisories.index', ['show' => 'all']));
        $this->actingAs($mao)->get(route('management.advisories.index', ['run' => 'abc']))
            ->assertRedirect(route('planting-advisories.index', ['show' => 'all', 'run' => 'abc']));
        // Farmers still cannot reach either address.
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('mao.operations'))->assertForbidden();
        $this->actingAs($farmer)->get(route('management.advisories.index'))->assertForbidden();
    }

    public function test_the_mao_sidebar_is_the_eleven_pages_in_order(): void
    {
        $html = $this->html();

        // The menu is in the page twice (side bar and phone drawer): the same eleven labels each time.
        preg_match_all('#<span class="sidebar-label">([^<]+)</span>#', $html, $labels);
        $labels = array_map('html_entity_decode', $labels[1]);
        $this->assertSame(['Dashboard', 'Heat Map', 'Advisories', 'Calendar', 'Records', 'Farmers', 'Field Reports', 'Predictions', 'Reports', 'Announcements', 'Messages'], array_values(array_unique($labels)));
        $this->assertSame(0, count($labels) % 11);
        $nav = [implode(' | ', $labels)];

        foreach (['Chatbot Knowledge', 'Community Feed', 'Farm Calendar', 'Planting Advisories', 'More Tools'] as $gone) {
            $this->assertStringNotContainsString($gone, $nav[0], $gone);
        }
    }

    public function test_records_is_one_item_with_climate_records_and_rice_production_as_tabs(): void
    {
        $mao = $this->mao();

        foreach (['climate-records.index' => 'Climate Records', 'rice-productions.index' => 'Rice Production'] as $route => $active) {
            $html = $this->actingAs($mao)->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<nav class="ic-tabs mb-4" aria-label="Records" data-records-tabs>(.*?)</nav>#s', $html, $m), $route);
            $this->assertStringContainsString('href="'.route('climate-records.index').'"', $m[1]);
            $this->assertStringContainsString('href="'.route('rice-productions.index').'"', $m[1]);
            $this->assertMatchesRegularExpression('#class="ic-tab is-active"\s+aria-current="page"\s*>'.$active.'</a>#', $m[1]);
        }
        // Other list pages have no Records tabs.
        $this->actingAs($mao)->get(route('farmer-profiles.index'))->assertDontSee('data-records-tabs', false);
    }

    public function test_chatbot_knowledge_belongs_to_it_and_the_feed_is_named_announcements_for_staff(): void
    {
        $mao = $this->mao();
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        // Chatbot Knowledge is hidden from the interface (2026-10): its pages open the dashboard and no menu lists it.
        $this->actingAs($mao)->get(route('ai-admin.index', 'topics'))->assertRedirect(route('dashboard'));
        $this->actingAs($it)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Chatbot Knowledge')->assertSee('Announcements');
        // Shown again (CHATBOT_KNOWLEDGE_UI=true), it belongs to IT only.
        config(['iclimate.chatbot_knowledge_ui' => true]);
        $this->actingAs($mao)->get(route('ai-admin.home'))->assertForbidden();
        $this->actingAs($mao)->get(route('ai-admin.index', 'topics'))->assertForbidden();
        $this->actingAs($it)->get(route('ai-admin.index', 'topics'))->assertOk();

        foreach ([$mao, $it] as $user) {
            $this->actingAs($user)->get(route('community-feed.index'))->assertOk()->assertSee('<h1>Announcements</h1>', false);
        }
    }
}
