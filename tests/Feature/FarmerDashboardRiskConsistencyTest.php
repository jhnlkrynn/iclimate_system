<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FarmerDashboardRiskConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_wind_alert_and_low_risk_both_show_without_contradictory_bad_weather_headline(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);

        // Barangay forecast has 30 km/h wind today (below BarangayWeekRiskService's 39 km/h Moderate threshold -> Low Risk,
        // but above farmer_dashboard strong_wind_kmh=25 -> triggers wind alert in banner).
        config(['farmer_dashboard.strong_wind_kmh' => 25]);
        $today = now('Asia/Manila')->toDateString();
        $daily = [];
        for ($i = 0; $i < 7; $i++) {
            $daily[] = [
                'date' => now('Asia/Manila')->addDays($i)->toDateString(),
                'rainfall' => 2.0,
                'wind_speed_max' => $i === 0 ? 30.0 : 10.0,
                'temperature_max' => 31.0,
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

        $this->mock(BarangayRiskMapService::class, function ($mock): void {
            $mock->shouldReceive('detailFor')->andReturn(['advisory' => ['planting' => 'Ideal planting period.']]);
            $mock->shouldReceive('dominantFarmTypes')->andReturn(array_fill_keys(LianBarangays::all(), 'Irrigated'));
        });

        $response = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $response->assertOk();
        $response->assertSee('Strong wind is expected today');
        $response->assertSee('Low Risk');
        $response->assertSee('Weather alert');
        $response->assertDontSee('Bad weather');
    }
}
