<?php

namespace App\Services\Prediction;

use App\Enums\PredictionType;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\MlModel;
use App\Models\User;
use App\Support\ActiveFarm;
use App\Services\MachineLearning\ModelRegistryService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RiceYieldPredictionService
{
    public const INPUT_TO_MODEL_FEATURE = [
        'rainfall' => 'RAINFALL',
        'temp_avg' => 'TEMP_AVG',
        'temp_range' => 'TEMP_RANGE',
        'area' => 'Area',
        'previous_rainfall' => 'Previous_Rainfall',
        'previous_temp' => 'Previous_Temp',
        'rainfall_6m' => 'Rainfall_6M',
        'temp_3m' => 'Temp_3M',
        'temp_6m' => 'Temp_6M',
        'seasonal_rainfall' => 'Seasonal_Rainfall',
        'seasonal_temp' => 'Seasonal_Temp',
        'season' => 'Season',
    ];

    public function __construct(
        private readonly PredictionEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $manualOverrides
     * @return array<string, mixed>
     */
    public function predictForUser(
        CarbonImmutable $targetDate,
        string $farmType,
        ?float $areaOverride = null,
        ?User $user = null,
        array $manualOverrides = [],
        ?string $predictionType = null,
    ): array {
        if ($predictionType !== null && $predictionType !== PredictionType::RICE_YIELD->value) {
            throw new InvalidArgumentException('RiceYieldPredictionService only accepts rice-yield predictions.');
        }

        $farmType = $this->normalizeFarmType($farmType);
        $manualOverrides = $this->normalizeManualOverrides($manualOverrides);

        $engineResult = $this->engine->predict($targetDate, $farmType, $areaOverride, $user, $manualOverrides);

        return $this->standardizeResult($engineResult, $areaOverride, $user, $manualOverrides);
    }

    /**
     * @param  array<string, mixed>  $engineResult
     * @param  array<string, mixed>  $manualOverrides
     * @return array<string, mixed>
     */
    public function standardizeResult(array $engineResult, ?float $areaOverride = null, ?User $user = null, array $manualOverrides = []): array
    {
        $yield = $engineResult['yield'] ?? [];
        $yield = is_array($yield) ? $yield : [];
        $modelInput = $engineResult['model_input'] ?? [];
        $decision = $engineResult['decision_support'] ?? [];
        $decimals = (int) config('prediction.rice_yield.display_decimals', 2);
        $predictedYield = array_key_exists('predicted_yield', $yield) && $yield['predicted_yield'] !== null
            ? round((float) $yield['predicted_yield'], $decimals)
            : null;
        $area = is_numeric($modelInput['area'] ?? null) ? round((float) $modelInput['area'], 2) : null;
        $totalProduction = $predictedYield !== null && $area !== null
            ? round($predictedYield * $area, $decimals)
            : null;
        $riskLevel = data_get($decision, 'risk.level');
        $riskLabel = data_get($decision, 'risk.label');
        $riskScore = data_get($decision, 'risk.score');
        $conditionScore = data_get($decision, 'score.value');
        $conditionInterpretation = data_get($decision, 'score.interpretation');
        $weatherAssessment = data_get($decision, 'overall_recommendation.weather')
            ?? data_get($decision, 'weather.summary');

        $result = [
            ...$yield,
            'success' => $predictedYield !== null,
            'prediction_type' => 'rice_yield',
            'yield_tons_per_hectare' => $predictedYield,
            'predicted_yield_raw' => $predictedYield,
            'predicted_yield' => $predictedYield,
            'predicted_yield_tons_per_hectare' => $predictedYield,
            'unit' => $yield['unit'] ?? 'tons/hectare',
            'farm_area_hectares' => $area,
            'estimated_total_production_tons' => $totalProduction,
            'season' => $modelInput['season'] ?? null,
            'farm_type' => $modelInput['farm_type'] ?? null,
            'target_date' => data_get($engineResult, 'target_date')?->toDateString(),
            'target_month' => data_get($engineResult, 'target_month')?->format('Y-m'),
            'model' => 'Random Forest',
            'model_source' => $this->modelSource(),
            'model_version' => $this->modelVersion(),
            'feature_order' => array_values(self::INPUT_TO_MODEL_FEATURE),
            'features' => $this->orderedModelFeatures($modelInput),
            'inputs_used' => $modelInput,
            'model_input' => $modelInput,
            'input_sources' => $this->inputSources($modelInput, $areaOverride, $user, $manualOverrides),
            'source' => $yield['source_name'] ?? 'iClimate Rice Harvest Estimate',
            'planting_advisory' => data_get($decision, 'planting.recommendation'),
            'irrigation_recommendation' => data_get($decision, 'irrigation.recommendation'),
            'weather_assessment' => $weatherAssessment,
            'risk_level' => $riskLevel,
            'risk_label' => $riskLabel,
            'risk_score' => $riskScore,
            'condition_score' => $conditionScore,
            'condition_score_label' => $conditionInterpretation,
            'notifications' => $decision['notifications'] ?? [],
            'decision_support' => $decision,
            'api_error' => $engineResult['api_error'] ?? null,
            'missing_inputs' => $engineResult['missing_inputs'] ?? [],
            'engine_result' => $engineResult,
        ];

        // A prediction that was made must be complete. When the model could not run
        // (missing inputs / service down) there is nothing to complete: success=false.
        if ($predictedYield !== null) {
            $this->assertCompleteResult($result);
        }

        return $result;
    }

    public function normalizeFarmType(?string $farmType): string
    {
        $value = strtolower(trim((string) $farmType));

        return match (true) {
            str_contains($value, 'irrigated'), str_contains($value, 'irigation'), str_contains($value, 'may irigasyon') => FarmerProfile::FARM_TYPE_IRRIGATED,
            default => FarmerProfile::FARM_TYPE_RAINFED,
        };
    }

    public function normalizeSeason(?string $season): ?string
    {
        $value = strtolower(trim((string) $season));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'dry'), str_contains($value, 'tag-init') => ClimateRecord::SEASON_DRY,
            str_contains($value, 'wet'), str_contains($value, 'rainy'), str_contains($value, 'tag-ulan') => ClimateRecord::SEASON_WET,
            default => null,
        };
    }

    /**
     * Path of the model to report: the registry's active model when it has one
     * whose file is present, otherwise the configured default. The registry
     * never blocks prediction; any problem resolves to the configured default.
     */
    public function modelSource(): string
    {
        return $this->activeRegistryModel()?->file_path ?? $this->configuredPath();
    }

    private function configuredPath(): string
    {
        return (string) config('prediction.rice_yield.model_path', 'storage/models/rice_yield_model_final.pkl');
    }

    public function modelVersion(): string
    {
        $active = $this->activeRegistryModel();
        $name = (string) config('prediction.rice_yield.model_name', 'rice_yield_model_final');

        // Keep the historical name while the registry points at the configured
        // file, so existing version strings are unchanged.
        if ($active !== null && $active->file_path !== $this->configuredPath()) {
            $name = 'rice_yield_model_v'.$active->version;
        }
        $path = base_path($this->modelSource());

        if (! is_file($path)) {
            return $name.':missing';
        }

        return $name.':'.substr((string) sha1_file($path), 0, 12);
    }

    private function activeRegistryModel(): ?MlModel
    {
        try {
            return app(ModelRegistryService::class)->resolveActive();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $modelInput
     * @return array<string, mixed>
     */
    private function orderedModelFeatures(array $modelInput): array
    {
        $features = [];

        foreach (self::INPUT_TO_MODEL_FEATURE as $inputName => $modelName) {
            $features[$modelName] = $modelInput[$inputName] ?? null;
        }

        return $features;
    }

    /**
     * @param  array<string, mixed>  $manualOverrides
     * @return array<string, mixed>
     */
    private function normalizeManualOverrides(array $manualOverrides): array
    {
        if (array_key_exists('season', $manualOverrides)) {
            $manualOverrides['season'] = $this->normalizeSeason((string) $manualOverrides['season']);
        }

        return array_filter($manualOverrides, fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $modelInput
     * @param  array<string, mixed>  $manualOverrides
     * @return array<string, array{value:mixed,source:string}>
     */
    private function inputSources(array $modelInput, ?float $areaOverride, ?User $user, array $manualOverrides): array
    {
        $sources = [];

        foreach ($modelInput as $key => $value) {
            $sources[$key] = [
                'value' => $value,
                'source' => match (true) {
                    array_key_exists($key, $manualOverrides) => 'user',
                    $key === 'area' && $areaOverride !== null => 'user',
                    $key === 'area' && $user && $user->role === User::ROLE_FARMER && ActiveFarm::current($user)?->modelArea() !== null => 'active_farm',
                    $key === 'area' && $user?->farmerProfile?->farm_area !== null => 'farmer_profile',
                    $key === 'area' => 'unavailable',
                    in_array($key, ['season', 'farm_type'], true) => 'profile_or_model_default',
                    default => 'shared_prediction_engine',
                },
            ];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function assertCompleteResult(array $result): void
    {
        $required = [
            'yield_tons_per_hectare',
            'farm_area_hectares',
            'estimated_total_production_tons',
            'weather_assessment',
            'planting_advisory',
            'irrigation_recommendation',
            'risk_level',
            'condition_score',
        ];

        foreach ($required as $field) {
            if (! array_key_exists($field, $result) || $result[$field] === null || $result[$field] === '') {
                throw new RuntimeException("Missing rice-yield prediction result field: {$field}");
            }
        }
    }
}
