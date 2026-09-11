<?php

namespace App\Services;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Services\Geo\LianBarangayBoundaryService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionEngine;
use App\Services\Risk\AgriculturalRiskScorer;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HeatmapRiskService
{
    /** @var array<string, float|null> */
    private array $yieldPredictionMemo = [];

<<<<<<< Updated upstream
=======
    public const PLACEHOLDER_NAMES = [
        'Barangay 1 (Pob.)',
        'Barangay 2 (Pob.)',
        'Barangay 3 (Pob.)',
        'Barangay 4 (Pob.)',
    ];

>>>>>>> Stashed changes
    public function __construct(
        private readonly MonthlyWeatherRandomForest $weatherForest,
        private readonly WeatherApiService $weatherApi,
        private readonly DecisionSupportService $decisionSupport,
        private readonly AgriculturalRiskScorer $riskScorer,
        private readonly PredictionEngine $predictionEngine,
        private readonly LianBarangayBoundaryService $boundaries,
    ) {}

    public function refresh(): void
    {
<<<<<<< Updated upstream
        $this->ensureDefaultAreas();
        $this->removeDuplicateAreas();
=======
        $this->removePlaceholderAreas();
>>>>>>> Stashed changes

        $weather = $this->weatherInputs();

        $coordinates = $this->boundaries->centroids();
        $barangays = $this->barangays($coordinates);

        foreach ($barangays as $barangay) {
            $production = RiceProduction::query()
                ->where('barangay', $barangay)
                ->latest('year')
                ->latest('id')
                ->first();

            $averageYield = $this->averageYieldForBarangay($barangay);

            if (! $weather['has_weather'] && ! $production && $averageYield === null) {
                continue;
            }

            $storedYield = $production?->yield_per_hectare !== null
                ? (float) $production->yield_per_hectare
                : $averageYield;

            $rainfall = $weather['rainfall'];
            $temperature = $weather['temperature'];
            $season = $weather['season'] ?? $production?->season ?? ClimateRecord::SEASON_WET;
            $farmType = $this->farmTypeFor($production?->irrigation_type);
            $modelYield = $this->predictedYieldForBarangay($weather, $production, $farmType, $season);
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

            $updates = [
                'risk_level' => $decision['risk']['level'],
                'risk_type' => $this->riskTypeFromDecision($decision, $temperature),
                'risk_score' => $decision['risk']['score'],
                'predicted_yield' => $yield,
                'rainfall_status' => $this->rainfallStatus($rainfall),
                'planting_advisory' => $decision['planting']['recommendation'],
                'irrigation_recommendation' => $decision['irrigation']['recommendation'],
                'description' => $weather['description'].' Yield source: '.$yieldSource.'. Barangay exposure: '.$this->riskScorer->exposureLabel($barangay).'. Recommendation confidence: '.$decision['confidence']['label'].'. Decision support score: '.$decision['score']['value'].' ('.$decision['score']['interpretation'].').',
            ];

            if (isset($coordinates[$barangay])) {
                $updates['latitude'] = $coordinates[$barangay][0];
                $updates['longitude'] = $coordinates[$barangay][1];
            }

            HeatmapArea::query()->updateOrCreate(
                ['barangay' => $barangay],
                $updates
            );
        }
    }

    private function barangays(array $coordinates): Collection
    {
        return HeatmapArea::query()
            ->whereNotIn('barangay', self::PLACEHOLDER_NAMES)
            ->pluck('barangay')
<<<<<<< Updated upstream
            ->merge(RiceProduction::query()->pluck('barangay'))
            ->merge(array_keys($coordinates))
=======
            ->merge(RiceProduction::query()->whereIn('barangay', LianBarangays::all())->pluck('barangay'))
>>>>>>> Stashed changes
            ->filter()
            ->unique()
            ->values();
    }

    public function verifiedQuery(): Builder
    {
        return HeatmapArea::query()
            ->whereNotIn('barangay', self::PLACEHOLDER_NAMES)
            ->whereIn('barangay', LianBarangays::all());
    }

<<<<<<< Updated upstream
        if ($legacyPoblacion && ! HeatmapArea::query()->where('barangay', 'Barangay 1 (Pob.)')->exists()) {
            $legacyPoblacion->forceFill(['barangay' => 'Barangay 1 (Pob.)'])->save();
        }

        foreach ($this->boundaries->centroids() as $barangay => [$latitude, $longitude]) {
            $area = HeatmapArea::query()
                ->where('barangay', $barangay)
                ->orderByRaw('latitude is null')
                ->orderBy('id')
                ->first();

            if ($area) {
                $area->fill([
                    'latitude' => $area->latitude ?? $latitude,
                    'longitude' => $area->longitude ?? $longitude,
                    'risk_level' => $area->risk_level ?: 'Low',
                    'risk_type' => $area->risk_type ?: 'Insufficient data',
                    'risk_score' => $area->risk_score ?? 0,
                    'rainfall_status' => $area->rainfall_status ?: 'Insufficient data',
                    'planting_advisory' => $area->planting_advisory ?: 'Insufficient data. Add verified weather, PAGASA, climate, production, or prediction inputs before acting.',
                    'irrigation_recommendation' => $area->irrigation_recommendation ?: 'Insufficient data. Verify field water conditions before changing irrigation schedules.',
                    'description' => $area->description ?: 'Insufficient verified inputs to calculate barangay risk.',
                ])->save();

                continue;
            }

            HeatmapArea::query()->create([
                'barangay' => $barangay,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'risk_level' => 'Low',
                'risk_type' => 'Insufficient data',
                'risk_score' => 0,
                'predicted_yield' => null,
                'rainfall_status' => 'Insufficient data',
                'planting_advisory' => 'Insufficient data. Add verified weather, PAGASA, climate, production, or prediction inputs before acting.',
                'irrigation_recommendation' => 'Insufficient data. Verify field water conditions before changing irrigation schedules.',
                'description' => 'Insufficient verified inputs to calculate barangay risk.',
            ]);
        }
=======
    public function removePlaceholderAreas(): void
    {
        HeatmapArea::query()->whereIn('barangay', self::PLACEHOLDER_NAMES)->delete();
>>>>>>> Stashed changes
    }

    private function removeDuplicateAreas(): void
    {
<<<<<<< Updated upstream
        HeatmapArea::query()
            ->select('barangay')
            ->whereNotNull('barangay')
            ->groupBy('barangay')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('barangay')
            ->each(function (string $barangay): void {
                $areas = HeatmapArea::query()
                    ->where('barangay', $barangay)
                    ->orderByRaw('latitude is null')
                    ->orderByRaw('longitude is null')
                    ->orderByDesc('updated_at')
                    ->orderBy('id')
                    ->get();

                $areas->slice(1)->each->delete();
            });
=======
        $coordinate = LianBarangays::coordinateFor($barangay);

        return $coordinate[0] ?? null;
    }

    private function defaultLongitude(string $barangay): ?float
    {
        $coordinate = LianBarangays::coordinateFor($barangay);

        return $coordinate[1] ?? null;
>>>>>>> Stashed changes
    }

    private function averageYieldForBarangay(string $barangay): ?float
    {
        $average = RiceProduction::query()
            ->where('barangay', $barangay)
            ->avg('yield_per_hectare');

        return $average !== null ? round((float) $average, 2) : null;
    }

    private function weatherInputs(): array
    {
        $latestClimate = ClimateRecord::query()->latest('record_date')->first();
        $recentClimate = ClimateRecord::query()
            ->latest('record_date')
            ->take(6)
            ->get();

        $targetMonth = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        $mlForecast = $this->weatherForest->predict($targetMonth);
        $apiForecast = $this->weatherApi->forecast();

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
    private function predictedYieldForBarangay(array $weather, ?RiceProduction $production, string $farmType, string $season): ?float
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
            'month_num' => (int) CarbonImmutable::now()->addMonthNoOverflow()->format('n'),
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
