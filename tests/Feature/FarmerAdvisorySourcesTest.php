<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\LocalFarmGuidanceService;
use App\Services\Advisories\PagasaAdvisoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesBarangayWeather;
use Tests\TestCase;

class FarmerAdvisorySourcesTest extends TestCase
{
    use FakesBarangayWeather;
    use RefreshDatabase;

    private const SOURCE = 'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-04 23:00', 'Asia/Manila'));
        Cache::flush();
        config(['services.pagasa.regional_forecast_urls' => [self::SOURCE], 'services.pagasa.weekly_outlook_url' => null]);
        Http::preventStrayRequests();
        User::factory()->maoPersonnel()->create();
    }

    public static function coverage(): array
    {
        return [
            'Lian' => ['Rainfall Advisory: Lian, Batangas.', 'municipality'],
            'San Diego' => ['Thunderstorm Advisory: San Diego, Lian.', 'barangay'],
            'Batangas province' => ['Rainfall Advisory: Batangas Province.', 'province'],
            'CALABARZON' => ['Weather Advisory: CALABARZON including Batangas.', 'regional'],
            'Region IV-A' => ['Weather Advisory: Region IV-A.', 'regional'],
            'unrelated province' => ['Thunderstorm Advisory: Quezon Province.', null],
            'same barangay name elsewhere' => ['Thunderstorm Advisory: San Diego, Quezon.', null],
            'restricted municipality list' => ['Thunderstorm Advisory: Batangas (Nasugbu, Tuy).', null],
            'explicit exclusion' => ['Weather Advisory: CALABARZON excluding Batangas.', null],
        ];
    }

    #[DataProvider('coverage')]
    public function test_official_location_coverage(string $text, ?string $scope): void
    {
        Http::fake([self::SOURCE => Http::response('<body>'.$text.'</body>')]);
        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertTrue($result['complete']);
        $this->assertSame($scope ? 1 : 0, $result['advisories_created']);
        if ($scope) {
            $advisory = PlantingAdvisory::firstOrFail();
            $this->assertSame($scope, $advisory->metadata['scope_type']);
            $this->assertTrue($advisory->isOfficialPagasa());
            $this->assertSame(self::SOURCE, $advisory->source_url);
            $this->assertNull($advisory->published_at, 'Fetch time is not publication time.');
        }
    }

    public function test_embedded_batangas_forecast_is_read_without_using_the_visible_default_province(): void
    {
        $regional = ['weather' => ['issued_date' => '2026-10-04 17:00:00', 'valid_date' => '2026-10-05 17:00:00'],
            'provincial' => ['data' => [
                '130000000' => ['name' => 'Metro Manila', 'outlook' => [['day_description' => 'Unrelated default forecast']]],
                '41000000' => ['name' => 'Batangas', 'outlook' => [['day_description' => 'Partly cloudy with isolated rainshowers.']]],
            ]]];
        Http::fake([self::SOURCE => Http::response('<select><option>Batangas</option></select><div>Unrelated default forecast</div><script>let regional = '.json_encode($regional).';</script>')]);
        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertSame(1, $result['advisories_created']);
        $row = PlantingAdvisory::firstOrFail();
        $this->assertSame('Partly cloudy with isolated rainshowers.', $row->summary);
        $this->assertSame('2026-10-04 09:00:00', $row->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 09:00:00', $row->valid_until->format('Y-m-d H:i:s'));
    }

    public function test_expired_bulletin_is_not_revived_and_duplicates_remain_one_row_across_hours(): void
    {
        $current = '<div>Thunderstorm Advisory No. 5. Issued at: 10:00 PM, 04 October 2026. Batangas Province within the next 2 hours.</div>';
        Http::fake([self::SOURCE => Http::sequence()
            ->push('<div>Thunderstorm Advisory No. 4. Issued at: 8:00 PM, 04 October 2026. Batangas Province within the next 2 hours.</div>')
            ->push($current)->push($current)]);
        $this->assertSame(0, app(PagasaAdvisoryService::class)->fetchAndStore(true)['advisories_created']);
        $this->assertSame(1, app(PagasaAdvisoryService::class)->fetchAndStore(true)['advisories_created']);
        $this->travel(10)->minutes();
        $this->assertSame(1, app(PagasaAdvisoryService::class)->fetchAndStore(true)['advisories_skipped_as_duplicates']);
        $this->assertSame(1, PlantingAdvisory::count());
        $this->assertSame('2026-10-04 14:00:00', PlantingAdvisory::first()->published_at->format('Y-m-d H:i:s'));
    }

    public function test_bulletin_replacement_can_remove_lian_from_its_coverage(): void
    {
        Http::fake([self::SOURCE => Http::sequence()
            ->push('<div>Thunderstorm Advisory No. 5. Issued at: 10:00 PM, 04 October 2026. Batangas (Lian, Tuy) within the next 2 hours.</div>')
            ->push('<div>Thunderstorm Advisory No. 6. Issued at: 10:30 PM, 04 October 2026. Batangas (Tuy) within the next 2 hours.</div>')]);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertSame(0, PlantingAdvisory::active()->count());
        $this->assertSame('archived', PlantingAdvisory::first()->status);
    }

    public function test_identical_bulletin_on_multiple_official_pages_is_stored_once(): void
    {
        config(['services.pagasa.regional_forecast_urls' => [self::SOURCE, 'https://bagong.pagasa.dost.gov.ph/regional-forecast/slprsd']]);
        Http::fake(['*' => Http::response('Thunderstorm Advisory No. 5. Issued at: 10:00 PM, 04 October 2026. Batangas Province within the next 2 hours.')]);
        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertSame(1, $result['advisories_created']);
        $this->assertSame(1, $result['advisories_skipped_as_duplicates']);
        $this->assertSame(1, PlantingAdvisory::count());
    }

    public function test_weekly_outlook_uses_current_nationwide_sentence_not_other_provinces_or_future_periods(): void
    {
        config(['services.pagasa.regional_forecast_urls' => [], 'services.pagasa.regional_forecast_url' => null,
            'services.pagasa.weekly_outlook_url' => 'https://pagasa.dost.gov.ph/weather/weather-outlook-weekly']);
        Http::fake(['*' => Http::response('<div class="article-content">Issued at: 12:00 PM, 02 October 2026. Valid until: 12:00 PM, 09 October 2026.
            <table><tr><td>October 3-5</td><td>Heavy rain in Visayas. The rest of the country will have isolated rainshowers.</td></tr>
            <tr><td>October 6-7</td><td>CALABARZON future forecast.</td></tr></table></div>')]);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertSame(1, PlantingAdvisory::count());
        $row = PlantingAdvisory::first();
        $this->assertSame('The rest of the country will have isolated rainshowers.', $row->summary);
        $this->assertSame('2026-10-05 15:59:59', $row->valid_until->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-02 04:00:00', $row->published_at->format('Y-m-d H:i:s'));
    }

    public function test_agricultural_validity_dates_are_not_presented_as_publication_time(): void
    {
        config(['services.pagasa.regional_forecast_urls' => ['https://bagong.pagasa.dost.gov.ph/ten-day-regional-agri-weather']]);
        Http::fake(['*' => Http::response('<div class="article-content">DEKAD NO. 28 1 - 10 OCTOBER 2026
            <div class="region-list">CALABARZON: Forecast rainfall and temperature outlook.</div></div>')]);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $row = PlantingAdvisory::firstOrFail();
        $this->assertNull($row->published_at);
        $this->assertSame('2026-09-30 16:00:00', $row->valid_from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10 15:59:59', $row->valid_until->format('Y-m-d H:i:s'));
    }

    public function test_structure_change_is_reported_as_a_parser_failure_not_successful_empty_results(): void
    {
        Http::fake(['*' => Http::response('<nav>Batangas</nav><h1>New PAGASA portal structure</h1>')]);
        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertFalse($result['complete']);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('structure is unrecognized', $result['errors'][0]);
    }

    public function test_restricted_barangay_coverage_does_not_leak_to_other_farmers(): void
    {
        Http::fake(['*' => Http::response('Rainfall Advisory: Matabungkay and Binubusan, Lian.')]);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $this->assertSame(1, PlantingAdvisory::active()->forBarangay('Matabungkay')->count());
        $this->assertSame(1, PlantingAdvisory::active()->forBarangay('Binubusan')->count());
        $this->assertSame(0, PlantingAdvisory::active()->forBarangay('San Diego')->count());
        $this->assertSame(0, PlantingAdvisory::active()->forBarangay(null)->count());
    }

    public function test_pagasa_named_row_without_a_genuine_source_url_is_not_counted_as_official(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        PlantingAdvisory::create(['title' => 'Unverified old row', 'content' => 'Weather', 'type' => 'Climate',
            'source' => PlantingAdvisory::SOURCE_PAGASA_LEGACY, 'source_url' => 'https://example.com/pagasa',
            'target_scope' => 'municipality', 'status' => 'published', 'valid_until' => now()->addHour(), 'posted_by' => $farmer->id]);
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()
            ->assertDontSee('Official PAGASA Advisory')->assertSee('Unverified source');
    }

    public function test_forecast_unavailable_is_distinct_from_pagasa_connected(): void
    {
        Http::fake([self::SOURCE => Http::response('Weather Advisory: Batangas Province.'),
            'api.open-meteo.com/*' => Http::response('unavailable', 503)]);
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        $this->actingAs($farmer)->postJson(route('planting-advisories.refresh-pagasa'))->assertOk();
        $this->get(route('planting-advisories.index'))->assertOk()->assertSee('PAGASA Connected')
            ->assertSee('Current forecast data is temporarily unavailable.')->assertSee('Official PAGASA Advisory')
            ->assertDontSee('No Active Advisories for Lian');
    }

    public function test_successful_forecast_creates_local_guidance_without_official_bulletins_or_a_barangay(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        $this->fakeSources('Weather Advisory: Quezon Province.');
        $this->actingAs($farmer)->postJson(route('planting-advisories.refresh-pagasa'))
            ->assertOk()->assertJsonPath('weather.available', true);
        $page = $this->get(route('planting-advisories.index'))->assertOk();
        $page->assertSee('iClimate Local Advisory')->assertSee('No active official advisories currently apply to Lian.')
            ->assertDontSee('No Active Advisories for Lian');
        $this->assertSame(0, PlantingAdvisory::fromPagasaOnline()->count());
        $this->assertGreaterThan(0, PlantingAdvisory::active()->count());
    }

    public function test_pagasa_failure_is_not_rendered_as_no_advisories_and_local_guidance_remains(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        $this->fakeSources('down', 503);
        $this->actingAs($farmer)->postJson(route('planting-advisories.refresh-pagasa'))->assertOk();
        $this->get(route('planting-advisories.index'))->assertOk()
            ->assertSee('PAGASA advisories are temporarily unavailable. Showing the latest available local guidance.')
            ->assertSee('iClimate Local Advisory')->assertDontSee('No Active Advisories for Lian');
    }

    public function test_both_sources_unavailable_returns_diagnostics_and_does_not_show_empty_success(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        $this->actingAs($farmer)->postJson(route('planting-advisories.refresh-pagasa'))->assertStatus(503);
        $this->get(route('planting-advisories.index'))->assertOk()->assertSee('Advisory information is temporarily unavailable.')
            ->assertDontSee('No Active Advisories for Lian');
        $this->assertNotEmpty(Cache::get('pagasa-advisories:status')['errors']);
    }

    public function test_first_visit_checks_in_the_background_instead_of_displaying_a_confirmed_empty_state(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => null]);
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()
            ->assertSee('data-fad-auto-refresh', false)->assertSee('Checking the latest advisories')
            ->assertDontSee('No Active Advisories for Lian');
        Http::assertNothingSent();
    }

    public function test_barangay_guidance_is_prioritized_and_manual_refresh_uses_shared_cooldown(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);
        $this->fakeSources('Weather Advisory: Batangas Province.');
        $this->actingAs($farmer)->postJson(route('planting-advisories.refresh-pagasa'))->assertOk();
        $before = PlantingAdvisory::count();
        $requests = count(Http::recorded());
        $this->postJson(route('planting-advisories.refresh-pagasa'))->assertOk();
        $this->assertSame($requests, count(Http::recorded()));
        $this->assertSame($before, PlantingAdvisory::count());
        $this->get(route('planting-advisories.index'))->assertOk()
            ->assertSeeInOrder(['San Diego Farm Weather Guidance', 'Official PAGASA Updates', 'Weather Advisory', 'Lian-wide Guidance']);
    }

    public function test_warm_municipal_cache_still_starts_the_first_registered_barangay_refresh(): void
    {
        $this->fakeSources('Weather Advisory: Batangas Province.');
        app(PagasaAdvisoryService::class)->fetchAndStore();
        app(LocalFarmGuidanceService::class)->refresh();
        $requests = count(Http::recorded());
        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()
            ->assertSee('data-fad-auto-refresh', false);
        $this->assertSame($requests, count(Http::recorded()), 'A GET still only reads prepared data.');
        $this->postJson(route('planting-advisories.refresh-pagasa'))->assertOk();
        $this->assertSame($requests + 1, count(Http::recorded()), 'Only the missing barangay weather is fetched.');
        $this->get(route('planting-advisories.index'))->assertOk()->assertSee('San Diego Farm Weather Guidance')
            ->assertDontSee('data-fad-auto-refresh', false);
    }

    public function test_target_barangay_filter_keeps_municipal_provincial_and_regional_advice(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);
        $this->fakeSources('Thunderstorm Advisory: San Diego, Lian.');
        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        foreach (['municipality', 'province', 'regional'] as $scope) {
            PlantingAdvisory::create(['title' => $scope.' guidance', 'content' => 'Guidance', 'type' => 'Climate',
                'advisory_type' => 'climate', 'target_scope' => 'municipality', 'status' => 'published',
                'valid_from' => now()->subHour(), 'valid_until' => now()->addHour(), 'metadata' => ['scope_type' => $scope],
                'posted_by' => $farmer->id]);
        }
        $this->actingAs($farmer)->get(route('planting-advisories.index', ['target_barangay' => 'San Diego']))->assertOk()
            ->assertSee('Thunderstorm Advisory')->assertSee('municipality guidance')->assertSee('province guidance')->assertSee('regional guidance');
    }

    public function test_refresh_updates_the_existing_daily_guidance_from_changed_forecast_inputs(): void
    {
        $rain = 5;
        Http::fake([self::SOURCE => Http::response('Weather Advisory: Quezon Province.'),
            'api.open-meteo.com/*' => function () use (&$rain) {
                return Http::response(['daily' => ['time' => [now('Asia/Manila')->toDateString()],
                    'temperature_2m_max' => [31], 'temperature_2m_min' => [24],
                    'precipitation_sum' => [$rain], 'precipitation_probability_max' => [60]]]);
            }]);
        app(LocalFarmGuidanceService::class)->refresh();
        $first = PlantingAdvisory::firstOrFail();
        $this->assertSame(5.0, (float) $first->metadata['weather_basis']['expected_rainfall_mm']);
        $rain = 18;
        $this->travel(11)->minutes();
        app(LocalFarmGuidanceService::class)->refresh();
        $this->assertSame(1, PlantingAdvisory::count());
        $this->assertSame(18.0, (float) $first->fresh()->metadata['weather_basis']['expected_rainfall_mm']);
        $this->assertStringContainsString('18.0 mm', $first->fresh()->message);
        $this->assertSame(now()->toIso8601String(), $first->fresh()->metadata['fetched_at']);
    }

    private function fakeSources(string $bulletin, int $status = 200): void
    {
        Http::fake([
            self::SOURCE => Http::response('<body>'.$bulletin.'</body>', $status),
            // Batch-aware, like the provider: the 19-barangay request gets one result per coordinate.
            'api.open-meteo.com/*' => fn ($request) => $this->openMeteoAnswer($request, fn () => [
                'current' => ['time' => now('Asia/Manila')->format('Y-m-d\TH:i'), 'temperature_2m' => 29,
                    'precipitation' => 0.1, 'relative_humidity_2m' => 80, 'wind_speed_10m' => 10],
                'daily' => ['time' => [now('Asia/Manila')->toDateString()], 'temperature_2m_max' => [31],
                    'temperature_2m_min' => [24], 'precipitation_sum' => [5], 'precipitation_probability_max' => [60]],
            ]),
        ]);
    }
}
