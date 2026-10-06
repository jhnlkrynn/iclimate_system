<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\DecisionSupportService;
use App\Services\PythonService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The single computation path for rice-yield/weather predictions, shared by
 * the System Prediction page and the AI Assistant so identical inputs always
 * produce identical outputs.
 *
 * Data accuracy rule: this class never invents a number. A missing input is
 * reported (missing_inputs) and the model is not run; a failed model call
 * returns a null yield. There is no rule-based or hardcoded fallback yield.
 */
class PredictionEngine
{
    /**
     * Inputs the DEPLOYED models need (rice_yield_service.py rice_features() and
     * weather_features()). If any is unavailable the model is not run: a missing
     * input is reported, never replaced with a guess or a zero.
     */
    public const REQUIRED_MODEL_INPUTS = [
        'rainfall', 'temp_avg', 'temp_range', 'area', 'previous_rainfall', 'previous_temp',
        'previous_humidity', 'previous_wind_speed', 'rainfall_6m', 'temp_3m', 'temp_6m',
        'seasonal_rainfall', 'seasonal_temp', 'season', 'month_num',
    ];

    public function __construct(
        private readonly PredictionOrchestrator $orchestrator,
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
        // Reads through PredictionOrchestrator (persisted DB row / cache for
        // the 12-month pre-generated horizon) instead of calling
        // MonthlyWeatherRandomForest::predict() directly, so this shared
        // engine — used by the Farmer Dashboard yield widget and by
        // PlantingSchedulePredictionService on every schedule save — never
        // retrains a model on a normal request.
        $weatherResult = $this->orchestrator->monthlyWeatherForecast($targetMonth);

        $modelInput = $this->buildModelInput($weatherResult, $farmType, $targetDate, $areaOverride, $user, $manualOverrides);
        $missing = $this->missingInputs($modelInput);
        $weatherLabel = $modelInput['rainfall'] !== null ? WeatherLabeler::fromRainfall((float) $modelInput['rainfall']) : null;

        [$yield, $apiError] = $missing === []
            ? $this->requestYieldFromModel($modelInput)
            : [null, 'Not enough verified input data to run the rice-yield model (missing: '.implode(', ', $missing).').'];

        if ($apiError !== null) {
            // Real problems stay visible to IT in the logs (System Logs / Health); the farmer sees a plain "not available".
            \Illuminate\Support\Facades\Log::warning('Rice-yield prediction was not produced.', ['reason' => $apiError, 'missing_inputs' => $missing, 'target_month' => $targetMonth->format('Y-m')]);
        }

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
            'missing_inputs' => $missing,
            'yield' => $yield,
            'decision_support' => $decision,
            'api_error' => $apiError,
        ];
    }

    /** @return list<string> required model inputs that are null */
    private function missingInputs(array $modelInput): array
    {
        return array_values(array_filter(
            self::REQUIRED_MODEL_INPUTS,
            fn (string $key): bool => ! isset($modelInput[$key]) || $modelInput[$key] === '',
        ));
    }

    /**
     * Every value is either a real number from a real source or null. Nothing is
     * substituted: no zero for a missing reading, no forecast standing in for a
     * "previous month" value, no default farm area.
     */
    private function buildModelInput(
        array $weatherResult,
        string $farmType,
        CarbonImmutable $targetDate,
        ?float $areaOverride,
        ?User $user,
        array $manualOverrides,
    ): array {
        $predictions = $weatherResult['predictions'] ?? [];
        // The recent real climate behind the "previous" and trailing-window inputs: the latest six months of
        // App\Support\ClimateHistory (entered records when they cover six months, otherwise the Ambulong
        // station record; never impossible rows, never seeded sample rows). The on-demand prediction run by
        // the Python service reads the same history, so both give the same inputs.
        $recent = \App\Support\ClimateHistory::months()->take(\App\Support\ClimateHistory::WINDOW)->values();
        $latest = $recent->first();

        $num = static fn ($v): ?float => is_numeric($v) ? (float) $v : null;
        $round = static fn (?float $v): ?float => $v === null ? null : round($v, 2);

        $rainfall = $num($predictions['rainfall'] ?? null);
        $temperature = $num($predictions['temperature'] ?? null);
        $humidity = $num($predictions['humidity'] ?? null);
        $windSpeed = $num($predictions['wind_speed'] ?? null);
        // The deployed model's "season" input and its same-season months use the encoding it was trained with,
        // worked out from the month (never a stored label), so the iClimate calendar can differ (App\Support\RiceSeason).
        $season = \App\Support\RiceSeason::forTrainedYieldModel((int) $targetDate->format('n'));
        $modelSeasonOf = static fn (object $row): ?string => ! empty($row->record_date) ? \App\Support\RiceSeason::forTrainedYieldModel((int) substr((string) $row->record_date, 5, 2)) : null;

        // Trailing windows are only valid when the whole window is present.
        $has6 = $recent->count() >= 6;
        $has3 = $recent->count() >= 3;
        $inSeason = $recent->filter(fn (object $row): bool => $modelSeasonOf($row) === $season);
        $temps = $recent->pluck('temperature')->filter(fn ($v) => $v !== null);

        $area = $areaOverride
            ?? $user?->farmerProfile?->farm_area
            ?? ($user ? null : RiceProduction::query()->avg('area_hectares'));
        $area = $num($area);
        $area = $area !== null && $area > 0 ? $area : null;

        $modelInput = [
            'rainfall' => $round($rainfall),
            'temp_avg' => $round($temperature),
            'temp_range' => $temps->isNotEmpty() ? round((float) $temps->max() - (float) $temps->min(), 2) : null,
            'area' => $round($area),
            'previous_rainfall' => $round($num($latest?->rainfall)),
            'previous_temp' => $round($num($latest?->temperature)),
            'previous_humidity' => $round($num($latest?->humidity)),
            'previous_wind_speed' => $round($num($latest?->wind_speed)),
            'rainfall_6m' => $has6 ? round((float) $recent->sum('rainfall'), 2) : null,
            'temp_3m' => $has3 ? $round($num($recent->take(3)->avg('temperature'))) : null,
            'temp_6m' => $has6 ? $round($num($recent->avg('temperature'))) : null,
            'seasonal_rainfall' => $inSeason->isNotEmpty() ? round((float) $inSeason->sum('rainfall'), 2) : null,
            'seasonal_temp' => $inSeason->isNotEmpty() ? $round($num($inSeason->avg('temperature'))) : null,
            'humidity' => $round($humidity),
            'wind_speed' => $round($windSpeed),
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

    /**
     * The single "ask the trained model" path — calls the iClimate ML service
     * (python_scripts/main.py, FastAPI). Shared by every feature that needs a
     * raw model yield so results never diverge. There is NO fallback: on any
     * failure (network error, invalid response) the yield is null and the error
     * message is returned. A number is only ever returned if the model made it.
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

            if (is_numeric($predicted)) {
                return [[
                    'predicted_yield' => round((float) $predicted, 2),
                    'unit' => data_get($yield, 'unit', 'tons/hectare'),
                    'uncertainty' => data_get($yield, 'uncertainty'),
                    'confidence' => data_get($apiResult, 'confidence_score'),
                    'fallback' => false,
                    'source_type' => 'Trained Model API',
                    'source_name' => 'iClimate Rice Harvest Estimate',
                    'source_credit' => 'Estimate produced by the iClimate rice-yield system.',
                ], null];
            }

            $apiError = 'The rice-yield system did not return a usable result.';
        } catch (Throwable $exception) {
            $apiError = $exception->getMessage();
        }

        return [null, $apiError];
    }
}
