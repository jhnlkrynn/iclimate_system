<?php

namespace Tests\Feature;

use App\Jobs\RefreshBarangayRiskMap;
use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\Risk\BarangayRiskMapService;
use App\Support\CacheScope;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesBarangayWeather;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

/**
 * The Heat Map's whole data flow (2026-10-07 fix): shared Open-Meteo barangay weather → saved daily forecast and
 * current reading → [This Week] / [2 Weeks] ratings and the current HeatmapArea ratings → page and API.
 *
 * The bug: with no scheduler running (or a cleared cache) the shared weather store was empty and nothing on the
 * Heat Map's path ever filled it, so all 19 barangays stayed grey "No data" for every role.
 */
class HeatMapDataFlowTest extends TestCase
{
    use FakesBarangayWeather;
    use PreparesPredictions;
    use RefreshDatabase;

    private bool $providerUp = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00', 'Asia/Manila'));
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/*' => fn ($request) => $this->openMeteoAnswer($request, fn (float $lat, float $lon) => $this->payloadFor($lat, $lon))]);
        // The monthly rainfall the current risk engine needs, as the scheduled prediction warm-up stores it.
        $this->preparePredictions();
    }

    /** Barangay name at a requested point. */
    private function nameAt(float $latitude, float $longitude): ?string
    {
        $name = collect(LianBarangays::coordinates())->search(fn (array $point) => abs($point[0] - $latitude) < 1e-6 && abs($point[1] - $longitude) < 1e-6);

        return $name === false ? null : $name;
    }

