<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Models\ClimateRecord;
use App\Models\FarmerOrganization;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\DecisionSupportService;
use App\Services\PythonService;
use App\Support\ActiveFarm;
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

        // The climate inputs of THIS target month: lags and trailing windows end at the month before it (never the
        // latest months on file, which gave every prediction the same eight inputs), Rainfall_6M is a six-month mean
        // and TEMP_RANGE the month's TMAX - TMIN, as the model was trained. Unobserved months stay missing.
        $climate = \App\Support\YieldModelClimateWindow::features($targetDate, $weatherResult['predictions'] ?? []);
        $modelInput = $this->buildModelInput($weatherResult, $climate, $farmType, $targetDate, $areaOverride, $user, $manualOverrides);
        $missing = $this->missingInputs($modelInput);
        $weatherLabel = $modelInput['rainfall'] !== null ? WeatherLabeler::fromRainfall((float) $modelInput['rainfall']) : null;

        [$yield, $apiError] = $missing === []
            ? $this->requestYieldFromModel($modelInput)
            : [null, 'Not enough verified input data to run the rice-yield model (missing: '.implode(', ', $missing).').'];

        if ($apiError !== null) {
            // Real problems stay visible to IT in the logs (System Logs / Health); the farmer sees a plain "not available".
            \Illuminate\Support\Facades\Log::warning('Rice-yield prediction was not produced.', ['reason' => $apiError, 'missing_inputs' => $missing, 'target_month' => $targetMonth->format('Y-m'), 'missing_months' => array_values(array_unique(array_merge($climate['missing_months'] ?? [], $climate['seasonal_missing_months'] ?? [])))]);
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
            // Where the observed-month inputs came from: months read from the Open-Meteo archive (ERA5) because neither
            // the Ambulong station file nor a MAO Climate Record covers them.
            'climate_sources' => [
                'reanalysis_months' => $climate['reanalysis_months'] ?? [],
                'forecast_months' => array_values(array_unique(array_merge($climate['lag_forecast_months'] ?? [], $climate['seasonal_forecast_months'] ?? []))),
                'missing_months' => array_values(array_unique(array_merge($climate['missing_months'] ?? [], $climate['seasonal_missing_months'] ?? []))),
            ],
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
            function (string $key) use ($modelInput): bool {
                $value = $modelInput[$key] ?? null;
                if ($value === null || $value === '') {
                    return true;
                }
                if ($key === 'season') {
                    return false;
                }

                return ! is_numeric($value) || ! is_finite((float) $value) || (float) $value === -999.0;
            },
        ));
    }

    /**
     * Every value is either a real number from a real source or null. Nothing is
     * substituted: no zero for a missing reading, no forecast standing in for a
     * "previous month" value, no default farm area.
     */
    private function buildModelInput(
        array $weatherResult,
        array $climate,
        string $farmType,
        CarbonImmutable $targetDate,
        ?float $areaOverride,
        ?User $user,
        array $manualOverrides,
    ): array {
        $predictions = $weatherResult['predictions'] ?? [];

        $num = static fn ($v): ?float => is_numeric($v) ? (float) $v : null;
        $round = static fn (?float $v): ?float => $v === null ? null : round($v, 2);

        $rainfall = $num($predictions['rainfall'] ?? null);
        $temperature = $num($predictions['temperature'] ?? null);
        $humidity = $num($predictions['humidity'] ?? null);
        $windSpeed = $num($predictions['wind_speed'] ?? null);
        // The deployed model's "season" input and its same-season months use the encoding it was trained with,
        // worked out from the month (never a stored label), so the iClimate calendar can differ (App\Support\RiceSeason).
        $season = \App\Support\RiceSeason::forTrainedYieldModel((int) $targetDate->format('n'));

        $activeFarm = $user && $user->role === User::ROLE_FARMER ? ActiveFarm::current($user) : null;
        $farmArea = $activeFarm?->modelArea();
        $area = $areaOverride
            ?? $farmArea
            ?? $user?->farmerProfile?->farm_area
            ?? ($user ? null : RiceProduction::query()->avg('area_hectares'));
        $area = $num($area);
        $area = $area !== null && $area > 0 ? $area : null;

        $modelInput = [
            'rainfall' => $round($rainfall),
            'temp_avg' => $round($temperature),
            'temp_range' => $climate['features']['temp_range'],
            'area' => $round($area),
            'previous_rainfall' => $climate['features']['previous_rainfall'],
            'previous_temp' => $climate['features']['previous_temp'],
            'previous_humidity' => $climate['features']['previous_humidity'],
            'previous_wind_speed' => $climate['features']['previous_wind_speed'],
            'rainfall_6m' => $climate['features']['rainfall_6m'],
            'temp_3m' => $climate['features']['temp_3m'],
            'temp_6m' => $climate['features']['temp_6m'],
            'seasonal_rainfall' => $climate['features']['seasonal_rainfall'],
            'seasonal_temp' => $climate['features']['seasonal_temp'],
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
     * Resolve the deployed model's Area feature for an All-of-Lian request.
     *
     * The deployed model was trained with a representative farm area, not a
     * municipality total. For a municipality request use the authoritative
     * organization area divided by its recorded farmer count. This is a
     * municipality-level input and is never a farmer prediction or a selected
     * farmer's farm size. Existing rice-production records and the maintained
     * Lian farm-type workbook are legitimate fallbacks for older installations.
     */
    public function municipalityArea(): ?float
    {
        try {
            $organizationArea = (float) FarmerOrganization::query()
                ->whereNotNull('area_hectares')
                ->where('area_hectares', '>', 0)
                ->sum('area_hectares');
            $organizationFarmers = (int) FarmerOrganization::query()
                ->whereNotNull('farmer_count')
                ->where('farmer_count', '>', 0)
                ->sum('farmer_count');

            if ($organizationArea > 0 && $organizationFarmers > 0) {
                return round($organizationArea / $organizationFarmers, 2);
            }
        } catch (Throwable) {
            // Continue through the data-backed fallbacks below.
        }

        try {
            $productionArea = RiceProduction::query()->avg('area_hectares');
            if (is_numeric($productionArea) && (float) $productionArea > 0) {
                return round((float) $productionArea, 2);
            }
        } catch (Throwable) {
            // Continue to the maintained municipality source below.
        }

        $records = \App\Support\LianFarmTypes::records();
        $workbookArea = (float) $records->sum('area_hectares');
        $workbookFarmers = (int) $records->sum('farmers');

        return $workbookArea > 0 && $workbookFarmers > 0
            ? round($workbookArea / $workbookFarmers, 2)
            : null;
    }

    /**
     * The single "ask the trained model" path — normally calls the iClimate ML
     * service (python_scripts/main.py, FastAPI), with PythonService's controlled
     * local inference path for a missing local service. Both paths load the same
     * deployed artifact and feature schema; neither trains or invents a value.
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

            if (is_numeric($predicted) && is_finite((float) $predicted)) {
                return [[
                    'predicted_yield' => round((float) $predicted, 2),
                    'unit' => data_get($yield, 'unit', 'tons/hectare'),
                    'uncertainty' => data_get($yield, 'uncertainty'),
                    'fallback' => false,
                    'source_type' => data_get($yield, 'source_type', data_get($apiResult, 'source_type', 'Trained Model API')),
                    'inference_transport' => data_get($apiResult, 'inference_transport', 'http'),
                    'api_fallback_used' => (bool) data_get($apiResult, 'api_fallback_used', false),
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
