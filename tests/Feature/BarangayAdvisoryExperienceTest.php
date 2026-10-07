<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\LocalFarmGuidanceService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesBarangayWeather;
use Tests\TestCase;

class BarangayAdvisoryExperienceTest extends TestCase
{
    use FakesBarangayWeather;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 00:46', 'Asia/Manila'));
        Cache::flush();
        Http::preventStrayRequests();
        User::factory()->maoPersonnel()->create();
    }

    public function test_san_diego_advisory_uses_the_exact_normalized_provider_values(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->weatherPayload())]);
        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);

        $result = app(LocalFarmGuidanceService::class)->refresh('San Diego');

        $this->assertSame(29.4, $result['barangay_weather']['temperature_c']);
        $this->assertSame(82.0, $result['barangay_weather']['humidity_percent']);
        $this->assertSame(9.2, $result['barangay_weather']['rainfall_24h_mm']);
        $this->assertSame(76.0, $result['barangay_weather']['rain_probability_percent']);

        [$latitude, $longitude] = LianBarangays::coordinateFor('San Diego');
        $this->assertSame($latitude, $result['barangay_weather']['latitude']);
        $this->assertSame($longitude, $result['barangay_weather']['longitude']);

        $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()
            ->assertSeeInOrder(['For your barangay', 'San Diego, Lian, Batangas', 'Local Farming Advisory', 'Barangay San Diego, Lian', 'Current conditions', 'What is happening', 'What farmers can do', 'Based on: current weather + forecast data for San Diego'])
            ->assertSee('29.4°C')->assertSee('82%')->assertSee('9.2 mm')->assertSee('76%')
            ->assertSee('Open-Meteo + iClimate Local Guidance');

        $stored = PlantingAdvisory::query()->where('generation_key', 'local-current:San Diego')->firstOrFail();
        $this->assertStringContainsString('29.4 °C', $stored->summary);
        $this->assertStringContainsString('9.2 mm', $stored->summary);
        $this->assertSame('barangay', $stored->metadata['scope_type']);
        $this->assertFalse($stored->metadata['is_official']);
    }

    public function test_each_tested_barangay_uses_its_own_polygon_verified_coordinate_request(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn ($request) => $this->openMeteoAnswer($request, fn () => $this->weatherPayload())]);
        $barangays = ['San Diego', 'Binubusan', 'Bungahan', 'Malaruhatan'];

        // One batched request carries every barangay's own coordinate; the asked-for barangay goes first.
        $result = app(LianBarangayWeatherService::class)->latestForBarangay('San Diego', refresh: true);

        $this->assertCount(1, Http::recorded());
        $points = $this->requestedPoints(Http::recorded()[0][0]);
        $this->assertCount(19, $points);
        $this->assertCount(19, collect($points)->map(fn (array $point) => implode(',', $point))->unique());
        $this->assertEquals(LianBarangays::coordinateFor('San Diego'), $points[0]);
        $this->assertSame(LianBarangays::coordinateFor('San Diego'), [$result['latitude'], $result['longitude']]);

        foreach ($barangays as $barangay) {
            $this->assertContainsEquals(LianBarangays::coordinateFor($barangay), $points);
            $record = app(LianBarangayWeatherService::class)->getCurrentWeather($barangay);
            $this->assertSame(LianBarangays::coordinateFor($barangay), [$record['latitude'], $record['longitude']]);
            $this->assertSame('fresh', $record['status']);
        }
    }

    public function test_province_pagasa_scope_is_truthful_and_national_outlook_is_not_primary(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);
        $author = User::factory()->maoPersonnel()->create();
        $this->officialAdvisory($author, 'Batangas Provincial Weather Forecast', 'province', 'Batangas Province');
        $this->officialAdvisory($author, 'PAGASA Weekly Weather Outlook', 'national', 'National Outlook');

        $page = $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk();
        // The province bulletin keeps its own scope and only says it includes Lian; it never claims a barangay.
        $page->assertSeeInOrder([
            'Official PAGASA Advisories',
            'This PAGASA advisory includes Lian, Batangas. PAGASA scope: Batangas Province.',
            'Batangas Provincial Weather Forecast',
            'Other PAGASA Information',
            'PAGASA Weekly Weather Outlook',
        ])->assertDontSee('Scope: San Diego')->assertDontSee('Affected barangays: San Diego');
    }

    public function test_failed_refresh_returns_stale_barangay_weather_without_claiming_it_is_current(): void
    {
        $providerUp = true;
        Http::fake(['api.open-meteo.com/*' => function ($request) use (&$providerUp) {
            return $this->openMeteoAnswer($request, fn () => $providerUp ? $this->weatherPayload() : null);
        }]);
        $service = app(LianBarangayWeatherService::class);
        $service->latestForBarangay('Binubusan', refresh: true);

        // Past the 30-minute "recent" window, with the provider down for the batch and every retry.
        $this->travel(31)->minutes();
        $providerUp = false;
        $result = $service->latestForBarangay('Binubusan', refresh: true);

        $this->assertSame('stale', $result['status']);
        $this->assertTrue($result['stale']);
        $this->assertSame(29.4, $result['temperature_c']);
        $this->assertStringContainsString('temporarily delayed', $result['message']);
    }

    public function test_mao_barangay_selection_reuses_the_same_prepared_weather(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->weatherPayload())]);
        app(LocalFarmGuidanceService::class)->refresh('Malaruhatan');
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->get(route('planting-advisories.index', ['target_barangay' => 'Malaruhatan']))
            ->assertOk()->assertSee('Local Farming Advisory')->assertSee('Malaruhatan, Lian')
            ->assertSee('29.4°C')->assertSee('9.2 mm');
    }

    private function officialAdvisory(User $author, string $title, string $scopeType, string $scopeName): PlantingAdvisory
    {
        return PlantingAdvisory::factory()->create([
            'title' => $title,
            'source' => PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY,
            'source_url' => 'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd',
            'target_scope' => 'municipality',
            'target_barangay' => null,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHours(6),
            'published_at' => now()->subHour(),
            'posted_by' => $author->id,
            'metadata' => ['scope_type' => $scopeType, 'scope_name' => $scopeName, 'is_official' => true],
        ]);
    }

    private function weatherPayload(): array
    {
        $start = now('Asia/Manila')->startOfHour();
        $times = collect(range(0, 23))->map(fn (int $hour) => $start->copy()->addHours($hour)->format('Y-m-d\TH:i'))->all();

        return [
            'current' => [
                'time' => now('Asia/Manila')->format('Y-m-d\TH:i'),
                'temperature_2m' => 29.4,
                'relative_humidity_2m' => 82,
                'precipitation' => 1.8,
                'rain' => 1.8,
                'weather_code' => 61,
                'is_day' => 0,
                'wind_speed_10m' => 12.3,
            ],
            'hourly' => [
                'time' => $times,
                'precipitation_probability' => [76, ...array_fill(0, 23, 20)],
                'precipitation' => [9.2, ...array_fill(0, 23, 0)],
                'rain' => [9.2, ...array_fill(0, 23, 0)],
                'temperature_2m' => array_fill(0, 24, 29.4),
                'relative_humidity_2m' => array_fill(0, 24, 82),
                'wind_speed_10m' => array_fill(0, 24, 12.3),
            ],
            'daily' => [
                'time' => [now('Asia/Manila')->toDateString()],
                'temperature_2m_max' => [31],
                'temperature_2m_min' => [24],
                'precipitation_sum' => [9.2],
                'rain_sum' => [9.2],
                'precipitation_probability_max' => [76],
            ],
        ];
    }
}
