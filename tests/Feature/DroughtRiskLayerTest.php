<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\PythonService;
use App\Services\Risk\DroughtRiskService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DroughtRiskLayerTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo($this->now = CarbonImmutable::parse('2026-11-25 08:00:00', 'Asia/Manila'));

        foreach (LianBarangays::all() as $name) {
            HeatmapArea::query()->updateOrCreate(
                ['barangay' => $name],
                [
                    'municipality' => 'Lian',
                    'province' => 'Batangas',
                    'risk_level' => 'Low',
                    'risk_type' => 'Drought',
                    'risk_score' => 20,
                ]
            );
        }
    }

    private function sampleDryForecast(int $days = 14): array
    {
        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $rows[] = [
                'date' => $this->now->addDays($i)->toDateString(),
                'rainfall' => 0.0,
                'precipitation_mm' => 0.0,
                'temp_max' => 35.0,
                'temperature_max' => 35.0,
                'humidity_percent' => 62.0,
                'wind_speed_max' => 10.0,
            ];
        }

        return $rows;
    }

    public function test_refresh_command_uses_ml_model_when_up_and_populates_all_19_barangays(): void
    {
        $daily = $this->sampleDryForecast(14);
        $savedForecasts = [];
        foreach (LianBarangays::all() as $name) {
            $savedForecasts[$name] = [
                'fetched_at' => $this->now->toIso8601String(),
                'daily' => $daily,
            ];
        }

        $this->mock(LianBarangayWeatherService::class, function ($mock) use ($savedForecasts): void {
            $mock->shouldReceive('savedForecasts')->andReturn($savedForecasts);
        });
        $this->mock(FarmerDashboardWeatherService::class, function ($mock): void {
            $mock->shouldReceive('stored')->andReturn(['success' => false]);
        });
        $this->mock(PythonService::class, function ($mock): void {
            $mock->shouldReceive('farmingAssistant')->andReturn([
                'success' => true,
                'weather_prediction' => ['predicted_weather' => 'Sunny'],
            ]);
        });

        $this->artisan('iclimate:refresh-drought-risk')->assertExitCode(0);

        $drought = app(DroughtRiskService::class)->readForLayer('2weeks');
        $this->assertSame('iClimate model', $drought['source']);
        $this->assertCount(19, $drought['barangays']);

        foreach (LianBarangays::all() as $name) {
            $this->assertSame('high', $drought['barangays'][$name]['level']);
            $area = HeatmapArea::where('barangay', $name)->firstOrFail();
            $this->assertSame('iClimate model', $area->risk_components['drought']['source']);
            $this->assertSame('high', $area->risk_components['drought']['level']);
        }
    }

    public function test_ml_down_falls_back_to_stored_open_meteo_forecast_and_logs_warning(): void
    {
        Log::spy();
        $daily = $this->sampleDryForecast(14);

        $this->mock(LianBarangayWeatherService::class, function ($mock): void {
            $mock->shouldReceive('savedForecasts')->andReturn([]);
        });
        $this->mock(FarmerDashboardWeatherService::class, function ($mock) use ($daily): void {
            $mock->shouldReceive('stored')->andReturn([
                'success' => true,
                'timestamps' => ['server_fetched_at' => $this->now->toIso8601String()],
                'forecast' => array_slice($daily, 0, 7),
                'forecast_extended' => $daily,
            ]);
        });
        $this->mock(PythonService::class, function ($mock): void {
            $mock->shouldReceive('farmingAssistant')->andReturn([
                'success' => false,
                'error' => 'Connection refused',
            ]);
        });

        $this->artisan('iclimate:refresh-drought-risk')->assertExitCode(0);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg) => str_contains($msg, 'ML service unavailable'))->once();

        $drought = app(DroughtRiskService::class)->readForLayer('2weeks');
        $this->assertSame('iClimate forecast', $drought['source']);
        $this->assertSame('high', $drought['barangays']['Humayingan']['level']);

        $farmer = User::factory()->farmer()->create(['barangay' => 'Humayingan', 'status' => User::STATUS_ACTIVE]);
        $api = $this->actingAs($farmer)->getJson('/api/heat-map?layer=drought&period=2weeks')->assertOk()->json();
        $this->assertSame('iClimate forecast', $api['source']);
        $this->assertCount(19, $api['barangays']);
        $this->assertSame('high', $api['barangays'][0]['level']);
    }

    public function test_no_forecast_falls_back_to_last_saved_drought_ratings_and_heat_map_labels(): void
    {
        $area = HeatmapArea::where('barangay', 'Humayingan')->firstOrFail();
        $area->forceFill([
            'risk_components' => [
                'drought' => [
                    'level' => 'moderate',
                    'updated_at' => $this->now->subHours(4)->toIso8601String(),
                    'source' => 'Last saved drought ratings',
                    'metrics' => ['days_covered' => 14],
                ],
            ],
        ])->save();

        $this->mock(LianBarangayWeatherService::class, function ($mock): void {
            $mock->shouldReceive('savedForecasts')->andReturn([]);
        });
        $this->mock(FarmerDashboardWeatherService::class, function ($mock): void {
            $mock->shouldReceive('stored')->andReturn(['success' => false]);
        });

        $drought = app(DroughtRiskService::class)->readForLayer('2weeks');
        $this->assertSame('moderate', $drought['barangays']['Humayingan']['level']);
        $this->assertTrue($drought['barangays']['Humayingan']['is_stale_fallback']);

        $farmer = User::factory()->farmer()->create(['barangay' => 'Humayingan', 'status' => User::STATUS_ACTIVE]);
        $html = $this->actingAs($farmer)->get(route('heat-map.index', ['range' => 'drought']))->assertOk()->getContent();
        $this->assertStringContainsString('Drought-prone barangays', $html);
        $this->assertStringContainsString('Drought-prone barangays \u2014 No data yet', $html);
        $this->assertStringNotContainsString('Flood-prone barangays — No data yet', $html);
    }
}
