<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WeatherAlertModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_dashboard_shows_popup_modal_instead_of_inline_banner_when_alert_is_active(): void
    {
        config(['farmer_dashboard.strong_wind_kmh' => 25]);
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $this->seedWeather(rain: 2.0, wind: 30.0);

        $this->mock(BarangayRiskMapService::class, function ($mock): void {
            $mock->shouldReceive('detailFor')->andReturn(['advisory' => ['planting' => 'Ideal planting period.']]);
            $mock->shouldReceive('dominantFarmTypes')->andReturn(array_fill_keys(LianBarangays::all(), 'Irrigated'));
        });

        $response = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('fh-mood-bad', $html);
        $this->assertMatchesRegularExpression('/data-slot="message"\s+hidden/', $html);
        $this->assertStringContainsString('data-weather-alert-modal', $html);
        $this->assertStringContainsString('data-alert-key="strong_wind|'.now('Asia/Manila')->toDateString().'"', $html);
        $this->assertStringContainsString('Weather alert', $html);
        $this->assertStringContainsString('Strong wind is expected today.', $html);
        $this->assertStringContainsString('Got it', $html);
    }

    public function test_farmer_dashboard_hides_modal_when_no_alert_is_active_and_translates_when_filipino(): void
    {
        config(['farmer_dashboard.strong_wind_kmh' => 25]);
        $calmFarmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $this->seedWeather(rain: 0.0, wind: 8.0);

        $this->mock(BarangayRiskMapService::class, function ($mock): void {
            $mock->shouldReceive('detailFor')->andReturn(['advisory' => ['planting' => 'Ideal planting period.']]);
            $mock->shouldReceive('dominantFarmTypes')->andReturn(array_fill_keys(LianBarangays::all(), 'Irrigated'));
        });

        $calmHtml = $this->actingAs($calmFarmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-weather-alert-modal', $calmHtml);

        $filFarmer = User::factory()->farmer()->create(['barangay' => 'Prenza', 'preferred_language' => 'fil']);
        $this->seedWeather(rain: 2.0, wind: 30.0);
        $filHtml = $this->actingAs($filFarmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Babala sa panahon', $filHtml);
        $this->assertStringContainsString('Nakuha ko', $filHtml);
    }

    public function test_mao_dashboard_renders_shared_weather_alert_modal_when_typhoon_is_active(): void
    {
        $mao = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
        ]);
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn([
            'key' => 'typhoon-2026',
            'title' => 'Typhoon Ompong',
            'severity' => 'High',
        ]));
        $this->mock(BarangayWeekRiskService::class, fn ($mock) => $mock->shouldReceive('forRange')->andReturn([
            'state' => 'ok',
            'counts' => ['high' => 0, 'moderate' => 0, 'low' => 19, 'severe' => 0, 'nodata' => 0],
            'barangays' => [],
            'updated_at' => null,
        ]));

        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-weather-alert-modal', $html);
        $this->assertStringContainsString('data-alert-key="typhoon:typhoon-2026|'.now('Asia/Manila')->toDateString().'"', $html);
        $this->assertStringContainsString('Typhoon Ompong', $html);
        $this->assertStringContainsString('Got it', $html);
    }

    private function seedWeather(float $rain = 0.0, float $wind = 10.0): void
    {
        $daily = [];
        for ($i = 0; $i < 7; $i++) {
            $daily[] = [
                'date' => now('Asia/Manila')->addDays($i)->toDateString(),
                'rainfall' => $i === 0 ? $rain : 0.0,
                'wind_speed_max' => $i === 0 ? $wind : 10.0,
                'temperature_max' => 31.0,
                'condition' => $i === 0 && $rain >= 25 ? 'Heavy Rain' : 'Partly Cloudy',
            ];
        }
        $store = ['fetched_at' => now()->toIso8601String(), 'records' => []];
        foreach (LianBarangays::all() as $name) {
            $store['records'][$name] = [
                'barangay' => $name,
                'fetched_at' => now()->toIso8601String(),
                'daily' => $daily,
            ];
        }
        Cache::put(LianBarangayWeatherService::STORE_KEY, $store, 600);
    }
}
