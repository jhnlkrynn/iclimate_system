<?php

namespace Tests\Feature;

use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\AdvisoryGenerationService;
use App\Services\Advisories\AdvisoryRuleEngine;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Weather\OpenMeteoService;
use Database\Seeders\AdvisoryRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlineAgriculturalAdvisorySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_meteo_service_normalizes_a_valid_response(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->openMeteoPayload(rainfall: 38, probability: 86), 200)]);

        $result = app(OpenMeteoService::class)->fetchForecast(true);

        $this->assertTrue($result['ok']);
        $this->assertSame(7, $result['records_saved']);
        $this->assertDatabaseHas('external_weather_data', [
            'source' => 'Open-Meteo',
            'rainfall_mm' => 38,
            'precipitation_probability' => 86,
        ]);
    }

    public function test_api_failure_returns_last_stored_successful_forecast(): void
    {
        $stored = ExternalWeatherData::query()->create($this->weatherRecord(['forecast_date' => now()->toDateString(), 'rainfall_mm' => 10]));
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);

        $result = app(OpenMeteoService::class)->fetchForecast(true);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['records']->contains('id', $stored->id));
    }

    public function test_heavy_rainfall_generates_climate_advisory_and_prevents_duplicates(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);
        $records = collect([ExternalWeatherData::query()->create($this->weatherRecord(['rainfall_mm' => 38, 'precipitation_probability' => 86]))]);

        $first = app(AdvisoryRuleEngine::class)->generate($records);
        $second = app(AdvisoryRuleEngine::class)->generate($records);

        $this->assertSame(2, $first['advisories_created']);
        $this->assertSame(2, $second['advisories_skipped_as_duplicates']);
        $this->assertDatabaseHas('planting_advisories', [
            'title' => 'Heavy Rainfall Warning',
            'advisory_type' => 'climate',
            'status' => 'published',
        ]);
    }

    public function test_dry_conditions_generate_irrigation_advisory(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);
        $records = collect(range(1, 3))->map(fn ($day) => ExternalWeatherData::query()->create($this->weatherRecord([
            'forecast_date' => now()->addDays($day - 1)->toDateString(),
            'rainfall_mm' => 1,
            'precipitation_probability' => 10,
            'evapotranspiration_mm' => 4.5,
            'soil_moisture' => 0.12,
        ])));

        app(AdvisoryRuleEngine::class)->generate($records);

        $this->assertDatabaseHas('planting_advisories', [
            'title' => 'Supplemental Irrigation May Be Needed',
            'advisory_type' => 'irrigation',
        ]);
    }

    public function test_favorable_forecast_generates_planting_advisory(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);
        $records = collect(range(1, 7))->map(fn ($day) => ExternalWeatherData::query()->create($this->weatherRecord([
            'forecast_date' => now()->addDays($day - 1)->toDateString(),
            'rainfall_mm' => 7,
            'precipitation_probability' => $day <= 3 ? 45 : 20,
            'temperature_max' => 30,
        ])));

        app(AdvisoryRuleEngine::class)->generate($records);

        $this->assertDatabaseHas('planting_advisories', [
            'title' => 'Potentially Favorable Planting Conditions',
            'advisory_type' => 'planting',
        ]);
    }

    public function test_realtime_generation_creates_hourly_daily_weekly_and_monthly_advisory_horizons(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);

        $records = collect(range(1, 16))->map(fn ($day) => ExternalWeatherData::query()->create($this->weatherRecord([
            'forecast_date' => now()->addDays($day - 1)->toDateString(),
            'rainfall_mm' => 38,
            'precipitation_probability' => 86,
        ])));

        $result = app(AdvisoryRuleEngine::class)->generate($records, [
            'weather_freshness' => 'fresh',
            'harvest_ready' => false,
            'advisory_horizons' => ['hourly', 'daily', 'weekly', 'monthly'],
        ]);

        $this->assertGreaterThanOrEqual(4, $result['advisories_created']);

        foreach (['hourly', 'daily', 'weekly', 'monthly'] as $horizon) {
            $this->assertDatabaseHas('planting_advisories', [
                'advisory_type' => 'climate',
                'generated_automatically' => true,
            ]);

            $this->assertTrue(
                PlantingAdvisory::query()->where('metadata->advisory_horizon', $horizon)->exists(),
                "Expected an advisory for the {$horizon} horizon."
            );
        }
    }

    public function test_realtime_generation_populates_baseline_advisories_when_no_rule_matches(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);

        $records = collect(range(1, 16))->map(fn ($day) => ExternalWeatherData::query()->create($this->weatherRecord([
            'forecast_date' => now()->addDays($day - 1)->toDateString(),
            'rainfall_mm' => 0,
            'precipitation_probability' => 5,
            'temperature_max' => 29,
            'evapotranspiration_mm' => 2,
            'soil_moisture' => 0.25,
        ])));

        $result = app(AdvisoryRuleEngine::class)->generate($records, [
            'weather_freshness' => 'fresh',
            'harvest_ready' => false,
            'advisory_horizons' => ['hourly', 'daily', 'weekly', 'monthly'],
        ]);

        $this->assertSame(4, $result['advisories_created']);

        foreach (['hourly', 'daily', 'weekly', 'monthly'] as $horizon) {
            $this->assertTrue(
                PlantingAdvisory::query()
                    ->where('metadata->advisory_horizon', $horizon)
                    ->where('metadata->baseline_advisory', true)
                    ->exists(),
                "Expected a baseline advisory for the {$horizon} horizon."
            );
        }
    }

    public function test_pagasa_service_stores_official_lian_batangas_online_advisory(): void
    {
        User::factory()->maoPersonnel()->create();

        Http::fake([
            'bagong.pagasa.dost.gov.ph/*' => Http::response('<html><body>Heavy Rainfall Warning No. 5 ORANGE WARNING LEVEL: Batangas(Lian, Nasugbu, Tuy). ASSOCIATED HAZARD: FLOODING is THREATENING in low lying areas.</body></html>', 200),
            'pagasa.dost.gov.ph/*' => Http::response('<html><body>Weather outlook for Batangas: cloudy skies with scattered rainshowers and thunderstorms.</body></html>', 200),
        ]);

        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertSame(2, $result['advisories_created']);
        $this->assertDatabaseHas('planting_advisories', [
            'source' => 'PAGASA',
            'advisory_type' => 'climate',
            'status' => 'published',
        ]);
        $this->assertTrue(PlantingAdvisory::query()->where('metadata->official_source', true)->exists());
        $this->assertTrue(PlantingAdvisory::query()->where('metadata->pagasa_location_match', 'Lian, Batangas')->exists());
    }

    public function test_pagasa_service_ignores_online_content_without_lian_or_batangas(): void
    {
        User::factory()->maoPersonnel()->create();

        Http::fake([
            'bagong.pagasa.dost.gov.ph/*' => Http::response('<html><body>Rainfall advisory for Metro Manila only.</body></html>', 200),
            'pagasa.dost.gov.ph/*' => Http::response('<html><body>Weather outlook for Northern Luzon.</body></html>', 200),
        ]);

        $result = app(PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertSame(0, $result['advisories_created']);
        $this->assertSame(2, $result['sources_without_lian_batangas_match']);
        $this->assertDatabaseMissing('planting_advisories', [
            'source' => 'PAGASA',
        ]);
    }

    public function test_pagasa_service_targets_one_lian_barangay_when_named_online(): void
    {
        User::factory()->maoPersonnel()->create();

        Http::fake([
            'bagong.pagasa.dost.gov.ph/*' => Http::response('<html><body>Thunderstorm Advisory: Moderate to heavy rainshowers are expected over Lian, Batangas, especially Matabungkay, within the next 2 hours.</body></html>', 200),
            'pagasa.dost.gov.ph/*' => Http::response('<html><body>No Batangas outlook today.</body></html>', 200),
        ]);

        app(PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertDatabaseHas('planting_advisories', [
            'source' => 'PAGASA',
            'target_barangay' => 'Matabungkay',
            'target_scope' => 'barangay',
        ]);
        $this->assertContains(
            'Matabungkay',
            PlantingAdvisory::query()->where('target_barangay', 'Matabungkay')->firstOrFail()->metadata['pagasa_barangay_matches']
        );
    }

    public function test_pagasa_service_keeps_all_barangays_when_multiple_lian_barangays_are_named_online(): void
    {
        User::factory()->maoPersonnel()->create();

        Http::fake([
            'bagong.pagasa.dost.gov.ph/*' => Http::response('<html><body>Rainfall Advisory: Lian, Batangas barangays Matabungkay and Binubusan may experience moderate to heavy rainfall.</body></html>', 200),
            'pagasa.dost.gov.ph/*' => Http::response('<html><body>No Batangas outlook today.</body></html>', 200),
        ]);

        app(PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertDatabaseHas('planting_advisories', [
            'source' => 'PAGASA',
            'target_barangay' => null,
            'target_scope' => 'municipality',
        ]);
        $this->assertTrue(PlantingAdvisory::query()->whereJsonContains('metadata->pagasa_barangay_matches', 'Matabungkay')->exists());
        $this->assertTrue(PlantingAdvisory::query()->whereJsonContains('metadata->pagasa_barangay_matches', 'Binubusan')->exists());
    }

    public function test_harvest_advisory_is_not_generated_without_harvest_ready_crop_data(): void
    {
        User::factory()->maoPersonnel()->create();
        $this->seed(AdvisoryRuleSeeder::class);
        $records = collect([ExternalWeatherData::query()->create($this->weatherRecord(['rainfall_mm' => 20, 'precipitation_probability' => 80]))]);

        app(AdvisoryRuleEngine::class)->generate($records, ['harvest_ready' => false]);

        $this->assertDatabaseMissing('planting_advisories', [
            'advisory_type' => 'harvesting',
        ]);
    }

    public function test_expired_advisories_are_marked_expired(): void
    {
        PlantingAdvisory::query()->create($this->advisoryPayload(['valid_until' => now()->subHour()]));

        $expired = app(AdvisoryGenerationService::class)->expireOutdated();

        $this->assertSame(1, $expired);
        $this->assertDatabaseHas('planting_advisories', ['status' => 'expired']);
    }

    public function test_farmer_only_sees_municipal_and_own_barangay_advisories(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Matabungkay']);
        PlantingAdvisory::query()->create($this->advisoryPayload(['title' => 'Municipal']));
        PlantingAdvisory::query()->create($this->advisoryPayload(['title' => 'Matabungkay', 'target_barangay' => 'Matabungkay', 'target_scope' => 'barangay']));
        PlantingAdvisory::query()->create($this->advisoryPayload(['title' => 'Prenza', 'target_barangay' => 'Prenza', 'target_scope' => 'barangay']));

        $this->actingAs($farmer)
            ->get(route('planting-advisories.index'))
            ->assertOk()
            ->assertSee('Municipal')
            ->assertSee('Matabungkay')
            ->assertDontSee('<div class="fw-bold">Prenza</div>', false);
    }

    public function test_mao_can_approve_and_farmer_cannot_approve(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $advisory = PlantingAdvisory::query()->create($this->advisoryPayload(['status' => 'pending_review']));

        $this->actingAs($farmer)->post(route('management.advisories.approve', $advisory))->assertForbidden();
        $this->assertDatabaseHas('planting_advisories', ['id' => $advisory->id, 'status' => 'pending_review']);

        $this->actingAs($mao)->post(route('management.advisories.approve', $advisory))->assertRedirect();
        $this->assertDatabaseHas('planting_advisories', ['id' => $advisory->id, 'status' => 'published']);
    }

    public function test_filters_and_validation_are_user_friendly(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        PlantingAdvisory::query()->create($this->advisoryPayload(['title' => 'Dry advisory', 'advisory_type' => 'irrigation', 'severity' => 'moderate']));

        $this->actingAs($mao)
            ->get(route('planting-advisories.index', ['advisory_type' => 'irrigation', 'severity' => 'moderate']))
            ->assertOk()
            ->assertSee('Dry advisory');

        $this->actingAs($mao)->post(route('planting-advisories.store'), [
            'title' => '',
            'message' => '',
            'advisory_type' => 'planting',
            'status' => 'published',
        ])->assertSessionHasErrors(['title', 'message']);
    }

    private function openMeteoPayload(float $rainfall = 10, float $probability = 40, float $temperature = 31, float $wind = 12): array
    {
        $dates = collect(range(0, 6))->map(fn ($day) => now()->addDays($day)->toDateString())->all();
        $hours = collect($dates)->flatMap(fn ($date) => collect(range(0, 23))->map(fn ($hour) => "{$date}T".str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00'))->values()->all();

        return [
            'latitude' => 14.033,
            'longitude' => 120.650,
            'timezone' => 'Asia/Manila',
            'hourly' => [
                'time' => $hours,
                'temperature_2m' => array_fill(0, count($hours), $temperature),
                'relative_humidity_2m' => array_fill(0, count($hours), 80),
                'precipitation_probability' => array_fill(0, count($hours), $probability),
                'precipitation' => array_fill(0, count($hours), $rainfall / 24),
                'rain' => array_fill(0, count($hours), $rainfall / 24),
                'wind_speed_10m' => array_fill(0, count($hours), $wind),
                'soil_temperature_0cm' => array_fill(0, count($hours), 29),
                'soil_moisture_0_to_1cm' => array_fill(0, count($hours), 0.22),
                'soil_moisture_1_to_3cm' => array_fill(0, count($hours), 0.24),
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => array_fill(0, 7, 61),
                'temperature_2m_max' => array_fill(0, 7, $temperature),
                'temperature_2m_min' => array_fill(0, 7, 24),
                'precipitation_sum' => array_fill(0, 7, $rainfall),
                'rain_sum' => array_fill(0, 7, $rainfall),
                'precipitation_probability_max' => array_fill(0, 7, $probability),
                'wind_speed_10m_max' => array_fill(0, 7, $wind),
                'et0_fao_evapotranspiration' => array_fill(0, 7, 3.2),
                'sunrise' => array_fill(0, 7, now()->toDateString().'T05:30'),
                'sunset' => array_fill(0, 7, now()->toDateString().'T18:10'),
            ],
        ];
    }

    private function weatherRecord(array $overrides = []): array
    {
        return array_merge([
            'source' => 'Open-Meteo',
            'location_name' => 'Lian, Batangas',
            'latitude' => 14.033,
            'longitude' => 120.650,
            'forecast_date' => now()->toDateString(),
            'temperature_max' => 31,
            'temperature_min' => 24,
            'rainfall_mm' => 10,
            'precipitation_probability' => 40,
            'wind_speed' => 12,
            'evapotranspiration_mm' => 3,
            'soil_moisture' => 0.24,
            'fetched_at' => now(),
        ], $overrides);
    }

    private function advisoryPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Advisory',
            'content' => 'Message',
            'message' => 'Message',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Summary',
            'severity' => 'information',
            'target_scope' => 'municipality',
            'source' => 'Open-Meteo + iClimate Rules',
            'status' => 'published',
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addDay(),
            'posted_by' => User::factory()->maoPersonnel()->create()->id,
        ], $overrides);
    }
}
