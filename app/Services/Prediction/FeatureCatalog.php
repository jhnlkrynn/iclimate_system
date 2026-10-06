<?php

namespace App\Services\Prediction;

/**
 * Single source of truth for every variable the rice-yield model(s) use —
 * what it means, its unit, and its expected directional effect on yield.
 *
 * SCHEMA RECONCILIATION
 * ----------------------
 * Two feature schemas currently exist in this codebase, and this catalog
 * deliberately covers both instead of picking a winner prematurely:
 *
 * - DEPLOYED (12 features, lag/rolling-window engineered): the exact schema
 *   baked into the currently-live storage/models/rice_yield_model_final.pkl,
 *   defined canonically by RiceYieldPredictionService::INPUT_TO_MODEL_FEATURE
 *   and python_scripts/rice_yield_service.py's rice_features(). This is what
 *   actually serves predictions today. Do not change
 *   INPUT_TO_MODEL_FEATURE's keys/order without retraining the deployed
 *   model to match — tests/Feature/PredictionParityTest.php asserts its
 *   exact shape.
 *
 * - TARGET (7 features, no lag engineering, adds irrigation_type): the
 *   schema python_scripts/compare_yield_features.py,
 *   python_scripts/evaluation_service.py, and
 *   python_scripts/train_rice_yield_model.py already use for the live
 *   model-evaluation pipeline (Phase 1/2 of the ML migration). Chosen as
 *   the target for three reasons: (1) it doesn't require dense, gap-free
 *   monthly climate history per barangay to compute rolling windows, which
 *   real MAO/PAGASA records realistically won't provide at season
 *   granularity; (2) app\Services\Prediction\PredictionEngine already
 *   computes humidity/wind_speed on every request but silently drops them
 *   before they reach the deployed model — Phase 1's cross-validated
 *   comparison showed including them modestly improved R²; (3) it adds
 *   irrigation_type, which the thesis's own Objective 2 names explicitly
 *   ("...under varying conditions, such as wet/dry seasons and
 *   irrigated/rainfed farming systems") and the deployed schema omits
 *   entirely.
 *
 * The DEPLOYED schema stays authoritative until real historical data
 * (see docs/capstone gap-analysis "Phase 0") is imported and a new model is
 * trained and evaluated on the TARGET schema — at that point the TARGET
 * schema becomes DEPLOYED and this catalog's `deployed` schema block should
 * be replaced, not duplicated.
 *
 * The "behavior" text below is grounded in this app's own already-encoded
 * domain thresholds (App\Services\Prediction\PredictionEngine::localYieldFallback()
 * and App\Services\Risk\AgriculturalRiskScorer), so the description a
 * farmer sees is consistent with what the rule-based fallback actually
 * does when the trained model is unavailable. These thresholds are not yet
 * backed by cited literature — see docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md
 * ("[LITERATURE SOURCE REQUIRED]") for the manuscript-facing version of the
 * same caveat.
 */
final class FeatureCatalog
{
    public const SCHEMA_DEPLOYED = 'deployed';

    public const SCHEMA_TARGET = 'target';

