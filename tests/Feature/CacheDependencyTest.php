<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\CacheScope;
use App\Support\PageCache;
use App\Support\StaleCache;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A data change retires only the caches that read that data (CacheScope), a cache build is never retired by its own
 * writes, and a real change still shows up on the next load.
 */
class CacheDependencyTest extends TestCase
{
    use RefreshDatabase;

    /** Pages / payloads watched in each test. */
    private const WATCHED = ['farmer-dashboard', 'mao-dashboard', 'it-dashboard', 'community-feed', 'calendar', 'advisories-index',
        'system-health', 'crud-user-options'];

    /** @var array<string, int> builds per watched cache */
    private array $builds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        HeatmapArea::query()->delete();
        config([
            'services.weather.latitude' => 14.033, 'services.weather.longitude' => 120.650, 'services.weather.timezone' => 'Asia/Manila',
            'services.weather.cache_minutes' => 5, 'services.weather.forecast_days' => 7, 'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
        ]);
        $this->fakeWeather(28.3);
    }

    // ------------------------------------------------------------------ which caches a change retires

    public function test_a_notification_retires_its_owners_pages_and_the_staff_counts_but_no_other_farmer(): void
    {
        $alice = $this->farmer();
        $bob = $this->farmer();
        $this->warm();
        $aliceKey = \App\Services\Analytics\FarmerDashboardService::cacheKey($alice);
        $bobKey = \App\Services\Analytics\FarmerDashboardService::cacheKey($bob);

        Notification::factory()->create(['user_id' => $alice->id, 'is_read' => false]);

        $this->assertSame(['it-dashboard'], $this->rebuilt(), 'only the IT overview counts notifications for everyone');
        $this->assertNotSame($aliceKey, \App\Services\Analytics\FarmerDashboardService::cacheKey($alice), "Alice's own dashboard is rebuilt");
        $this->assertSame($bobKey, \App\Services\Analytics\FarmerDashboardService::cacheKey($bob), "Bob's dashboard stays cached");
    }

    public function test_marking_a_notification_read_does_not_rescan_for_seed_rows_but_a_new_one_does(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->farmer()->id, 'is_read' => false]);
        $notification = $notification->fresh();
        $this->probe('sample-data:batches');
        $this->probe('sample-data:rice_productions');
        $this->probe('provenance');

        $notification->update(['is_read' => true]);
        $this->probe('sample-data:batches');
        $this->probe('sample-data:rice_productions');
        $this->assertSame(1, $this->builds['sample-data:batches'], 'the read flag is not text the seed scan looks at');
        $this->assertSame(1, $this->builds['sample-data:rice_productions']);
        $this->probe('provenance');
        $this->assertContains('it-dashboard', $this->rebuilt(), 'the unread total did change');
        $this->assertSame(1, $this->builds['provenance'], 'record counts and newest dates did not change');

        Notification::factory()->create(['user_id' => $notification->user_id]);
        $this->probe('sample-data:batches');
        $this->assertSame(2, $this->builds['sample-data:batches'], 'a new row may be a seed row: scanned again');
    }

    public function test_a_feed_reaction_retires_only_the_community_feed(): void
    {
        $post = FeedPost::query()->create(['user_id' => $this->farmer()->id, 'title' => 'Seedlings', 'body' => 'Ready to share.', 'category' => 'Update']);
        $neighbour = $this->farmer();
        $this->warm();

        FeedReaction::query()->create(['feed_post_id' => $post->id, 'user_id' => $neighbour->id, 'type' => 'Like']);

        $this->assertSame(['community-feed'], $this->rebuilt());
    }

    public function test_a_weather_fetch_with_the_same_values_retires_nothing_and_new_values_retire_only_weather_readers(): void
    {
        $row = ExternalWeatherData::query()->create($this->weatherRow(['temperature' => 28.3]));
        $this->warm();

        $row->update(['fetched_at' => now()->addMinutes(3), 'raw_response' => ['fetched' => 'again']]);
        $this->assertSame([], $this->rebuilt(), 'fetched again, nothing a page shows changed');

        $row->update(['temperature' => 31.9, 'fetched_at' => now()->addMinutes(6)]);
        $this->assertSame(['farmer-dashboard', 'mao-dashboard', 'it-dashboard', 'advisories-index', 'system-health'], $this->rebuilt(),
            'the weather changed: every page showing weather is rebuilt, User Management, Community and the calendar are not');
    }

    public function test_the_same_weather_read_back_from_postgresql_as_text_retires_nothing(): void
    {
        $row = ExternalWeatherData::query()->create($this->weatherRow(['temperature' => 24.5, 'humidity' => 91, 'rainfall_mm' => 0, 'wind_speed' => 6.1]));
        // What PostgreSQL hands back for numeric columns: text in the column's scale.
        $stored = (new ExternalWeatherData)->newFromBuilder(array_merge($row->getAttributes(),
            ['temperature' => '24.50', 'humidity' => '91.0', 'rainfall_mm' => '0.00', 'wind_speed' => '6.10']));
        $this->warm();

        // The scheduled refresh saves the same readings as numbers, with a new fetch time and raw payload.
        $stored->update(['temperature' => 24.5, 'humidity' => 91, 'rainfall_mm' => 0, 'wind_speed' => 6.1,
            'fetched_at' => now()->addMinutes(3), 'raw_response' => ['fetched' => 'again']]);
        $this->assertSame([], $this->rebuilt(), '"24.50" and 24.5 are the same reading: nothing to rebuild');

        $stored->update(['temperature' => 25.1]);
        $this->assertSame(['farmer-dashboard', 'mao-dashboard', 'it-dashboard', 'advisories-index', 'system-health'], $this->rebuilt(),
            'a really different temperature retires every weather reader, and only those');
    }

    public function test_numeric_looking_codes_and_text_still_count_as_changes(): void
    {
        $row = ExternalWeatherData::query()->create($this->weatherRow(['location_name' => '024']));
        $stored = (new ExternalWeatherData)->newFromBuilder($row->getAttributes());
        $this->warm();

        $stored->update(['location_name' => '24']);              // a code with a leading zero is not the number 24
        $this->assertContains('system-health', $this->rebuilt());

        $stored->update(['location_name' => 'Lian, Batangas']);  // text is compared as text
        $this->assertContains('system-health', $this->rebuilt());
    }

    public function test_a_farmer_profile_change_retires_that_farmers_pages_and_the_staff_views(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer();
        $this->warm();
        $otherKey = \App\Services\Analytics\FarmerDashboardService::cacheKey($other);
        $ownKey = \App\Services\Analytics\FarmerDashboardService::cacheKey($farmer);

        $farmer->farmerProfile->update(['farm_area' => 3.5]);

        $this->assertSame(['mao-dashboard', 'it-dashboard', 'calendar'], $this->rebuilt());
        $this->assertNotSame($ownKey, \App\Services\Analytics\FarmerDashboardService::cacheKey($farmer));
        $this->assertSame($otherKey, \App\Services\Analytics\FarmerDashboardService::cacheKey($other));
    }

    public function test_a_climate_record_retires_climate_readers_and_report_figures_only(): void
    {
        Cache::put('reports:source-stats', ['climate_records' => 0], 300);
        Cache::put('dashboard:mao-action-items', ['kept'], 300);
        $this->warm();

        ClimateRecord::factory()->create();

        $this->assertSame(['mao-dashboard', 'it-dashboard'], $this->rebuilt());
        $this->assertNull(Cache::get('reports:source-stats'), 'report source figures are recounted');
        $this->assertSame(['kept'], Cache::get('dashboard:mao-action-items'), 'figures that do not read climate records stay');
    }

    public function test_a_risk_recalculation_retires_the_risk_displays(): void
    {
        $area = HeatmapArea::factory()->create(['barangay' => 'Balibago', 'risk_level' => 'Low']);
        $this->warm();

        $area->update(['risk_level' => 'High', 'risk_score' => 0.81]);

        $this->assertSame(['farmer-dashboard', 'mao-dashboard', 'it-dashboard', 'advisories-index'], $this->rebuilt());
    }

    public function test_deactivating_a_user_retires_user_lists_and_shows_at_once(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $farmer = $this->farmer();
        $this->warm();

        $farmer->update(['status' => User::STATUS_INACTIVE]);

        $this->assertContains('crud-user-options', $this->rebuilt());
        $this->actingAs($it)->get(route('users.index'))->assertOk()->assertSee(User::STATUS_INACTIVE);
    }

    // ------------------------------------------------------------------ a build is not retired by its own writes

    public function test_after_the_farmer_refresh_stores_a_weather_snapshot_the_next_page_is_the_stored_copy(): void
    {
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andReturn(['risk_level' => 'No Data'])->shouldReceive('snapshot')->andReturn(['barangays' => []]));
        $farmer = $this->farmer();
        $weatherBefore = CacheScope::token('farmer-dashboard');
        $builtAt = now();

        // The Refresh button: the only farmer request that fetches the weather, so its build stores a snapshot.
        $this->actingAs($farmer)->getJson(route('farmer.dashboard.live', ['refresh' => 1]))->assertOk();
        $this->assertGreaterThan(0, ExternalWeatherData::query()->count(), 'the build fetched the weather and stored a snapshot');
        $this->assertNotSame($weatherBefore, CacheScope::token('farmer-dashboard'), 'that snapshot is new data for everyone else');

        $this->travel(5)->seconds();
        $page = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->viewData('f');

        $this->assertTrue($page['generated_at']->lte($builtAt->copy()->addSecond()), 'the next visit is the copy the refresh stored, not another build');
    }

    public function test_after_the_mao_refresh_stores_a_weather_snapshot_the_next_page_is_the_stored_copy(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $builtAt = now();

        $this->actingAs($mao)->getJson(route('mao.dashboard.live', ['refresh' => 1]))->assertOk();
        $this->assertGreaterThan(0, ExternalWeatherData::query()->count(), 'the refresh stored a weather snapshot while building');

        $this->travel(5)->seconds();
        $page = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->viewData('mao');

        $this->assertTrue($page['generated_at']->lte($builtAt->copy()->addSecond()), 'served from the copy the refresh stored');
    }

    public function test_a_cold_dashboard_build_is_found_by_the_very_next_request(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $first = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->viewData('mao');
        $this->travel(5)->seconds();
        $second = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->viewData('mao');

        $this->assertEquals($first['generated_at'], $second['generated_at']);
    }

    public function test_a_change_by_someone_else_during_a_build_is_never_hidden_behind_the_new_version(): void
    {
        $builds = 0;
        $build = function () use (&$builds) {
            $builds++;
            if ($builds === 1) {
                // Another request saves weather while this build runs: only the shared version moves, not this process's count.
                Cache::forever('cache-scope:weather:version', (int) Cache::get('cache-scope:weather:version', 1) + 1);
            }

            return 'built '.$builds;
        };

        StaleCache::scoped('mao-dashboard', fn (string $token): string => 'probe:'.$token, 600, $build);
        $next = StaleCache::scoped('mao-dashboard', fn (string $token): string => 'probe:'.$token, 600, $build);

        $this->assertSame('built 2', $next, 'the first value may predate that change, so it is rebuilt');
    }

    public function test_a_build_that_writes_data_still_retires_the_other_pages_that_read_it(): void
    {
        $this->warm();

        $writerBuilds = 0;
        $writer = function () use (&$writerBuilds) {
            $writerBuilds++;
            ExternalWeatherData::query()->create($this->weatherRow(['temperature' => 30.0]));   // a snapshot stored while building

            return 'payload';
        };
        PageCache::shared('mao-dashboard', 600, $writer, ['part' => 'writer']);

        $this->assertSame(['farmer-dashboard', 'mao-dashboard', 'it-dashboard', 'advisories-index', 'system-health'], $this->rebuilt(),
            'every other cache that reads weather is rebuilt');
        PageCache::shared('mao-dashboard', 600, $writer, ['part' => 'writer']);
        $this->assertSame(1, $writerBuilds, 'the build that wrote the snapshot is not rebuilt: its value already includes it');
    }

    // ------------------------------------------------------------------ real changes still show

    public function test_a_new_advisory_appears_on_the_next_load_of_a_cached_advisories_page(): void
    {
        $farmer = $this->farmer();
        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk();

        PlantingAdvisory::factory()->create(['title' => 'Drain the paddies before Thursday', 'status' => 'published',
            'target_barangay' => 'Binubusan', 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(3)]);

        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->assertSee('Drain the paddies before Thursday');
    }

    public function test_a_pagasa_check_retires_the_advisories_page_and_leaves_the_rest_of_the_system_warm(): void
    {
        Http::fake(['*pagasa.dost.gov.ph*' => Http::response('<html><body>No bulletins today.</body></html>', 200), '*' => Http::response([], 500)]);
        $this->warm();

        app(PagasaAdvisoryService::class)->fetchAndStore(true, 5);

        $this->assertSame(['advisories-index'], $this->rebuilt(), 'only the page that shows when PAGASA was last checked');
    }

    public function test_refetching_an_unchanged_pagasa_advisory_retires_nothing_but_new_details_do(): void
    {
        $advisory = PlantingAdvisory::factory()->create(['metadata' => ['source' => 'PAGASA', 'fetched_at' => '2026-10-06T08:00:00+08:00']]);
        $advisory = $advisory->fresh();
        $this->warm();

        $advisory->update(['metadata' => array_merge($advisory->metadata, ['fetched_at' => '2026-10-06T08:15:00+08:00'])]);
        $this->assertSame([], $this->rebuilt(), 'only the fetch time inside metadata changed');

        $advisory->update(['metadata' => array_merge($advisory->metadata, ['pagasa_details' => ['rain' => 'Heavy']])]);
        $this->assertContains('advisories-index', $this->rebuilt(), 'new bulletin details are shown, so readers are rebuilt');
    }

    public function test_scheduled_weather_refreshes_keep_unrelated_pages_warm(): void
    {
        Artisan::call('iclimate:refresh-weather-cache');
        // PostgreSQL keeps forecast_date as a date, so the next run finds and updates today's row. SQLite stores the cast
        // value with a time, which the lookup would miss; store it the way PostgreSQL holds it.
        \Illuminate\Support\Facades\DB::table('external_weather_data')->update(['forecast_date' => \Illuminate\Support\Facades\DB::raw('substr(forecast_date, 1, 10)')]);
        $this->warm();

        $this->travel(3)->minutes();
        Artisan::call('iclimate:refresh-weather-cache');            // the 3-minute run, same weather
        $this->assertSame([], $this->rebuilt(), 'the same weather fetched again retires nothing');

        $this->travel(3)->minutes();
        $this->fakeWeather(33.1);
        Artisan::call('iclimate:refresh-weather-cache');            // the weather really changed
        $rebuilt = $this->rebuilt();
        $this->assertContains('farmer-dashboard', $rebuilt);
        $this->assertContains('mao-dashboard', $rebuilt);
        $this->assertNotContains('community-feed', $rebuilt);
        $this->assertNotContains('calendar', $rebuilt);
        $this->assertNotContains('crud-user-options', $rebuilt);
    }

    // ------------------------------------------------------------------ helpers

    /** Build every watched cache once. */
    private function warm(): void
    {
        foreach (self::WATCHED as $consumer) {
            $this->probe($consumer);
        }
    }

    /**
     * Read every watched cache again; returns the ones that had to be rebuilt (in WATCHED order).
     *
     * @param  list<string>  $skip
     * @return list<string>
     */
    private function rebuilt(array $skip = []): array
    {
        $before = $this->builds;
        $rebuilt = [];
        foreach (self::WATCHED as $consumer) {
            if (in_array($consumer, $skip, true)) {
                continue;
            }
            $this->probe($consumer);
            if (($this->builds[$consumer] ?? 0) > ($before[$consumer] ?? 0)) {
                $rebuilt[] = $consumer;
            }
        }

        return $rebuilt;
    }

    /** A cached value under the consumer's real dependencies; counts how often it is built. */
    private function probe(string $consumer): void
    {
        PageCache::shared($consumer, 3600, function () use ($consumer) {
            $this->builds[$consumer] = ($this->builds[$consumer] ?? 0) + 1;

            return $this->builds[$consumer];
        });
    }

    private function farmer(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Binubusan', 'farm_area' => 1.2, 'farm_type' => 'Irrigated']);

        return $user->fresh();
    }

    private function weatherRow(array $values): array
    {
        return $values + ['source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.033, 'longitude' => 120.65,
            'forecast_date' => now('Asia/Manila')->toDateString(), 'humidity' => 82, 'rainfall_mm' => 4.0, 'fetched_at' => now()];
    }

    private function fakeWeather(float $temperature): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = 7;
        $payload = [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => $today->addHours(15)->format('Y-m-d\TH:i'), 'temperature_2m' => $temperature, 'relative_humidity_2m' => 82,
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
                'precipitation_sum' => array_fill(0, $days, 4.0), 'rain_sum' => array_fill(0, $days, 4.0), 'showers_sum' => array_fill(0, $days, 0.0),
                'precipitation_probability_max' => array_fill(0, $days, 60), 'wind_speed_10m_max' => array_fill(0, $days, 12.0),
                'wind_gusts_10m_max' => array_fill(0, $days, 20.0),
            ],
            'hourly' => ['time' => [$today->format('Y-m-d').'T15:00'], 'temperature_2m' => [$temperature], 'relative_humidity_2m' => [82],
                'precipitation_probability' => [60], 'precipitation' => [0.2], 'rain' => [0.2]],
        ];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.open-meteo.com/*' => Http::response($payload, 200), '*' => Http::response([], 500)]);
    }
}
