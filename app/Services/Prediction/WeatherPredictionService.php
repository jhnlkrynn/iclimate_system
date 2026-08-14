<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class WeatherPredictionService
{
    public function __construct(
        private readonly MonthlyWeatherRandomForest $forest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function predict(CarbonImmutable $targetDate, ?string $predictionType = null): array
    {
        if ($predictionType !== null && $predictionType !== PredictionType::WEATHER->value) {
            throw new InvalidArgumentException('WeatherPredictionService only accepts weather predictions.');
        }

        $targetMonth = $targetDate->startOfMonth();
        $result = $this->forest->predict($targetMonth);
        $predictions = $result['predictions'] ?? [];

        return [
            ...$result,
            'prediction_type' => PredictionType::WEATHER->value,
            'target_date' => $targetDate,
            'target_month' => $targetMonth,
            'model_source' => (string) config('prediction.weather.model_path', 'storage/models/weather_model.pkl'),
            'model_name' => (string) config('prediction.weather.model_name', 'weather_model'),
            'model_runtime' => 'MonthlyWeatherRandomForest',
            'model_input' => [
                'rainfall' => isset($predictions['rainfall']) ? round((float) $predictions['rainfall'], 2) : null,
                'temp_avg' => isset($predictions['temperature']) ? round((float) $predictions['temperature'], 2) : null,
                'humidity' => isset($predictions['humidity']) ? round((float) $predictions['humidity'], 2) : null,
                'wind_speed' => isset($predictions['wind_speed']) ? round((float) $predictions['wind_speed'], 2) : null,
                'season' => $predictions['season'] ?? null,
                'month_num' => (int) $targetDate->format('n'),
            ],
        ];
    }
}
