<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeatherPredictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_monthly_weather_prediction(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        foreach (range(1, 6) as $month) {
            ClimateRecord::query()->create([
                'record_date' => sprintf('2026-%02d-15', $month),
                'rainfall' => 80 + ($month * 8),
                'temperature' => 27 + ($month * 0.2),
                'humidity' => 70 + $month,
                'wind_speed' => 8 + ($month * 0.3),
                'season' => $month >= 5 ? ClimateRecord::SEASON_WET : ClimateRecord::SEASON_DRY,
                'source' => 'PAGASA',
            ]);
        }

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => '2026-07']))
            ->assertOk()
            ->assertSee('Monthly Weather Prediction')
            ->assertSee('Random Forest')
            ->assertSee('Rainfall');
    }

    public function test_authenticated_user_can_predict_rice_yield(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'rainfall' => 180,
                'temp_avg' => 29,
                'temp_range' => 8,
                'area' => 120,
                'previous_rainfall' => 150,
                'previous_temp' => 28.5,
                'rainfall_6m' => 170,
                'temp_3m' => 29,
                'temp_6m' => 28.8,
                'seasonal_rainfall' => 900,
                'seasonal_temp' => 29,
                'season' => 'Wet',
                'farm_type' => 'Rainfed',
            ])
            ->assertOk()
            ->assertSee('Rice Yield Prediction Result')
            ->assertSee('Predicted Rice Yield')
            ->assertDontSee('Prediction error:');
    }

    public function test_rice_yield_prediction_rejects_unrealistic_inputs(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($user)
            ->from(route('weather-predictions.index'))
            ->post(route('weather-predictions.predict'), [
                'rainfall' => 999,
                'temp_avg' => 55,
                'temp_range' => 40,
                'area' => -1,
                'previous_rainfall' => -5,
                'previous_temp' => 10,
                'rainfall_6m' => 900,
                'temp_3m' => 45,
                'temp_6m' => 8,
                'seasonal_rainfall' => 5000,
                'seasonal_temp' => 41,
                'season' => 'Wet',
                'farm_type' => 'Rainfed',
            ])
            ->assertRedirect(route('weather-predictions.index'))
            ->assertSessionHasErrors([
                'rainfall',
                'temp_avg',
                'temp_range',
                'area',
                'previous_rainfall',
                'previous_temp',
                'rainfall_6m',
                'temp_3m',
                'temp_6m',
                'seasonal_rainfall',
                'seasonal_temp',
            ]);
    }
}
