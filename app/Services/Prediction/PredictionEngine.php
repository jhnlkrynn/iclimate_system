<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\DecisionSupportService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\PythonService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The single computation path for rice-yield/weather predictions, shared by
 * the System Prediction page and the AI Assistant so identical inputs always
 * produce identical outputs.
 */
class PredictionEngine
{
    public function __construct(
        private readonly MonthlyWeatherRandomForest $forest,
        private readonly PythonService $python,
        private readonly DecisionSupportService $decisionSupport,
    ) {}

    /**
     * @param  array<string, mixed>  $manualOverrides  optional overrides for any model-input key
     */
    public function predict(
        CarbonImmutable $targetDate,
        string $farmType,
        ?float $areaOverride = null,
        ?User $user = null,
        array $manualOverrides = [],
    ): array {
        $targetMonth = $targetDate->startOfMonth();
        $weatherResult = $this->forest->predict($targetMonth);

        $modelInput = $this->buildModelInput($weatherResult, $farmType, $targetDate, $areaOverride, $user, $manualOverrides);
        $weatherLabel = WeatherLabeler::fromRainfall($modelInput['rainfall']);

        [$yield, $apiError] = $this->requestYield($modelInput);

        $decision = $this->decisionSupport->evaluate([
            ...$modelInput,
            'predicted_yield' => $yield['predicted_yield'] ?? null,
        ]);

        return [
            'target_date' => $targetDate,
            'target_month' => $targetMonth,
            'weather' => $weatherResult,
            'weather_label' => $weatherLabel,
            'model_input' => $modelInput,
            'yield' => $yield,
            'decision_support' => $decision,
            'api_error' => $apiError,
        ];
    }

    private function buildModelInput(
        array $weatherResult,
        string $farmType,
        CarbonImmutable $targetDate,
        ?float $areaOverride,
        ?User $user,
        array $manualOverrides,
    ): array {
        $predictions = $weatherResult['predictions'] ?? [];
        $recentClimateRecords = ClimateRecord::query()
            ->latest('record_date')
            ->take(6)
            ->get();
        $latestClimate = $recentClimateRecords->first();
        $averageRainfall = $recentClimateRecords->avg('rainfall');
        $averageTemperature = $recentClimateRecords->avg('temperature');
        $totalRainfall = $recentClimateRecords->sum('rainfall');
        $temperatureRange = $recentClimateRecords->isNotEmpty()
            ? ((float) $recentClimateRecords->max('temperature') - (float) $recentClimateRecords->min('temperature'))
            : null;

        $rainfall = (float) ($predictions['rainfall'] ?? $averageRainfall ?? $latestClimate?->rainfall ?? 0);
        $temperature = (float) ($predictions['temperature'] ?? $averageTemperature ?? $latestClimate?->temperature ?? 0);
        $humidity = (float) ($predictions['humidity'] ?? $latestClimate?->humidity ?? 0);
        $windSpeed = (float) ($predictions['wind_speed'] ?? $latestClimate?->wind_speed ?? 0);
        $forecastSeason = $predictions['season'] ?? 'Wet';
        $season = in_array($forecastSeason, ['Wet', 'Dry'], true)
            ? $forecastSeason
            : ($latestClimate?->season ?? 'Wet');

        $area = $areaOverride
            ?? $user?->farmerProfile?->farm_area
            ?? ($user ? 1.0 : (float) (RiceProduction::query()->avg('area_hectares') ?? 0));

        $modelInput = [
            'rainfall' => round($rainfall, 2),
            'temp_avg' => round($temperature, 2),
            'temp_range' => round(max(0, $temperatureRange ?? 0), 2),
            'area' => round((float) $area, 2),
            'previous_rainfall' => round((float) ($latestClimate?->rainfall ?? $rainfall), 2),
            'previous_temp' => round((float) ($latestClimate?->temperature ?? $temperature), 2),
            'previous_humidity' => round((float) ($latestClimate?->humidity ?? $humidity), 2),
            'previous_wind_speed' => round((float) ($latestClimate?->wind_speed ?? $windSpeed), 2),
            'rainfall_6m' => round((float) ($totalRainfall ?: $rainfall), 2),
            'temp_3m' => round((float) ($recentClimateRecords->take(3)->avg('temperature') ?? $temperature), 2),
            'temp_6m' => round((float) ($averageTemperature ?? $temperature), 2),
            'seasonal_rainfall' => round((float) ($recentClimateRecords->where('season', $season)->sum('rainfall') ?: $rainfall), 2),
            'seasonal_temp' => round((float) ($recentClimateRecords->where('season', $season)->avg('temperature') ?? $temperature), 2),
            'humidity' => round($humidity, 2),
            'wind_speed' => round($windSpeed, 2),
            'season' => $season,
            'farm_type' => $farmType,
            'month_num' => (int) $targetDate->format('n'),
        ];

        foreach ($manualOverrides as $key => $value) {
            if ($value !== null && array_key_exists($key, $modelInput)) {
                $modelInput[$key] = $value;
            }
        }

        return $modelInput;
    }

