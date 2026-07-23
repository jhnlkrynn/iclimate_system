<?php

namespace App\Services;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

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

    private const BARANGAY_EXPOSURE = [
        'Bagong Pook' => 0.55,
        'Balibago' => 0.70,
        'Barangay 1 (Pob.)' => 0.50,
        'Barangay 2 (Pob.)' => 0.50,
        'Barangay 3 (Pob.)' => 0.55,
        'Barangay 4 (Pob.)' => 0.55,
        'Barangay 5 (Pob.)' => 0.50,
        'Binubusan' => 0.45,
        'Bungahan' => 0.65,
        'Cumba' => 0.80,
        'Humayingan' => 0.75,
        'Kapito' => 0.70,
        'Lumaniag' => 0.55,
        'Luyahan' => 0.75,
        'Malaruhatan' => 0.80,
        'Matabungkay' => 0.90,
        'Prenza' => 0.65,
        'Puting-Kahoy' => 0.75,
        'San Diego' => 0.85,
    ];

    public function __construct(
        private readonly MonthlyWeatherRandomForest $weatherForest,
        private readonly WeatherApiService $weatherApi,
        private readonly DecisionSupportService $decisionSupport,
    ) {}

    public function refresh(): void
    {
        $this->ensureDefaultAreas();

        $weather = $this->weatherInputs();

        $barangays = $this->barangays();

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
                'wind_speed' => $weather['wind_speed'],
                'humidity' => $weather['humidity'],
                'season' => $season,
                'predicted_yield' => $yield,
            ]);
            $riskScore = $this->agriculturalRiskScore($barangay, $rainfall, $temperature, $yield, $farmType, $decision);

            $updates = [
                'risk_level' => $this->riskLevelFromScore($riskScore),
                'risk_type' => $this->riskTypeFromDecision($decision, $temperature),
                'risk_score' => $riskScore,
                'predicted_yield' => $yield,
                'rainfall_status' => $this->rainfallStatus($rainfall),
                'planting_advisory' => $decision['planting']['recommendation'],
                'irrigation_recommendation' => $decision['irrigation']['recommendation'],
                'description' => $weather['description'].' Yield source: '.$yieldSource.'. Barangay exposure: '.$this->exposureLabel($barangay).'. Recommendation confidence: '.$decision['confidence']['label'].'. Decision support score: '.$decision['score']['value'].' ('.$decision['score']['interpretation'].').',
            ];

            if ($this->defaultLatitude($barangay) !== null && $this->defaultLongitude($barangay) !== null) {
                $updates['latitude'] = $this->defaultLatitude($barangay);
                $updates['longitude'] = $this->defaultLongitude($barangay);
            }

            HeatmapArea::query()->updateOrCreate(
                ['barangay' => $barangay],
                $updates
            );
        }
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
        $legacyPoblacion = HeatmapArea::query()->where('barangay', 'Poblacion')->first();

        if ($legacyPoblacion && ! HeatmapArea::query()->where('barangay', 'Barangay 1 (Pob.)')->exists()) {
            $legacyPoblacion->forceFill(['barangay' => 'Barangay 1 (Pob.)'])->save();
        }

        foreach (self::DEFAULT_AREAS as $barangay => [$latitude, $longitude]) {
            $area = HeatmapArea::query()
                ->where('barangay', $barangay)
                ->orderByRaw('latitude is null')
                ->orderBy('id')
                ->first();

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
                ])->save();

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

    private function predictedYieldForBarangay(array $weather, ?RiceProduction $production, string $farmType, string $season): ?float
    {
        $scriptPath = base_path('python_scripts/predict.py');
        $modelPath = storage_path('models/rice_yield_model_final.pkl');

        if (! file_exists($scriptPath) || ! file_exists($modelPath)) {
            return null;
        }

        $area = (float) ($production?->area_hectares ?? 1);

        if ($area <= 0) {
            $area = 1;
        }

        $payload = [
            'rainfall' => (float) $weather['rainfall'],
            'temp_avg' => (float) $weather['temperature'],
            'temp_range' => max(1, abs((float) $weather['temperature'] - (float) $weather['previous_temp'])),
            'area' => $area,
            'previous_rainfall' => (float) $weather['previous_rainfall'],
            'previous_temp' => (float) $weather['previous_temp'],
            'rainfall_6m' => (float) $weather['rainfall_6m'],
            'temp_3m' => (float) $weather['temp_3m'],
            'temp_6m' => (float) $weather['temp_6m'],
            'seasonal_rainfall' => (float) $weather['seasonal_rainfall'],
            'seasonal_temp' => (float) $weather['seasonal_temp'],
            'season' => in_array($season, [ClimateRecord::SEASON_WET, ClimateRecord::SEASON_DRY], true) ? $season : ClimateRecord::SEASON_WET,
            'farm_type' => $farmType,
        ];

        $memoKey = md5(json_encode($payload));

        if (array_key_exists($memoKey, $this->yieldPredictionMemo)) {
            return $this->yieldPredictionMemo[$memoKey];
        }

        $process = Process::timeout(20)
            ->env($this->pythonEnvironment())
            ->run([
                $this->pythonBinary(),
                $scriptPath,
                json_encode($payload),
            ]);

        if (! $process->successful()) {
            $this->yieldPredictionMemo[$memoKey] = null;

            return null;
        }

        $result = json_decode($process->output(), true);

        if (! is_array($result) || ! isset($result['predicted_yield']) || ! is_numeric($result['predicted_yield'])) {
            $this->yieldPredictionMemo[$memoKey] = null;

            return null;
        }

        return $this->yieldPredictionMemo[$memoKey] = round((float) $result['predicted_yield'], 2);
    }

    private function pythonBinary(): string
    {
        $configured = env('PYTHON_BINARY');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $windowsPython = 'C:\\Users\\Luke\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe';

        return file_exists($windowsPython) ? $windowsPython : 'python';
    }

    private function pythonEnvironment(): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\WINDOWS';
        $path = getenv('PATH') ?: getenv('Path') ?: '';

        return [
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => getenv('WINDIR') ?: $systemRoot,
            'Path' => $path,
            'PATH' => $path,
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
        ];
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

    private function mapRiskScore(array $decision): float
    {
        $score = round(1 - ((float) $decision['score']['value'] / 100), 2);

        return match ($decision['risk']['level']) {
            'High', 'Severe' => max(0.75, $score),
            'Moderate' => max(0.50, min(0.74, $score)),
            default => min(0.49, $score),
        };
    }

    private function agriculturalRiskScore(string $barangay, float $rainfall, float $temperature, ?float $yield, string $farmType, array $decision): float
    {
        $rainfallRisk = match (true) {
            $rainfall <= 0 => 0.60,
            $rainfall < 80 => 0.95,
            $rainfall < 120 => 0.75,
            $rainfall <= 280 => 0.22,
            $rainfall <= 350 => 0.62,
            default => 0.92,
        };

        $yieldRisk = match (true) {
            $yield === null => 0.55,
            $yield < 2 => 0.95,
            $yield < 3 => 0.78,
            $yield < 4 => 0.48,
            default => 0.18,
        };

        $temperatureRisk = match (true) {
            $temperature >= 35 => 0.86,
            $temperature >= 32 => 0.64,
            $temperature < 22 && $temperature > 0 => 0.52,
            default => 0.24,
        };

        $exposure = self::BARANGAY_EXPOSURE[$barangay] ?? 0.55;
        $farmExposure = strtolower($farmType) === 'rainfed' ? 0.10 : 0.03;
        $stressPenalty = min(0.12, count($decision['stress_factors'] ?? []) * 0.025);

        $score = ($rainfallRisk * 0.34)
            + ($yieldRisk * 0.27)
            + ($temperatureRisk * 0.14)
            + ($exposure * 0.18)
            + $farmExposure
            + $stressPenalty;

        return round(max(0.05, min(0.98, $score)), 2);
    }

    private function riskLevelFromScore(float $score): string
    {
        return match (true) {
            $score >= 0.85 => 'Severe',
            $score >= 0.68 => 'High',
            $score >= 0.45 => 'Moderate',
            default => 'Low',
        };
    }

    private function exposureLabel(string $barangay): string
    {
        $exposure = self::BARANGAY_EXPOSURE[$barangay] ?? 0.55;

        return match (true) {
            $exposure >= 0.80 => 'high',
            $exposure >= 0.60 => 'moderate',
            default => 'low',
        };
    }
}
