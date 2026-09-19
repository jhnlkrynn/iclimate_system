<?php

namespace App\Services;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\HeatmapAreaForecast;
use App\Models\RiceProduction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionEngine;
use App\Services\Risk\AgriculturalRiskScorer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HeatmapRiskService
{
    /** @var array<string, float|null> */
    private array $yieldPredictionMemo = [];

    private const DEFAULT_AREAS = [
        'Bagong Pook' => [14.0182000, 120.6408000],
        'Balibago' => [13.9898000, 120.6524000],
        'Barangay 1 (Pob.)' => [14.0361000, 120.6507000],
        'Barangay 2 (Pob.)' => [14.0371000, 120.6530000],
        'Barangay 3 (Pob.)' => [14.0349000, 120.6489000],
        'Barangay 4 (Pob.)' => [14.0383000, 120.6484000],
        'Barangay 5 (Pob.)' => [14.0339000, 120.6525000],
        'Binubusan' => [14.0296000, 120.6364000],
        'Bungahan' => [14.0480000, 120.6285000],
        'Cumba' => [14.0205000, 120.7045000],
        'Humayingan' => [14.0108000, 120.6888000],
        'Kapito' => [14.0557000, 120.6765000],
        'Lumaniag' => [14.0479000, 120.6613000],
        'Luyahan' => [13.9926000, 120.6355000],
        'Malaruhatan' => [14.0119000, 120.6669000],
        'Matabungkay' => [13.9593000, 120.6227000],
        'Prenza' => [14.0353000, 120.6822000],
        'Puting-Kahoy' => [14.0290000, 120.7015000],
        'San Diego' => [13.9793000, 120.6118000],
    ];

    public function __construct(
        private readonly MonthlyWeatherRandomForest $weatherForest,
        private readonly WeatherApiService $weatherApi,
        private readonly DecisionSupportService $decisionSupport,
        private readonly AgriculturalRiskScorer $riskScorer,
        private readonly PredictionEngine $predictionEngine,
    ) {}

    public function refresh(): void
    {
        $this->ensureDefaultAreas();

        $targetMonth = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        $weather = $this->weatherInputs($targetMonth);
        $barangays = $this->barangays();
        $productionByBarangay = $this->productionByBarangay($barangays);
        $failureCounterKey = 'metrics:heatmap-refresh:failures:'.today()->toDateString();

        foreach ($barangays as $barangay) {
            try {
                $updates = $this->computeUpdatesForBarangay(
                    $barangay,
                    $weather,
                    $productionByBarangay->get($barangay, collect()),
                    $targetMonth,
                );

                if ($updates === null) {
                    continue;
                }

                if ($this->defaultLatitude($barangay) !== null && $this->defaultLongitude($barangay) !== null) {
                    $updates['fields']['latitude'] = $this->defaultLatitude($barangay);
                    $updates['fields']['longitude'] = $this->defaultLongitude($barangay);
                }

                HeatmapArea::query()->updateOrCreate(
                    ['barangay' => $barangay],
                    $updates['fields']
                );

                if (isset($updates['risk_breakdown'])) {
                    Cache::put(self::riskBreakdownCacheKey($barangay), $updates['risk_breakdown'], now()->addDays(7));
                }
            } catch (\Throwable $exception) {
                Log::error('Heatmap risk refresh failed for one barangay; skipping it.', [
                    'barangay' => $barangay,
                    'message' => $exception->getMessage(),
                ]);

                if (! Cache::has($failureCounterKey)) {
                    Cache::put($failureCounterKey, 0, now()->addDay());
                }
                Cache::increment($failureCounterKey);

                continue;
            }
        }

        Cache::put('heatmap:last-refresh', now()->toDateTimeString(), now()->addDay());
    }

    public static function refreshFailuresToday(): int
    {
        return (int) Cache::get('metrics:heatmap-refresh:failures:'.today()->toDateString(), 0);
    }

    /**
     * Precomputes and stores barangay-level risk/yield forecasts for each of
     * the next $months calendar months, so the heat map can offer a month
     * selector without recalculating on every request. Weather is forecast
     * once per month (municipality-wide, via MonthlyWeatherRandomForest,
     * which already accepts an arbitrary target month) and then blended per
     * barangay with that barangay's own rice production history and
     * exposure profile, mirroring refresh()'s current-month computation.
     */
    public function refreshForecasts(int $months = 6): void
    {
        $this->ensureDefaultAreas();

        $barangays = $this->barangays();
        $productionByBarangay = $this->productionByBarangay($barangays);

        for ($offset = 1; $offset <= $months; $offset++) {
            $targetMonth = CarbonImmutable::now()->addMonthsNoOverflow($offset)->startOfMonth();

            try {
                $weather = $this->weatherInputs($targetMonth);
            } catch (\Throwable $exception) {
                Log::error('Heatmap forecast weather lookup failed for a target month; skipping it.', [
                    'target_month' => $targetMonth->format('Y-m'),
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            foreach ($barangays as $barangay) {
                try {
                    $updates = $this->computeUpdatesForBarangay(
                        $barangay,
                        $weather,
                        $productionByBarangay->get($barangay, collect()),
                        $targetMonth,
                    );

                    if ($updates === null) {
                        continue;
                    }

                    HeatmapAreaForecast::query()->updateOrCreate(
                        ['barangay' => $barangay, 'forecast_month' => $targetMonth->toDateString()],
                        [...$updates['fields'], 'computed_at' => now()],
                    );
                } catch (\Throwable $exception) {
                    Log::error('Heatmap forecast refresh failed for one barangay; skipping it.', [
                        'barangay' => $barangay,
                        'target_month' => $targetMonth->format('Y-m'),
                        'message' => $exception->getMessage(),
                    ]);

                    continue;
                }
            }
        }
    }

    /**
     * @return array{fields: array<string, mixed>, risk_breakdown?: array}|null
     */
    private function computeUpdatesForBarangay(
        string $barangay,
        array $weather,
        Collection $productionRecords,
        CarbonImmutable $targetMonth,
    ): ?array {
        $production = $productionRecords->first();
        $averageYield = $productionRecords->whereNotNull('yield_per_hectare')->avg('yield_per_hectare');
        $averageYield = $averageYield !== null ? round((float) $averageYield, 2) : null;

        if (! $weather['has_weather'] && ! $production && $averageYield === null) {
            return null;
        }

        $storedYield = $production?->yield_per_hectare !== null
            ? (float) $production->yield_per_hectare
            : $averageYield;

        $rainfall = $weather['rainfall'];
        $temperature = $weather['temperature'];
        $season = $weather['season'] ?? $production?->season ?? ClimateRecord::SEASON_WET;
        $farmType = $this->farmTypeFor($production?->irrigation_type);
        $modelYield = $this->predictedYieldForBarangay($weather, $production, $farmType, $season, $targetMonth);
        $yield = $modelYield ?? $storedYield;
        $yieldSource = $modelYield !== null
            ? 'trained rice yield model estimate'
            : ($storedYield !== null ? 'stored barangay production record' : 'no yield estimate available');

        $decision = $this->decisionSupport->evaluate([
            'barangay' => $barangay,
            'farm_type' => $farmType,
            'rainfall' => $rainfall,
            'temperature' => $temperature,
            'wind_speed' => $weather['wind_speed'],
            'humidity' => $weather['humidity'],
            'season' => $season,
            'predicted_yield' => $yield,
        ]);

        $fields = [
            'risk_level' => $decision['risk']['level'],
            'risk_type' => $this->riskTypeFromDecision($decision, $temperature),
            'risk_score' => $decision['risk']['score'],
            'risk_components' => $decision['risk']['components'] ?? null,
            'predicted_yield' => $yield,
            'rainfall_status' => $this->rainfallStatus($rainfall),
            'planting_advisory' => $decision['planting']['recommendation'],
            'irrigation_recommendation' => $decision['irrigation']['recommendation'],
            'description' => $weather['description'].' Yield source: '.$yieldSource.'. Barangay exposure: '.$this->riskScorer->exposureLabel($barangay).'. Estimated recommendation confidence (heuristic, not statistical): '.$decision['confidence']['label'].'. Decision support score: '.$decision['score']['value'].' ('.$decision['score']['interpretation'].').',
        ];

        return isset($decision['risk_breakdown'])
            ? ['fields' => $fields, 'risk_breakdown' => $decision['risk_breakdown']]
            : ['fields' => $fields];
    }

    private function productionByBarangay(Collection $barangays): Collection
    {
        return RiceProduction::query()
            ->select([
                'id', 'barangay', 'season', 'irrigation_type', 'yield_per_hectare',
                'area_hectares', 'total_production', 'year',
            ])
            ->whereIn('barangay', $barangays)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get()
            ->groupBy('barangay');
    }

    /**
     * The labeled risk-score breakdown computed for a barangay during the
     * most recent refresh(), or null if it hasn't been computed yet. Reads
     * from cache rather than recomputing, since recomputation would require
     * re-running the same weather/ML calls refresh() already performs.
     */
    public function riskBreakdownFor(string $barangay): ?array
    {
        return Cache::get(self::riskBreakdownCacheKey($barangay));
    }

    private static function riskBreakdownCacheKey(string $barangay): string
    {
        return 'heatmap:risk-breakdown:'.$barangay;
    }

    private function barangays(): Collection
    {
        return HeatmapArea::query()
            ->pluck('barangay')
            ->merge(RiceProduction::query()->pluck('barangay'))
            ->merge(array_keys(self::DEFAULT_AREAS))
            ->filter()
            ->unique()
            ->values();
    }

    private function ensureDefaultAreas(): void
    {
        $areas = HeatmapArea::query()
            ->whereIn('barangay', [...array_keys(self::DEFAULT_AREAS), 'Poblacion'])
            ->orderByRaw('latitude is null')
            ->orderBy('id')
            ->get()
            ->unique('barangay')
            ->keyBy('barangay');
        $legacyPoblacion = $areas->get('Poblacion');

        if ($legacyPoblacion && ! $areas->has('Barangay 1 (Pob.)')) {
            $legacyPoblacion->forceFill(['barangay' => 'Barangay 1 (Pob.)'])->save();
            $areas->put('Barangay 1 (Pob.)', $legacyPoblacion);
        }

        foreach (self::DEFAULT_AREAS as $barangay => [$latitude, $longitude]) {
            $area = $areas->get($barangay);

            if ($area) {
                $area->fill([
                    'latitude' => $area->latitude ?? $latitude,
                    'longitude' => $area->longitude ?? $longitude,
                    'risk_level' => $area->risk_level ?: 'Moderate',
                    'risk_type' => $area->risk_type ?: 'Drought',
                    'risk_score' => $area->risk_score ?? 0.50,
                    'rainfall_status' => $area->rainfall_status ?: 'Pending climate update',
                    'planting_advisory' => $area->planting_advisory ?: 'Review latest climate and rice production data before planting.',
                    'irrigation_recommendation' => $area->irrigation_recommendation ?: 'Monitor field water availability and update after the next rainfall record.',
                    'description' => $area->description ?: 'Default Lian barangay map point.',
                ]);

                if ($area->isDirty()) {
                    $area->save();
                }

                continue;
            }

            HeatmapArea::query()->create([
                'barangay' => $barangay,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'risk_level' => 'Moderate',
                'risk_type' => 'Drought',
                'risk_score' => 0.50,
                'predicted_yield' => null,
                'rainfall_status' => 'Pending climate update',
                'planting_advisory' => 'Review latest climate and rice production data before planting.',
                'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.',
                'description' => 'Default Lian barangay map point.',
            ]);
        }
    }

    private function defaultLatitude(string $barangay): ?float
    {
        return self::DEFAULT_AREAS[$barangay][0] ?? null;
    }

    private function defaultLongitude(string $barangay): ?float
    {
        return self::DEFAULT_AREAS[$barangay][1] ?? null;
    }

    private function weatherInputs(CarbonImmutable $targetMonth): array
    {
        $recentClimate = ClimateRecord::query()
            ->select(['record_date', 'rainfall', 'temperature', 'humidity', 'wind_speed', 'season'])
            ->latest('record_date')
            ->take(6)
            ->get();
        $latestClimate = $recentClimate->first();

        $mlForecast = $this->weatherForest->predict($targetMonth);
        // A short-range day-by-day forecast is only meaningful for the
        // immediate next month; for months further out it would be blended
        // in as if it were still current, so it's skipped entirely.
        $isImmediateMonth = $targetMonth->isSameMonth(CarbonImmutable::now()->addMonthNoOverflow());
        $apiForecast = $isImmediateMonth ? $this->weatherApi->forecast() : null;

        $rainfallValues = [];
        $temperatureValues = [];
        $sources = [];

        if (($mlForecast['ready'] ?? false) && isset($mlForecast['predictions'])) {
            $rainfallValues[] = ['value' => (float) ($mlForecast['predictions']['rainfall'] ?? 0), 'weight' => 0.65];
            $temperatureValues[] = ['value' => (float) ($mlForecast['predictions']['temperature'] ?? 0), 'weight' => 0.65];
            $sources[] = 'Random Forest ML forecast for '.$targetMonth->format('F Y');
        }

        if ($apiForecast !== null) {
            $rainfallValues[] = ['value' => (float) $apiForecast['monthly_rainfall_estimate_mm'], 'weight' => 0.35];
            $temperatureValues[] = ['value' => (float) $apiForecast['temperature_c'], 'weight' => 0.35];
            $sources[] = $apiForecast['source'].' '.$apiForecast['forecast_days'].'-day forecast converted to monthly rainfall estimate';
        }

        if ($rainfallValues === [] && $latestClimate?->rainfall !== null) {
            $rainfallValues[] = ['value' => (float) $latestClimate->rainfall, 'weight' => 1];
            $sources[] = 'latest stored climate record';
        }

        if ($temperatureValues === [] && $latestClimate?->temperature !== null) {
            $temperatureValues[] = ['value' => (float) $latestClimate->temperature, 'weight' => 1];
        }

        if ($rainfallValues === [] && $recentClimate->isNotEmpty()) {
            $rainfallValues[] = ['value' => (float) ($recentClimate->avg('rainfall') ?: 0), 'weight' => 1];
            $sources[] = 'recent stored climate average';
        }

        if ($temperatureValues === [] && $recentClimate->isNotEmpty()) {
            $temperatureValues[] = ['value' => (float) ($recentClimate->avg('temperature') ?: 0), 'weight' => 1];
        }

        return [
            'has_weather' => $rainfallValues !== [] || $temperatureValues !== [],
            'rainfall' => $this->weightedAverage($rainfallValues),
            'temperature' => $this->weightedAverage($temperatureValues),
            'humidity' => $apiForecast['humidity_percent'] ?? (float) ($latestClimate?->humidity ?? $recentClimate->avg('humidity') ?: 0),
            'wind_speed' => $apiForecast['wind_speed_kmh'] ?? (float) ($latestClimate?->wind_speed ?? $recentClimate->avg('wind_speed') ?: 0),
            'season' => $mlForecast['predictions']['season'] ?? $latestClimate?->season ?? null,
            'previous_rainfall' => (float) ($latestClimate?->rainfall ?? $recentClimate->avg('rainfall') ?: 0),
            'previous_temp' => (float) ($latestClimate?->temperature ?? $recentClimate->avg('temperature') ?: 0),
            'rainfall_6m' => (float) ($recentClimate->avg('rainfall') ?: ($latestClimate?->rainfall ?? 0)),
            'temp_3m' => (float) ($recentClimate->take(3)->avg('temperature') ?: ($latestClimate?->temperature ?? 0)),
            'temp_6m' => (float) ($recentClimate->avg('temperature') ?: ($latestClimate?->temperature ?? 0)),
            'seasonal_rainfall' => (float) max($recentClimate->sum('rainfall') ?: 0, ($mlForecast['predictions']['rainfall'] ?? 0) * 6),
            'seasonal_temp' => (float) ($recentClimate->avg('temperature') ?: ($mlForecast['predictions']['temperature'] ?? $latestClimate?->temperature ?? 0)),
            'description' => 'Auto-updated from '.(implode(', ', array_unique($sources)) ?: 'available weather inputs').' and barangay rice production data.',
        ];
    }

    /**
     * Delegates to PredictionEngine::requestYieldFromModel() — the same model
     * call and rounding used by the AI Assistant and System Prediction page —
     * so a barangay's heatmap yield never diverges from what those features
     * would compute for the same inputs. Falls back to the barangay's stored
     * production average (handled by the caller) if the model can't be reached.
     */
    private function predictedYieldForBarangay(array $weather, ?RiceProduction $production, string $farmType, string $season, CarbonImmutable $targetMonth): ?float
    {
        $area = (float) ($production?->area_hectares ?? 1);

        if ($area <= 0) {
            $area = 1;
        }

        $modelInput = [
            'rainfall' => (float) $weather['rainfall'],
            'temp_avg' => (float) $weather['temperature'],
            'temp_range' => max(1, abs((float) $weather['temperature'] - (float) $weather['previous_temp'])),
            'area' => $area,
            'previous_rainfall' => (float) $weather['previous_rainfall'],
            'previous_temp' => (float) $weather['previous_temp'],
            'previous_humidity' => (float) ($weather['humidity'] ?? 0),
            'previous_wind_speed' => (float) ($weather['wind_speed'] ?? 0),
            'rainfall_6m' => (float) $weather['rainfall_6m'],
            'temp_3m' => (float) $weather['temp_3m'],
            'temp_6m' => (float) $weather['temp_6m'],
            'seasonal_rainfall' => (float) $weather['seasonal_rainfall'],
            'seasonal_temp' => (float) $weather['seasonal_temp'],
            'humidity' => (float) ($weather['humidity'] ?? 0),
            'wind_speed' => (float) ($weather['wind_speed'] ?? 0),
            'season' => in_array($season, [ClimateRecord::SEASON_WET, ClimateRecord::SEASON_DRY], true) ? $season : ClimateRecord::SEASON_WET,
            'farm_type' => $farmType,
            'month_num' => (int) $targetMonth->format('n'),
        ];

        $memoKey = md5(json_encode($modelInput));

        if (array_key_exists($memoKey, $this->yieldPredictionMemo)) {
            return $this->yieldPredictionMemo[$memoKey];
        }

        [$yield] = $this->predictionEngine->requestYieldFromModel($modelInput);

        return $this->yieldPredictionMemo[$memoKey] = $yield['predicted_yield'] ?? null;
    }

    private function weightedAverage(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($values as $value) {
            $weighted += $value['value'] * $value['weight'];
            $totalWeight += $value['weight'];
        }

        return round($weighted / max($totalWeight, 0.0001), 2);
    }

    /**
     * Cosmetic display chip only — intentionally separate from the shared
     * risk formula in AgriculturalRiskScorer, not used in any risk scoring.
     */
    private function rainfallStatus(float $rainfall): string
    {
        return $rainfall < 100 ? 'Low rainfall' : ($rainfall > 250 ? 'High rainfall' : 'Adequate rainfall');
    }

    private function farmTypeFor(?string $irrigationType): string
    {
        return str_contains(strtolower((string) $irrigationType), 'irrig') ? 'Irrigated' : 'Rainfed';
    }

    private function riskTypeFromDecision(array $decision, float $temperature): string
    {
        $warning = strtolower(implode(' ', $decision['weather']['warnings'] ?? []));

        if (str_contains($warning, 'flood') || str_contains($warning, 'heavy rainfall')) {
            return 'Flood';
        }

        if (str_contains($warning, 'drought')) {
            return 'Drought';
        }

        if (str_contains($warning, 'wind')) {
            return 'Typhoon';
        }

        return $temperature >= 32 ? 'Heat' : 'Drought';
    }
}