    /**
     * @var array<string, array{
     *     label: string,
     *     unit: string,
     *     schemas: array<int, string>,
     *     model_keys: array<string, string>,
     *     description: string,
     *     behavior: string,
     * }>
     */
    private const FEATURES = [
        'rainfall' => [
            'label' => 'Rainfall',
            'unit' => 'mm',
            'schemas' => [self::SCHEMA_DEPLOYED, self::SCHEMA_TARGET],
            'model_keys' => ['deployed' => 'RAINFALL', 'target' => 'rainfall'],
            'description' => 'Rainfall for the target period. The single strongest driver of rice yield in the model — rice needs standing or near-saturated soil water through most of its growth cycle.',
            'behavior' => 'Yield is highest in a moderate band (roughly 120-280mm). Below ~80mm the app applies a strong drought penalty; above ~300-350mm it applies a flooding/waterlogging penalty. Both extremes reduce yield — this is not a "more rain is always better" relationship.',
        ],
        'temperature' => [
            'label' => 'Temperature (Average)',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED, self::SCHEMA_TARGET],
            'model_keys' => ['deployed' => 'TEMP_AVG', 'target' => 'temperature'],
            'description' => 'Average air temperature for the target period. Affects photosynthesis rate, grain-filling, and heat/cold stress on the rice plant.',
            'behavior' => 'Favorable roughly between 22°C and 32°C. Above ~32°C the app applies a heat-stress penalty (steeper above 34°C); below ~22°C it applies a cold-stress penalty. Extreme heat during flowering is the most damaging case in practice.',
        ],
        'temp_range' => [
            'label' => 'Temperature Range',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'TEMP_RANGE'],
            'description' => 'Spread between the highest and lowest recorded temperature in the period (a volatility/stress indicator, not an average).',
            'behavior' => 'A wider range signals more erratic conditions (e.g. cold nights against hot days), which stresses seedlings more than a stable temperature at the same average. Not currently used by the TARGET schema.',
        ],
        'area' => [
            'label' => 'Farm Area',
            'unit' => 'hectares',
            'schemas' => [self::SCHEMA_DEPLOYED, self::SCHEMA_TARGET],
            'model_keys' => ['deployed' => 'Area', 'target' => 'area_hectares'],
            'description' => "The farmer's (or the prediction request's) field size.",
            'behavior' => "Included as a model input, but it scales total production, not yield-per-hectare — its effect on the model's yield-per-hectare output should be small and is mainly there to let the model pick up any farm-size-correlated effects present in the training data (e.g. larger fields tending toward one irrigation type). Kept in the TARGET schema on the theory that larger farms tend to have better access to mechanization, credit, and bulk input pricing (economies of scale), which can shift yield-per-hectare even after normalizing for size — not because area itself directly causes a higher rate.",
        ],
        'previous_rainfall' => [
            'label' => 'Previous Month Rainfall',
            'unit' => 'mm',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Previous_Rainfall'],
            'description' => "Rainfall recorded in the month immediately before the target period — carries the prior period's soil-moisture memory into the current one.",
            'behavior' => 'A wet previous month with a dry current month can still leave usable residual soil moisture; a dry previous month compounds a dry current month. Requires reasonably continuous monthly records to be meaningful, which is why the TARGET schema drops it.',
        ],
        'previous_temp' => [
            'label' => 'Previous Month Temperature',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Previous_Temp'],
            'description' => 'Average temperature in the month immediately before the target period — a short-term thermal trend/momentum signal.',
            'behavior' => 'Used alongside the current temperature so the model can distinguish a sudden temperature swing from a stable trend, which affect plant stress differently even at the same current-month average.',
        ],
        'rainfall_6m' => [
            'label' => '6-Month Rainfall',
            'unit' => 'mm',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Rainfall_6M'],
            'description' => 'Rainfall aggregated over the trailing 6 months — a season-long water-balance signal rather than a single-month snapshot.',
            'behavior' => 'Captures cumulative drought or waterlogging risk that a single month of data would miss (e.g. one good rainy month cannot offset a season of drought). Requires 6 months of continuous records per location, which real MAO/PAGASA exports may not provide — dropped in the TARGET schema for this reason.',
        ],
        'temp_3m' => [
            'label' => '3-Month Temperature',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Temp_3M'],
            'description' => 'Average temperature over the trailing 3 months — a shorter thermal-trend window than the 6-month figure.',
            'behavior' => 'Smooths out single-month temperature spikes so the model reacts to a sustained warm or cool trend rather than one unusually hot or cold month.',
        ],
        'temp_6m' => [
            'label' => '6-Month Temperature',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Temp_6M'],
            'description' => 'Average temperature over the trailing 6 months.',
            'behavior' => 'Same idea as the 3-month figure but over a longer window — helps the model separate a season-long warming/cooling trend from short-term noise.',
        ],
        'seasonal_rainfall' => [
            'label' => 'Seasonal Rainfall',
            'unit' => 'mm',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Seasonal_Rainfall'],
            'description' => 'Cumulative rainfall recorded so far within the current wet/dry season, as opposed to a rolling calendar window.',
            'behavior' => 'Ties the rainfall figure to the Philippine wet/dry season cycle specifically, since rice planting decisions are usually made in reference to the season, not the calendar month. High seasonal rainfall in the wet season is expected and normal; the same total in the dry season would be unusual and worth flagging.',
        ],
        'seasonal_temp' => [
            'label' => 'Seasonal Temperature',
            'unit' => '°C',
            'schemas' => [self::SCHEMA_DEPLOYED],
            'model_keys' => ['deployed' => 'Seasonal_Temp'],
            'description' => 'Average temperature recorded so far within the current wet/dry season.',
            'behavior' => 'Same season-relative framing as seasonal rainfall — lets the model compare the current temperature against what is typical for this season rather than the calendar year as a whole.',
        ],
        'season' => [
            'label' => 'Season',
            'unit' => 'Wet or Dry',
            'schemas' => [self::SCHEMA_DEPLOYED, self::SCHEMA_TARGET],
            'model_keys' => ['deployed' => 'Season', 'target' => 'season'],
            'description' => 'Whether the target period falls in the wet or dry season in Lian, Batangas, encoded as in the training data of the model (wet May-September). iClimate pages use wet May-October (App\Support\RiceSeason).',
            'behavior' => 'One of the strongest categorical predictors in Philippine rice farming — wet- and dry-season yields follow different baselines even before weather variation is considered, because irrigation dependence, pest pressure, and typhoon exposure differ by season.',
        ],
        'humidity' => [
            'label' => 'Humidity',
            'unit' => '% relative humidity',
            'schemas' => [self::SCHEMA_TARGET],
            'model_keys' => ['target' => 'humidity'],
            'description' => 'Relative humidity for the target period. Currently computed by PredictionEngine on every request but not passed to the DEPLOYED model — only used by the TARGET schema and by the rule-based fallback/warnings.',
            'behavior' => "Sustained high humidity (the app's threshold is >92%) raises fungal disease risk and is treated as a mild yield penalty. Moderate humidity is otherwise not a strong standalone driver — its effect is mostly through disease/pest pressure rather than the plant's water supply directly.",
        ],
        'wind_speed' => [
            'label' => 'Wind Speed',
            // UNRESOLVED: this was previously asserted as 'km/h' without a
            // sourced basis. When this feature is populated from the
            // Ambulong historical CSV (via MonthlyWeatherRandomForest), its
            // unit is not established by any repository or manuscript
            // evidence (values run ~1-3 across 131 months, implausibly low
            // for km/h). When populated from live Open-Meteo wind readings
            // elsewhere in the app, that source IS confirmed km/h (Open-Meteo's
            // documented API default). Do not re-assert 'km/h' here without
            // sourced provenance for the historical dataset.
            'unit' => 'unresolved for the historical dataset (see AmbulongHistoricalClimateDataset provenance notes); confirmed km/h only for live Open-Meteo-sourced readings',
            'schemas' => [self::SCHEMA_TARGET],
            'model_keys' => ['target' => 'wind_speed'],
            'description' => 'Wind speed for the target period. Same status as humidity — computed on every request but only reaches the TARGET schema and the rule-based fallback today.',
            'behavior' => 'Low impact under normal conditions. The app penalizes yield once wind speed passes ~20 (lodging risk — stalks bending/breaking) and more heavily past ~30 (storm-level, structural crop damage) on whatever scale the feeding source uses — see the unit note above; this threshold assumes km/h-scale input and may not fire meaningfully against the historical dataset\'s ~1-3 scale.',
        ],
        'irrigation_type' => [
            'label' => 'Irrigation Type',
            'unit' => 'Irrigated or Rainfed',
            'schemas' => [self::SCHEMA_TARGET],
            'model_keys' => ['target' => 'irrigation_type'],
            'description' => "Whether the farm has controlled irrigation or depends on rainfall alone. Named explicitly in the study's own objectives as a condition to compare yield under, but absent from the DEPLOYED schema entirely — this is the clearest gap the TARGET schema closes.",
            'behavior' => 'Irrigated fields get a flat yield bonus in the rule-based fallback and are far less exposed to drought-driven yield loss than rainfed fields, especially in the dry season, where rainfed farms take an additional penalty. This is expected to be one of the more influential TARGET-schema features once real data is available to confirm it statistically.',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::FEATURES;
    }

    /**
     * @return array<string, array<string, mixed>> keyed the same as all(), filtered to one schema
     */
    public static function forSchema(string $schema): array
    {
        return array_filter(self::FEATURES, fn (array $feature): bool => in_array($schema, $feature['schemas'], true));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        return self::FEATURES[$key] ?? null;
    }

    /**
     * Look up a catalog entry by its model-facing column name (e.g. "RAINFALL"
     * or "irrigation_type") instead of the internal catalog key.
     *
     * @return array<string, mixed>|null
     */
    public static function byModelKey(string $modelKey, string $schema = self::SCHEMA_DEPLOYED): ?array
    {
        foreach (self::FEATURES as $key => $feature) {
            if (($feature['model_keys'][$schema] ?? null) === $modelKey) {
                return ['key' => $key, ...$feature];
            }
        }

        return null;
    }

    /**
     * At-a-glance status for a feature's current numeric value, reusing the
     * exact same thresholds already shown elsewhere on the prediction pages
     * (WeatherLabeler) so a value never gets described two different ways
     * in two different places. Returns null for features that don't have a
     * meaningful "good/bad" direction (e.g. farm area, season, irrigation
     * type) — forcing a badge onto those would be arbitrary, not helpful.
     *
     * @return array{label: string, tone: string}|null
     */
    public static function classify(string $key, float $value): ?array
    {
        if ($key === 'temp_range') {
            return $value > 8.0
                ? ['label' => 'Volatile', 'tone' => 'tone-amber']
                : ['label' => 'Stable', 'tone' => 'tone-green'];
        }

        if (str_contains($key, 'rainfall')) {
            return match (WeatherLabeler::fromRainfall($value)) {
                'Heavy Rain' => ['label' => 'Heavy Rain', 'tone' => 'tone-red'],
                'Dry' => ['label' => 'Dry', 'tone' => 'tone-amber'],
                default => ['label' => 'Workable Range', 'tone' => 'tone-green'],
            };
        }

        if (str_contains($key, 'temp')) {
            return match (WeatherLabeler::fromTemperature($value)) {
                'Hot' => ['label' => 'Hot', 'tone' => 'tone-red'],
                'Cool' => ['label' => 'Cool', 'tone' => 'tone-amber'],
                default => ['label' => 'Favorable', 'tone' => 'tone-green'],
            };
        }

        return null;
    }
}
