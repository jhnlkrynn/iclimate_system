<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\User;
use App\Services\Prediction\PredictionDateValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeatherPredictionTest extends TestCase
{
    use RefreshDatabase;

    private function seedClimateRecords(): void
    {
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
    }

    public function test_authenticated_user_can_view_monthly_weather_prediction(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $futureMonth = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m');

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth]))
            ->assertOk()
            ->assertSee('Rice Yield Forecast')
            ->assertSee('Random Forest')
            ->assertSee('Rainfall');
    }

    public function test_authenticated_user_can_predict_rice_yield(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
            ])
            ->assertOk()
            ->assertSee('Prediction Result')
            ->assertSee('Estimated Rice Yield')
            ->assertDontSee('Prediction error:');
    }

    public function test_rice_yield_prediction_rejects_unrealistic_inputs(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($user)
            ->from(route('weather-predictions.index'))
            ->post(route('weather-predictions.predict'), [
                'prediction_date' => 'not-a-date',
                'farm_type' => 'Rainfed',
            ])
            ->assertRedirect(route('weather-predictions.index'))
            ->assertSessionHasErrors([
                'prediction_date',
            ]);
    }

    public function test_rice_yield_prediction_rejects_todays_date(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_date' => Carbon::today()->format('Y-m-d'),
                'farm_type' => 'Rainfed',
            ])
            ->assertOk()
            ->assertSee(PredictionDateValidator::ERROR_MESSAGE)
            ->assertDontSee('Prediction Result');
    }

    public function test_rice_yield_prediction_rejects_past_date(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_date' => '2020-01-01',
                'farm_type' => 'Rainfed',
            ])
            ->assertOk()
            ->assertSee(PredictionDateValidator::ERROR_MESSAGE)
            ->assertDontSee('Prediction Result');
    }
}
