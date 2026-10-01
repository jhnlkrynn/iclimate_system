<?php

namespace Tests\Concerns;

use App\Models\MonthlyWeatherPrediction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;

/**
 * Stands in for the scheduled refresh: stores ready monthly forecasts the way
 * `iclimate:refresh-predictions` / `iclimate:warm-prediction-cache` do, so a
 * chat request (which only reads prepared predictions) has something to read.
 * The numbers are fixed test values, not model output.
 */
trait PreparesPredictions
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function preparePredictions(int $months = 14, array $overrides = [], bool $fresh = true): void
    {
        $start = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $datasetVersion = app(MonthlyWeatherRandomForest::class)->datasetVersion();

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonthsNoOverflow($i);

            MonthlyWeatherPrediction::query()->updateOrCreate(
                [
                    'prediction_year' => $month->year,
                    'prediction_month' => $month->month,
                    'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
                ],
                $overrides + [
                    'rainfall' => 226.4,
                    'tmax' => 31.5,
                    'tmin' => 24.3,
                    'temperature_average' => 27.9,
                    'humidity' => 82.0,
                    'wind_speed' => 9.0,
                    'wind_direction' => 90.0,
                    'season' => in_array($month->month, [6, 7, 8, 9, 10, 11], true) ? 'Wet' : 'Dry',
                    'training_from' => '2015-01-01',
                    'training_to' => $month->subMonth()->format('Y-m-01'),
                    'generated_at' => now(),
                    'expires_at' => $fresh ? now()->addHours(20) : now()->subHour(),
                    'status' => MonthlyWeatherPrediction::STATUS_READY,
                    'metadata' => [
                        'source_name' => 'Prepared test forecast',
                        'message' => 'Prepared for tests.',
                        'months_available' => 120,
                        'confidence' => ['score' => 70],
                        'dataset_version' => $datasetVersion,
                    ],
                ],
            );
        }
    }
}
