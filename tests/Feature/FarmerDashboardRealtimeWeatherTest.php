<?php

namespace Tests\Feature;

use App\Models\ExternalWeatherData;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FarmerDashboardRealtimeWeatherTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_dashboard_shows_live_open_meteo_current_weather_cards(): void
    {
        $farmer = User::factory()->farmer()->create();
        Notification::factory()->create(['user_id' => $farmer->id, 'is_read' => false]);
        HeatmapArea::factory()->create(['risk_level' => 'High']);

        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
        ]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('28.7°C')
            ->assertSee('0.0 mm')
            ->assertSee('77%')
            ->assertSee('Live as of')
            ->assertSee('Data updated')
            ->assertDontSee('Live online fetch')
            ->assertDontSee('Live database count');
    }

    public function test_farmer_dashboard_uses_stored_weather_without_blocking_login_on_http(): void
    {
        $farmer = User::factory()->farmer()->create();
        ExternalWeatherData::query()->create([
            'source' => 'Open-Meteo',
            'location_name' => 'Lian, Batangas',
            'latitude' => 14.033,
            'longitude' => 120.650,
            'forecast_date' => now('Asia/Manila')->toDateString(),
            'temperature' => 29.4,
            'temperature_max' => 31,
            'temperature_min' => 24,
            'humidity' => 79,
            'rainfall_mm' => 0,
            'precipitation_probability' => 20,
            'wind_speed' => 9,
            'raw_response' => [
                'current' => [
                    'time' => now('Asia/Manila')->format('Y-m-d\TH:00'),
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 79,
                    'rain' => 0,
                    'precipitation' => 0,
                ],
            ],
            'fetched_at' => now(),
        ]);

        Http::fake([
            'api.open-meteo.com/*' => function () {
                $this->fail('Login dashboard should not block on Open-Meteo when stored weather exists.');
            },
        ]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('29.4°C')
            ->assertSee('OPEN-METEO');
    }

    private function openMeteoPayload(): array
    {
        $date = now('Asia/Manila')->toDateString();
        $dates = collect(range(0, 15))->map(fn (int $day): string => now('Asia/Manila')->addDays($day)->toDateString())->all();
        $hours = collect($dates)
            ->flatMap(fn (string $day): array => collect(range(0, 23))->map(fn (int $hour): string => "{$day}T".str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00')->all())
            ->values()
            ->all();

        return [
            'latitude' => 14.033,
            'longitude' => 120.650,
            'timezone' => 'Asia/Manila',
            'current' => [
                'time' => "{$date}T16:00",
                'temperature_2m' => 28.7,
                'relative_humidity_2m' => 77,
                'precipitation' => 0,
                'rain' => 0,
                'weather_code' => 3,
                'wind_speed_10m' => 9.2,
            ],
            'hourly' => [
                'time' => $hours,
                'temperature_2m' => array_fill(0, count($hours), 28.7),
                'relative_humidity_2m' => array_fill(0, count($hours), 77),
                'precipitation_probability' => array_fill(0, count($hours), 20),
                'precipitation' => array_fill(0, count($hours), 0),
                'rain' => array_fill(0, count($hours), 0),
                'wind_speed_10m' => array_fill(0, count($hours), 9.2),
                'soil_temperature_0cm' => array_fill(0, count($hours), 27),
                'soil_moisture_0_to_1cm' => array_fill(0, count($hours), 0.22),
                'soil_moisture_1_to_3cm' => array_fill(0, count($hours), 0.24),
            ],
            'daily' => [
                'time' => $dates,
                'weather_code' => array_fill(0, count($dates), 3),
                'temperature_2m_max' => array_fill(0, count($dates), 31),
                'temperature_2m_min' => array_fill(0, count($dates), 24),
                'precipitation_sum' => array_fill(0, count($dates), 0),
                'rain_sum' => array_fill(0, count($dates), 0),
                'precipitation_probability_max' => array_fill(0, count($dates), 20),
                'wind_speed_10m_max' => array_fill(0, count($dates), 9.2),
                'et0_fao_evapotranspiration' => array_fill(0, count($dates), 3.1),
                'sunrise' => array_fill(0, count($dates), "{$date}T05:30"),
                'sunset' => array_fill(0, count($dates), "{$date}T18:10"),
            ],
        ];
    }
}
