<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Heat Map (2026-10): the map opens straight away with one panel beside it. The panel shows the municipal summary,
 * then the clicked barangay's risk level, plain explanation and recommended action. [This Week] / [2 Weeks] are rated
 * from the saved daily forecast (BarangayWeekRiskService); the page never calls a provider.
 */
class HeatMapPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        // Any provider call from the page would fail the test's expectations (nothing may be sent).
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    /** One calm forecast day; override rainfall, wind_speed_max or temperature_max. */
    private function day(int $offset, array $values = []): array
    {
        return $values + [
            'date' => CarbonImmutable::now('Asia/Manila')->startOfDay()->addDays($offset)->toDateString(),
            'weather_code' => 3, 'condition' => 'Cloudy', 'rainfall' => 4.0, 'precipitation_probability' => 40,
            'temperature_max' => 31.0, 'temperature_min' => 24.0, 'wind_speed_max' => 12.0,
        ];
    }

    /**
     * Saves a 16-day forecast for every barangay: calm, except the days given per barangay ([offset => values]).
     *
     * @param  array<string, array<int, array<string, mixed>>>  $hazards
     */
    private function storeForecasts(array $hazards = [], ?array $only = null, ?\DateTimeInterface $fetchedAt = null): void
    {
        $fetched = CarbonImmutable::instance($fetchedAt ?? now());
        // Day 0 is the day the forecast was fetched (Philippine time), as the provider returns it.
        $shift = (int) CarbonImmutable::now('Asia/Manila')->startOfDay()->diffInDays($fetched->timezone('Asia/Manila')->startOfDay(), false);
        $records = [];
        foreach ($only ?? LianBarangays::all() as $barangay) {
            $records[$barangay] = [
                'barangay' => $barangay, 'temperature_c' => 28.0, 'fetched_at' => $fetched->toIso8601String(), 'observed_at' => $fetched->toIso8601String(),
                'daily' => array_map(fn (int $i) => $this->day($i + $shift, $hazards[$barangay][$i] ?? []), range(0, 15)),
            ];
        }
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => $records, 'status' => []]);
    }

    private function farmer(string $barangay = 'Prenza', string $language = 'en'): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay, 'preferred_language' => $language]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_type' => 'Irrigated']);

        return $user;
    }

    private function staff(string $role = User::ROLE_MAO): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** The visible words of one panel view (data-heat-map-view="<range>:<summary|barangay>"). */
    private function panelView(string $html, string $key): string
    {
        $found = preg_match('#<section class="hmx-view" data-heat-map-view="'.preg_quote($key, '#').'"[^>]*>(.*?)</section>#s', $html, $m);
        if (! $found && $key !== 'week:summary' && $key !== '2weeks:summary') {
            [$range, $barangay] = explode(':', $key, 2);
            $detail = $this->getJson(route('heat-map.detail', ['range' => $range, 'barangay' => $barangay]))->assertOk()->json('html');
            $found = preg_match('#<section class="hmx-view" data-heat-map-detail="'.preg_quote($key, '#').'"[^>]*>(.*?)</section>#s', (string) $detail, $m);
        }
        $this->assertSame(1, $found, "panel view {$key}");

        // Tags become spaces first, so a label and its value never run together.
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    // ------------------------------------------------------------ layout

    public function test_the_map_opens_first_with_nothing_above_it_and_the_panel_beside_it(): void
    {
        $this->storeForecasts();
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $start = strpos($html, 'data-heat-map ');
        $this->assertNotFalse($start);
        $map = strpos($html, 'id="heatMapCanvas"');
        $panel = strpos($html, 'data-heat-map-panel');
        $this->assertTrue($start < $map && $map < $panel, 'map first, then the panel');

        // Nothing visible sits between the page's start and the map: only the screen-reader page title.
        $before = substr($html, $start, $map - $start);
        $this->assertSame('Heat Map', trim(strip_tags(substr($before, strpos($before, '>') + 1))));

        // The old page's header, overview cards, forecast controls and AI Insight pop-up are gone from this page.
        foreach (['fad-hero', 'data-risk-overview', 'data-risk-forecast', 'data-insight-url', 'Refresh weather', 'ABOUT THIS FORECAST', 'id="barangayRiskMap"'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
    }

    public function test_the_toggle_is_this_week_and_2_weeks_and_the_legend_is_green_yellow_red(): void
    {
        $this->storeForecasts();
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-heat-map-range="week" aria-pressed="true">This Week</button>#', $html);
        $this->assertMatchesRegularExpression('#data-heat-map-range="2weeks" aria-pressed="false">2 Weeks</button>#', $html);

        $this->assertSame(1, preg_match('#<div class="hmx-legend".*?</div>#s', $html, $m));
        $this->assertSame('Farm risk Low Moderate High', trim((string) preg_replace('/\s+/', ' ', strip_tags($m[0]))));
        foreach (['#2e7d32', '#f2b134', '#c62828'] as $color) {
            $this->assertStringContainsString('--hmx-color: '.$color, $m[0]);
        }

        // ?range=2weeks opens on the second range.
        $two = $this->actingAs($this->staff())->get(route('heat-map.index', ['range' => '2weeks']))->assertOk()->getContent();
        $this->assertStringContainsString('data-heat-map data-range="2weeks"', $two);
        $this->assertMatchesRegularExpression('#data-heat-map-view="2weeks:summary"\s*>#', $two);
        $this->assertMatchesRegularExpression('#data-heat-map-view="week:summary"\s+hidden\s*>#', $two);
    }

    // ------------------------------------------------------------ the panel

    public function test_before_a_click_the_panel_is_the_municipal_summary(): void
    {
        $this->storeForecasts([
            'Matabungkay' => [1 => ['rainfall' => 60.0]],                                   // very heavy rain: High
            'San Diego' => [2 => ['wind_speed_max' => 70.0]],                               // storm wind: High
            'Cumba' => [3 => ['rainfall' => 30.0], 4 => ['rainfall' => 26.0], 5 => ['rainfall' => 40.0]], // 3 heavy days: High
            'Prenza' => [2 => ['rainfall' => 30.0]],                                        // one heavy day: Moderate
        ]);
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $summary = $this->panelView($html, 'week:summary');
        $this->assertStringStartsWith('Lian this week 3 barangays are at high risk 1 barangay is at moderate risk 15 barangays are at low risk', $summary);
        $this->assertStringContainsString('Click a barangay on the map to see what it means and what to do.', $summary);
        $this->assertStringNotContainsString('no data', strtolower($summary));
        // The summary is the only view shown at first; every barangay view waits for a click.
        $this->assertSame(1, preg_match_all('#<section class="hmx-view" data-heat-map-view="[^"]+"\s*>#', $html));

        // The map's colours are the same ratings the panel words come from.
        $this->assertSame(1, preg_match('#<script type="application/json" data-heat-map-levels>(.*?)</script>#s', $html, $m));
        $levels = json_decode($m[1], true)['levels'];
        $this->assertCount(19, $levels['week']);
        $this->assertSame(['Cumba', 'Matabungkay', 'San Diego'], array_keys(array_filter($levels['week'], fn ($l) => $l === 'high')));
        $this->assertSame('moderate', $levels['week']['Prenza']);
    }

    public function test_a_barangay_view_has_name_level_meaning_and_action(): void
    {
        $this->storeForecasts(['Matabungkay' => [1 => ['rainfall' => 60.0]], 'Prenza' => [2 => ['rainfall' => 30.0]]]);
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();
        $day = CarbonImmutable::now('Asia/Manila')->addDays(2)->format('D');

        $prenza = $this->panelView($html, 'week:Prenza');
        $this->assertStringContainsString('Prenza Moderate risk This Week', $prenza);
        $this->assertStringContainsString("What it means Heavy rain is expected on {$day}. Low parts of the field may hold water.", $prenza);
        $this->assertStringContainsString('What to do now Clear the drainage canals. Do not spray or apply fertilizer before the rainy days.', $prenza);

        $high = $this->panelView($html, 'week:Matabungkay');
        $this->assertStringContainsString('Matabungkay High risk', $high);
        $this->assertStringContainsString('Fields may flood', $high);
        $this->assertStringContainsString('Clear the drainage canals now.', $high);

        $calm = $this->panelView($html, 'week:Balibago');
        $this->assertStringContainsString('Balibago Low risk', $calm);
        $this->assertStringContainsString('What it means No heavy rain, strong wind or extreme heat is expected in this period.', $calm);
        $this->assertStringContainsString('What to do now Continue normal farm work.', $calm);
    }

    public function test_two_weeks_can_rate_a_barangay_higher_than_this_week(): void
    {
        // A heavy-rain day on day 10 is outside [This Week] and inside [2 Weeks].
        $this->storeForecasts(['Kapito' => [9 => ['rainfall' => 55.0]]]);
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Kapito Low risk This Week', $this->panelView($html, 'week:Kapito'));
        $this->assertStringContainsString('Kapito High risk 2 Weeks', $this->panelView($html, '2weeks:Kapito'));
        $this->assertStringStartsWith('Lian in the next 2 weeks 1 barangay is at high risk', $this->panelView($html, '2weeks:summary'));
        // A day past the coming week is named by its date, since each weekday comes twice in two weeks.
        $this->assertStringContainsString(CarbonImmutable::now('Asia/Manila')->addDays(9)->format('M j'), $this->panelView($html, '2weeks:Kapito'));
    }

    // ------------------------------------------------------------ roles and language

    public function test_staff_see_the_forecast_numbers_and_farmers_read_plain_words(): void
    {
        $this->storeForecasts(['Prenza' => [2 => ['rainfall' => 30.0]]]);

        $staff = $this->panelView($this->actingAs($this->staff(User::ROLE_MAO))->get(route('heat-map.index'))->assertOk()->getContent(), 'week:Prenza');
        $this->assertStringContainsString('Forecast numbers Rain in the period 54.0 mm Wettest day 30.0 mm Strongest wind 12 km/h Hottest day 31.0 °C', $staff);

        $farmer = $this->panelView($this->actingAs($this->farmer())->get(route('heat-map.index'))->assertOk()->getContent(), 'week:Prenza');
        $this->assertStringContainsString('Moderate risk', $farmer);
        $this->assertStringNotContainsString('Forecast numbers', $farmer);
        $this->assertDoesNotMatchRegularExpression('/\d\s?(mm|km\/h|°C)/', $farmer);
    }

    public function test_a_farmer_reads_the_panel_in_their_language_and_sees_their_own_barangay(): void
    {
        $this->storeForecasts(['Prenza' => [0 => ['rainfall' => 60.0]]]);
        $html = $this->actingAs($this->farmer('Prenza', 'fil'))->get(route('heat-map.index'))->assertOk()->getContent();

        $summary = $this->panelView($html, 'week:summary');
        $this->assertStringStartsWith('Lian ngayong linggo 1 barangay ang mataas ang panganib Walang barangay na katamtaman ang panganib 18 barangay ang mababa ang panganib', $summary);
        $this->assertStringContainsString('Ang inyong barangay Prenza Mataas', $summary);
        $this->assertStringContainsString('data-own-barangay="Prenza"', $html);
        $this->assertMatchesRegularExpression('#aria-pressed="true">Ngayong Linggo</button>#', $html);

        $prenza = $this->panelView($html, 'week:Prenza');
        $this->assertStringContainsString('Mataas ang panganib', $prenza);
        $this->assertStringContainsString('Ibig sabihin May inaasahang napakalakas o paulit-ulit na malakas na ulan (ngayon).', $prenza);
        $this->assertStringContainsString('Gawin ngayon Linisin na ngayon ang mga kanal na daluyan ng tubig.', $prenza);

        // The farmer still sees all 19 barangays, never only their own.
        $this->assertSame(0, preg_match_all('#data-heat-map-view="week:(?!summary)[^"]+"#', $html));

        // English farmers get English; staff never get a "your barangay" line.
        $english = $this->panelView($this->actingAs($this->farmer('Prenza', 'en'))->get(route('heat-map.index'))->getContent(), 'week:summary');
        $this->assertStringContainsString('Your barangay Prenza High', $english);
        $staff = $this->actingAs($this->staff())->get(route('heat-map.index'))->getContent();
        $this->assertStringNotContainsString('data-own-barangay', $staff);
        $this->assertStringNotContainsString('Your barangay Prenza', $this->panelView($staff, 'week:summary'));
    }

    public function test_farmers_and_mao_open_the_heat_map_it_goes_to_its_dashboard_and_guests_to_login(): void
    {
        $this->storeForecasts();
        $this->actingAs($this->staff(User::ROLE_IT_EXPERT))->get(route('heat-map.index'))->assertRedirect(route('admin.dashboard'));
        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = $role === User::ROLE_FARMER ? $this->farmer() : $this->staff($role);
            $this->actingAs($user)->get(route('heat-map.index'))->assertOk()->assertSee('id="heatMapCanvas"', false);
        }
        auth()->logout();
        $this->get(route('heat-map.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ saved data only

    public function test_with_nothing_saved_the_page_says_no_data_without_calling_the_provider(): void
    {
        // Staff read English only (the screenshot mixed "Walang datos pa" with an English note).
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();
        $this->assertSame('Lian this week No data yet It appears here after the next update.', $this->panelView($html, 'week:summary'));
        $this->assertStringNotContainsString('Walang datos pa', $html);
        $this->assertStringNotContainsString('low risk', $this->panelView($html, 'week:summary'));
        // Refreshes are scheduler/background work; ordinary navigation never asks the provider.
        $this->assertStringContainsString('data-heat-map-updating', $html);
        $this->assertCount(0, Http::recorded());

        // A Filipino farmer reads it all in Filipino; a second view in the same minute asks the provider nothing.
        $farmer = $this->actingAs($this->farmer('Prenza', 'fil'))->get(route('heat-map.index'))->assertOk()->getContent();
        $this->assertSame('Lian ngayong linggo Walang datos pa Lalabas ito pagkatapos ng susunod na update.', $this->panelView($farmer, 'week:summary'));
        $this->assertCount(0, Http::recorded());

        // With current saved forecasts the page shows them with their time and makes no provider call at all.
        $this->storeForecasts();
        $ready = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Updated: ', $this->panelView($ready, 'week:summary'));
        $this->assertStringNotContainsString('data-heat-map-updating', $ready);
        $this->assertStringNotContainsString('could not be updated', $ready);
        $this->assertCount(0, Http::recorded());
    }

    public function test_an_old_saved_forecast_still_rates_its_remaining_days_and_says_it_is_delayed(): void
    {
        // Saved 2 days ago: its days 2..15 are today onwards, so Matabungkay's heavy rain on its day 3 is tomorrow.
        $this->storeForecasts(['Matabungkay' => [3 => ['rainfall' => 60.0]]], fetchedAt: now()->subDays(2));
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $summary = $this->panelView($html, 'week:summary');
        $this->assertStringStartsWith('Lian this week 1 barangay is at high risk', $summary);
        $this->assertStringContainsString('Updated: '.now('Asia/Manila')->subDays(2)->format('M j'), $summary);
        $this->assertStringContainsString('The forecast could not be updated yet. This is the last saved forecast.', $summary);
        $this->assertStringContainsString('The forecast could not be updated yet.', $this->panelView($html, 'week:Matabungkay'));
        // Only the past days are dropped: the 16 saved days still cover both periods in full.
        $this->assertStringNotContainsString('Based on', $this->panelView($html, 'week:Matabungkay'));
        $this->assertStringNotContainsString('Based on', $this->panelView($html, '2weeks:Matabungkay'));
    }

    public function test_a_forecast_whose_days_have_all_passed_is_no_data_never_low(): void
    {
        $this->storeForecasts(['Prenza' => [2 => ['rainfall' => 30.0]]], fetchedAt: now()->subDays(17));
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertStringContainsString('No data yet', $this->panelView($html, 'week:summary'));
        $this->assertStringContainsString('Prenza No data', $this->panelView($html, 'week:Prenza'));
        $this->assertStringNotContainsString('Low risk', $this->panelView($html, 'week:Prenza'));
    }

    public function test_the_summary_gives_lian_forecast_numbers_from_the_barangays_own_forecasts(): void
    {
        $this->storeForecasts([
            'Matabungkay' => [1 => ['rainfall' => 60.0, 'temperature_max' => 34.2]],
            'Prenza' => [2 => ['rainfall' => 30.0, 'wind_speed_max' => 41.0]],
            'Kapito' => [9 => ['rainfall' => 55.0]],
        ]);
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        // 7 calm days are 7 × 4.0 mm; Matabungkay has 6 × 4.0 + 60.0. Nothing is a separate municipal figure.
        $this->assertStringContainsString('Lian forecast numbers Rain in the period (by barangay) 28.0–84.0 mm Wettest day 60.0 mm Strongest wind 41 km/h Hottest day 34.2 °C', $this->panelView($html, 'week:summary'));
        // [2 Weeks] counts 14 days and Kapito's day 10.
        $this->assertStringContainsString('Rain in the period (by barangay) 56.0–112.0 mm Wettest day 60.0 mm', $this->panelView($html, '2weeks:summary'));

        // Farmers keep plain words: no numbers block.
        $farmer = $this->panelView($this->actingAs($this->farmer())->get(route('heat-map.index'))->getContent(), 'week:summary');
        $this->assertStringNotContainsString('forecast numbers', strtolower($farmer));
        $this->assertDoesNotMatchRegularExpression('/\d\s?(mm|km\/h|°C)/', $farmer);
    }

    public function test_the_map_shows_the_forecast_rating_and_never_the_current_risk_row(): void
    {
        // The current risk (HeatmapArea, used by the dashboards and /api/lian/risk-map) says High for Prenza…
        \App\Models\HeatmapArea::query()->updateOrCreate(['barangay' => 'Prenza'], [
            'latitude' => 14.0128685, 'longitude' => 120.6398701, 'risk_level' => 'High', 'risk_type' => 'Drought', 'risk_score' => 0.8,
            'rainfall_status' => 'Low rainfall', 'description' => 'Computed test rating.', 'risk_computed_at' => now(),
        ]);
        // …while its saved forecast is calm for both periods.
        $this->storeForecasts();

        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Prenza Low risk This Week', $this->panelView($html, 'week:Prenza'));

        $api = collect($this->getJson('/api/lian/risk-map')->assertOk()->json('barangays'))->firstWhere('barangay', 'Prenza');
        $this->assertSame('High', $api['risk_level']);
    }

    public function test_a_barangay_without_a_saved_forecast_is_no_data_never_low(): void
    {
        $this->storeForecasts(only: array_values(array_diff(LianBarangays::all(), ['Luyahan'])));
        $html = $this->actingAs($this->staff())->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertStringContainsString('18 barangays are at low risk 1 barangay has no data yet', $this->panelView($html, 'week:summary'));
        $luyahan = $this->panelView($html, 'week:Luyahan');
        $this->assertStringContainsString('Luyahan No data', $luyahan);
        $this->assertStringContainsString('There is no saved forecast for this barangay yet.', $luyahan);
        $this->assertStringNotContainsString('Low risk', $luyahan);
        // The legend gains its grey "No data" entry only when it is needed.
        $this->assertSame(1, preg_match('#<div class="hmx-legend".*?</div>#s', $html, $m));
        $this->assertStringContainsString('No data', strip_tags($m[0]));
    }

    public function test_the_farmer_home_badge_is_the_heat_maps_this_week_rating(): void
    {
        $this->storeForecasts(['Prenza' => [1 => ['rainfall' => 60.0]]]);
        $farmer = $this->farmer('Prenza');

        $home = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<p class="fh-risk-badge is-danger">High</p>#', $home);
        $this->assertStringContainsString('Prenza High risk', $this->panelView($this->actingAs($farmer)->get(route('heat-map.index'))->getContent(), 'week:Prenza'));

        $this->assertSame('high', app(BarangayWeekRiskService::class)->forBarangay('prenza')['level']);
        $this->assertNull(app(BarangayWeekRiskService::class)->forBarangay('Not A Barangay'));
    }
}