    /**
     * 16 days from the provider: calm, except very heavy rain at Matabungkay tomorrow, one heavy-rain day at Prenza
     * in two days and very heavy rain at Kapito on day 10 (only inside [2 Weeks]).
     */
    private function payloadFor(float $latitude, float $longitude): ?array
    {
        if (! $this->providerUp) {
            return null;
        }
        $name = $this->nameAt($latitude, $longitude);
        $hazards = ['Matabungkay' => [1 => 60.0], 'Prenza' => [2 => 30.0], 'Kapito' => [9 => 55.0]];
        $start = now('Asia/Manila')->startOfHour();
        $days = collect(range(0, 15))->map(fn (int $i) => now('Asia/Manila')->startOfDay()->addDays($i)->toDateString())->all();

        return [
            'current' => [
                'time' => now('Asia/Manila')->format('Y-m-d\TH:i'),
                'temperature_2m' => 29.5, 'relative_humidity_2m' => 80, 'precipitation' => 0.0, 'rain' => 0.0,
                'weather_code' => 3, 'is_day' => 1, 'wind_speed_10m' => 10.0,
            ],
            'hourly' => [
                'time' => collect(range(0, 47))->map(fn (int $h) => $start->copy()->addHours($h)->format('Y-m-d\TH:i'))->all(),
                'precipitation_probability' => array_fill(0, 48, 40), 'precipitation' => array_fill(0, 48, 0.1),
                'rain' => array_fill(0, 48, 0.1), 'temperature_2m' => array_fill(0, 48, 29),
                'relative_humidity_2m' => array_fill(0, 48, 80), 'wind_speed_10m' => array_fill(0, 48, 10),
            ],
            'daily' => [
                'time' => $days,
                'weather_code' => array_fill(0, 16, 3),
                'precipitation_sum' => array_map(fn (int $i) => $hazards[$name][$i] ?? 4.0, range(0, 15)),
                'rain_sum' => array_map(fn (int $i) => $hazards[$name][$i] ?? 4.0, range(0, 15)),
                'temperature_2m_max' => array_fill(0, 16, 31.0),
                'temperature_2m_min' => array_fill(0, 16, 24.0),
                'precipitation_probability_max' => array_fill(0, 16, 40),
                'wind_speed_10m_max' => array_fill(0, 16, 12.0),
            ],
        ];
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    /** @return array<string, array<string, string>> the map colours embedded in the page, by range */
    private function levels(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-heat-map-levels>(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true)['levels'];
    }

    private function summary(string $html, string $range = 'week'): string
    {
        $this->assertSame(1, preg_match('#<section class="hmx-view" data-heat-map-view="'.$range.':summary"[^>]*>(.*?)</section>#s', $html, $m));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    // ------------------------------------------------------------ the root cause

    public function test_an_empty_weather_store_is_filled_by_one_shared_refresh_and_the_next_view_is_coloured(): void
    {
        $user = $this->mao();

        // First view: nothing saved yet. The page answers at once, honestly, and says new weather is on its way.
        $first = $this->actingAs($user)->get(route('heat-map.index'))->assertOk()->getContent();
        $this->assertStringContainsString('No data yet It appears here after the next update.', $this->summary($first));
        $this->assertStringContainsString('data-heat-map-updating', $first);

        // After that response: ONE batched request for all 19 barangays (the shared service), nothing per barangay.
        $this->assertCount(1, Http::recorded());
        $this->assertCount(19, $this->requestedPoints(Http::recorded()[0][0]));

        // The same refresh rated the current risk and saved it in one pass: 19 rows, one calculation time.
        $areas = HeatmapArea::query()->get();
        $this->assertCount(19, $areas);
        $this->assertEqualsCanonicalizing(LianBarangays::all(), $areas->pluck('barangay')->all());
        $this->assertSame([now()->toIso8601String()], $areas->map(fn (HeatmapArea $a) => $a->risk_computed_at->toIso8601String())->unique()->values()->all());
        $this->assertEmpty($areas->reject(fn (HeatmapArea $a) => in_array($a->risk_level, ['Low', 'Moderate', 'High', 'Severe'], true)));

        // Next view: every polygon has its colour from the saved forecast, and no further provider request is made.
        $second = $this->actingAs($user)->get(route('heat-map.index'))->assertOk()->getContent();
        $levels = $this->levels($second);
        $this->assertCount(19, $levels['week']);
        $this->assertCount(19, $levels['2weeks']);
        $this->assertNotContains('nodata', $levels['week']);
        $this->assertSame('high', $levels['week']['Matabungkay']);
        $this->assertSame('moderate', $levels['week']['Prenza']);
        $this->assertSame('low', $levels['week']['Kapito']);
        $this->assertSame('high', $levels['2weeks']['Kapito']);
        $this->assertStringNotContainsString('data-heat-map-updating', $second);
        $this->assertStringStartsWith('Lian this week 1 barangay is at high risk 1 barangay is at moderate risk 17 barangays are at low risk', $this->summary($second));
        $this->assertStringContainsString('Updated: 9:00 AM', $this->summary($second));
        $this->assertCount(1, Http::recorded());
    }

    public function test_many_views_while_the_weather_is_due_make_one_provider_request(): void
    {
        $this->providerUp = false;
        foreach (range(1, 4) as $_) {
            $this->actingAs($this->mao())->get(route('heat-map.index'))->assertOk();
        }

        // The batched request failed, so each barangay was tried once on its own — and the next three views made none.
        $this->assertCount(1 + 19, Http::recorded());
        // Nothing to rate from: still No data, never Low, and no HeatmapArea row invented.
        $this->assertSame(['nodata'], array_values(array_unique($this->levels($this->actingAs($this->mao())->get(route('heat-map.index'))->getContent())['week'])));
        $this->assertSame(0, HeatmapArea::query()->computed()->count());
    }

    // ------------------------------------------------------------ keeping the last verified data

    public function test_a_failed_refresh_keeps_the_last_forecast_and_current_risk_with_their_own_times(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();
        $ratedAt = HeatmapArea::query()->value('risk_computed_at')->toIso8601String();

        // A day and a half later the provider is down and nothing was refreshed meanwhile.
        $this->travel(30)->hours();
        $this->providerUp = false;
        $this->actingAs($this->mao())->get(route('heat-map.index'))->assertOk();
        $html = $this->actingAs($this->mao())->get(route('heat-map.index'))->assertOk()->getContent();

        // The saved forecast's remaining days still rate every barangay: tomorrow's rain at Matabungkay is today's now.
        $levels = $this->levels($html);
        $this->assertNotContains('nodata', $levels['week']);
        $this->assertSame('high', $levels['week']['Matabungkay']);
        $summary = $this->summary($html);
        $this->assertStringContainsString('Updated: Oct 7, 9:00 AM', $summary);
        $this->assertStringContainsString('The forecast could not be updated yet. This is the last saved forecast.', $summary);

        // The current risk keeps its last verified ratings and their own calculation time, never No Data.
        $api = $this->getJson('/api/lian/risk-map')->assertOk();
        $this->assertCount(19, $api->json('barangays'));
        foreach ($api->json('barangays') as $entry) {
            $this->assertContains($entry['risk_level'], ['Low', 'Moderate', 'High', 'Severe'], $entry['barangay']);
            $this->assertSame($ratedAt, $entry['risk_calculated_at'], $entry['barangay']);
        }
    }

    // ------------------------------------------------------------ the API

    public function test_the_risk_map_api_returns_the_19_barangays_with_risk_weather_times_and_source(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();

        $response = $this->getJson('/api/lian/risk-map')->assertOk()->assertJsonPath('status', 'ok');
        $barangays = collect($response->json('barangays'));

        $this->assertCount(19, $barangays);
        $this->assertEqualsCanonicalizing(LianBarangays::all(), $barangays->pluck('barangay')->all());
        foreach ($barangays as $entry) {
            $this->assertContains($entry['risk_level'], ['Low', 'Moderate', 'High', 'Severe'], $entry['barangay']);
            $this->assertSame(now()->toIso8601String(), $entry['risk_calculated_at']);
            $this->assertSame('fresh', $entry['weather']['status']);
            $this->assertEquals(29.5, $entry['weather']['temperature_c']);
            $this->assertEquals(80, $entry['weather']['humidity_percent']);
            $this->assertSame('Open-Meteo', $entry['weather']['provider']);
            $this->assertSame(now()->toIso8601String(), $entry['weather']['fetched_at']);
        }
        $this->assertSame(19, $response->json('weather_summary.current'));
        $this->assertSame('Open-Meteo', $response->json('weather_summary.provider'));
    }

    // ------------------------------------------------------------ scheduler, queue and caches

    public function test_the_risk_reconciliation_and_the_queued_job_rerate_without_another_provider_request(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();
        $this->assertCount(1, Http::recorded());

        // :05 / :35, a few minutes after the weather command: re-rated from the same shared weather.
        $this->travel(4)->minutes();
        $this->artisan('iclimate:refresh-barangay-risk')->expectsOutputToContain('19 barangay(s)')->assertSuccessful();
        $this->assertSame(now()->toIso8601String(), HeatmapArea::query()->first()->risk_computed_at->toIso8601String());

        $this->travel(1)->minutes();
        app()->call([new RefreshBarangayRiskMap, 'handle']);
        $this->assertSame(now()->toIso8601String(), HeatmapArea::query()->first()->risk_computed_at->toIso8601String());

        $this->assertCount(1, Http::recorded(), 'Re-rating must reuse the shared weather, not ask Open-Meteo again.');
    }

    public function test_rating_retires_risk_caches_only(): void
    {
        $risk = CacheScope::token('dashboard:high-risk-areas');
        $advisories = CacheScope::token('advisories-summary');

        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();

        $this->assertNotSame($risk, CacheScope::token('dashboard:high-risk-areas'));
        $this->assertSame($advisories, CacheScope::token('advisories-summary'));
    }

    public function test_mao_and_farmer_see_the_same_ratings_and_time_for_the_same_barangay(): void
    {
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Matabungkay', 'preferred_language' => 'en']);
        \App\Models\FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Matabungkay']);

        $mao = $this->actingAs($this->mao())->get(route('heat-map.index'))->assertOk()->getContent();
        $mine = $this->actingAs($farmer)->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertSame($this->levels($mao), $this->levels($mine));
        $this->assertStringContainsString('Your barangay Matabungkay High', $this->summary($mine));
        $this->assertStringContainsString('data-own-barangay="Matabungkay"', $mine);
        preg_match('#Updated: [^<]+#', $this->summary($mao), $maoTime);
        preg_match('#Updated: [^<]+#', $this->summary($mine), $farmerTime);
        $this->assertSame($maoTime, $farmerTime);
        $this->assertCount(1, Http::recorded());
    }
}
