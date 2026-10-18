<?php

namespace Tests\Feature;

use App\Models\MaoUserBarangay;
use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HeatMapApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedForecasts(): void
    {
        $today = now('Asia/Manila')->startOfDay();
        $records = [];
        foreach (LianBarangays::all() as $name) {
            $records[$name] = ['barangay' => $name, 'fetched_at' => now()->toIso8601String(), 'daily' => collect(range(0, 15))->map(fn (int $i): array => ['date' => $today->copy()->addDays($i)->toDateString(), 'rainfall' => $name === 'Matabungkay' ? 60 : 4, 'runoff_mm' => 0, 'soil_moisture' => .25, 'evapotranspiration_mm' => 3])->all()];
        }
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => $records, 'status' => []]);
    }

    public function test_mao_gets_all_nineteen_for_every_layer_and_period(): void
    {
        $this->seedForecasts();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        foreach (['weather_risk', 'flood', 'drought', 'yield'] as $layer) foreach (['week', '2weeks'] as $period) {
            $json = $this->actingAs($mao)->getJson('/api/heat-map?layer='.$layer.'&period='.$period)->assertOk();
            $this->assertSame($layer, $json->json('layer'));
            $this->assertCount(19, $json->json('barangays'));
            $this->assertNotEmpty($json->json('source'));
            foreach ($json->json('barangays') as $row) $this->assertContains($row['level'], $layer === 'flood' ? ['flood_prone', 'moderate', 'low', 'unavailable'] : ['low', 'moderate', 'high', 'severe', 'unavailable']);
        }
    }

    public function test_assigned_mao_gets_only_their_barangay(): void
    {
        $this->seedForecasts();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        MaoUserBarangay::create(['user_id' => $mao->id, 'barangay' => 'Kapito']);
        $this->actingAs($mao)->getJson('/api/heat-map?layer=flood&period=week')->assertOk()->assertJsonCount(1, 'barangays')->assertJsonPath('barangays.0.name', 'Kapito');
    }

    public function test_flood_uses_latest_stored_rating_when_forecast_is_empty(): void
    {
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => [], 'status' => []]);
        HeatmapArea::query()->create([
            'barangay' => 'Puting Kahoy',
            'risk_level' => 'High',
            'risk_type' => 'Flood',
            'description' => 'Stored flood rating',
            'risk_components' => ['flood' => ['level' => 'high', 'updated_at' => now()->subHour()->toIso8601String(), 'source' => 'stored']],
        ]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $json = $this->actingAs($mao)->getJson('/api/heat-map?layer=flood&period=week')->assertOk();

        $puting = collect($json->json('barangays'))->firstWhere('name', 'Puting-Kahoy');
        $this->assertSame('flood_prone', $puting['level']);
        $this->assertContains('Most recent stored flood rating', $puting['drivers']);
    }
}
