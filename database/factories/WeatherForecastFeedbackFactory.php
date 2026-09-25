<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WeatherForecastFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeatherForecastFeedback> */
class WeatherForecastFeedbackFactory extends Factory
{
    protected $model = WeatherForecastFeedback::class;

    public function definition(): array
    {
        $date = now('Asia/Manila')->subMonths(2)->startOfMonth();

        return [
            'user_id' => User::factory()->farmer(),
            'barangay' => 'Malaruhatan',
            'forecast_type' => WeatherForecastFeedback::TYPE_MONTHLY,
            'forecast_date' => $date->toDateString(),
            'monthly_weather_prediction_id' => null,
            'forecast_condition' => 'Moderate Rain',
            'forecast_rainfall_mm' => 150,
            'forecast_temperature_c' => 28,
            'forecast_humidity_percent' => 80,
            'accuracy' => WeatherForecastFeedback::ACCURATE,
            'observed_condition' => null,
            'submitted_at' => now(),
        ];
    }

    public function accuracy(string $accuracy, ?string $observed = null): static
    {
        return $this->state(fn () => ['accuracy' => $accuracy, 'observed_condition' => $observed]);
    }
}