    private function requestYield(array $modelInput): array
    {
        [$modelYield, $apiError] = $this->requestYieldFromModel($modelInput);

        if ($modelYield !== null) {
            return [$modelYield, $apiError];
        }

        return [$this->localYieldFallback($modelInput), $apiError];
    }

    /**
     * The single "ask the trained model" path — calls the iClimate ML service
     * (python_scripts/main.py, FastAPI). Shared by every feature that needs a
     * raw model yield (System Prediction, AI Assistant, and per-barangay
     * heatmap risk) so results never diverge. On any failure (network error,
     * invalid response), returns a null yield with the error message so the
     * caller falls back to localYieldFallback().
     *
     * @return array{0: array<string, mixed>|null, 1: string|null} [yield, apiError]
     */
    public function requestYieldFromModel(array $modelInput): array
    {
        try {
            $apiResult = $this->python->farmingAssistant([
                'prediction_type' => PredictionType::RICE_YIELD->value,
                'features' => $modelInput,
            ]);
            $yield = data_get($apiResult, 'rice_yield_prediction', $apiResult);
            $predicted = data_get($yield, 'predicted_yield');

            if ($predicted !== null) {
                return [[
                    'predicted_yield' => round((float) $predicted, 2),
                    'unit' => data_get($yield, 'unit', 'tons/hectare'),
                    'uncertainty' => data_get($yield, 'uncertainty'),
                    'confidence' => data_get($apiResult, 'confidence_score'),
                    'fallback' => false,
                    'source_type' => 'Trained Model API',
                    'source_name' => 'iClimate ML service trained Random Forest model',
                    'source_credit' => 'Output generated by the iClimate ML service using the trained iClimate Random Forest model.',
                ], null];
            }

            $apiError = 'The trained rice-yield model returned an invalid response.';
        } catch (Throwable $exception) {
            $apiError = $exception->getMessage();
        }

        return [null, $apiError];
    }

    /**
     * Shared last-resort estimate used identically by both features when the
     * Flask API and the local script are both unreachable.
     */
    private function localYieldFallback(array $modelInput): array
    {
        $season = (string) $modelInput['season'];

        $records = RiceProduction::query()
            ->where('season', $season)
            ->whereNotNull('yield_per_hectare')
            ->latest('year')
            ->take(8)
            ->get();

        $baseYield = $records->isNotEmpty()
            ? (float) $records->avg('yield_per_hectare')
            : (strtolower($season) === 'dry' ? 3.6 : 4.1);

        $rainfall = (float) $modelInput['rainfall'];
        $temperature = (float) $modelInput['temp_avg'];
        $humidity = (float) $modelInput['humidity'];
        $windSpeed = (float) $modelInput['wind_speed'];
        $farmType = strtolower((string) $modelInput['farm_type']);

        $adjustment = match (true) {
            $rainfall < 80 => -0.8,
            $rainfall < 120 => -0.35,
            $rainfall >= 180 && $rainfall <= 280 => 0.25,
            $rainfall > 350 => -0.65,
            $rainfall > 300 => -0.35,
            default => 0.0,
        };

        $adjustment += match (true) {
            $temperature > 34 => -0.4,
            $temperature > 32 => -0.2,
            $temperature < 22 => -0.25,
            default => 0.0,
        };

        if ($humidity > 92) {
            $adjustment -= 0.15;
        }

        if ($windSpeed > 30) {
            $adjustment -= 0.25;
        } elseif ($windSpeed > 20) {
            $adjustment -= 0.1;
        }

        if ($farmType === 'irrigated') {
            $adjustment += 0.15;
        } elseif ($farmType === 'rainfed' && strtolower($season) === 'dry') {
            $adjustment -= 0.25;
        }

        $predictedYield = max(1.2, min(6.5, $baseYield + $adjustment));

        return [
            'predicted_yield' => round($predictedYield, 2),
            'unit' => 'tons/hectare',
            'uncertainty' => null,
            'confidence' => null,
            'fallback' => true,
            'source_type' => 'Local Estimate',
            'source_name' => 'Local estimate — trained model unavailable',
            'source_credit' => 'Estimated locally from recent rice production records and weather adjustments because no trained model was reachable.',
            'records_used' => $records->count(),
        ];
    }
}
