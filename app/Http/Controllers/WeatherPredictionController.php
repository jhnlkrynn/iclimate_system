<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Services\DecisionSupportService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\PythonService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;
use Throwable;

class WeatherPredictionController extends Controller
{
    public function index(Request $request, MonthlyWeatherRandomForest $forest, DecisionSupportService $decisionSupport, PythonService $python): View
    {
        $validated = $request->validate([
            'target_month' => ['nullable', 'date_format:Y-m'],
            'target_date' => ['nullable', 'date'],
        ]);

        $targetDate = isset($validated['target_date'])
            ? CarbonImmutable::parse($validated['target_date'])
            : (isset($validated['target_month'])
                ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')
                : CarbonImmutable::now()->addMonthNoOverflow());

        $targetMonth = isset($validated['target_month']) && ! isset($validated['target_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')->startOfMonth()
            : $targetDate->startOfMonth();

        $weatherResult = $forest->predict($targetMonth);
        $modelInput = $this->modelInputFromForecast($weatherResult, 'Rainfed', $targetDate);
        [$mlResult, $error] = $this->predictYield($modelInput, $weatherResult, $decisionSupport, $python);

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $modelInput,
            'mlResult' => $mlResult,
            'error' => $error,
        ]);
    }

    public function predict(Request $request, MonthlyWeatherRandomForest $forest, DecisionSupportService $decisionSupport, PythonService $python): View
    {
        $validated = $request->validate([
            'prediction_date' => ['required', 'date'],
            'farm_type' => ['nullable', 'in:Rainfed,Irrigated'],
        ]);

        $targetDate = isset($validated['prediction_date'])
            ? CarbonImmutable::parse($validated['prediction_date'])
            : CarbonImmutable::now()->addMonthNoOverflow();
        $targetMonth = $targetDate->startOfMonth();
        $weatherResult = $forest->predict($targetMonth);
        $modelInput = $this->modelInputFromForecast($weatherResult, $validated['farm_type'] ?? 'Rainfed', $targetDate);
        [$mlResult, $error] = $this->predictYield($modelInput, $weatherResult, $decisionSupport, $python);

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $modelInput,
            'mlResult' => $mlResult,
            'error' => $error,
        ]);
    }

    private function predictYield(array $modelInput, array $weatherResult, DecisionSupportService $decisionSupport, PythonService $python): array
    {
        $apiError = null;

        try {
            $apiResult = $python->farmingAssistant([
                'features' => $modelInput,
            ]);
            $mlResult = $this->normalizeApiResult($apiResult);
        } catch (Throwable $exception) {
            $apiError = $exception->getMessage();
            $mlResult = $this->predictYieldWithLocalScript($modelInput);
        }

        if (! is_array($mlResult) || ! array_key_exists('predicted_yield', $mlResult)) {
            return [null, $apiError ?: 'The trained rice-yield model returned an invalid response.'];
        }

        $decision = $decisionSupport->evaluate([
            'farm_type' => $modelInput['farm_type'],
            'rainfall' => $modelInput['rainfall'],
            'wind_speed' => $modelInput['wind_speed'] ?? ($weatherResult['predictions']['wind_speed'] ?? 0),
            'humidity' => $modelInput['humidity'] ?? ($weatherResult['predictions']['humidity'] ?? 0),
            'season' => $modelInput['season'],
            'predicted_yield' => $mlResult['predicted_yield'],
        ]);

        $sourceNote = $apiError
            ? 'Local trained Random Forest script fallback'
            : 'Farming AI API trained Random Forest model';

        return [[
            ...$mlResult,
            'model_input' => $modelInput,
            'planting_advisory' => $decision['planting']['recommendation'],
            'irrigation_recommendation' => $decision['irrigation']['recommendation'],
            'notifications' => $decision['notifications'],
            'decision_support' => $decision,
            'source' => $sourceNote,
            'api_error' => $apiError,
        ], null];
    }

    private function normalizeApiResult(array $apiResult): array
    {
        $yield = data_get($apiResult, 'rice_yield_prediction.predicted_yield');

        return [
            'predicted_yield' => $yield !== null ? round((float) $yield, 2) : null,
            'unit' => data_get($apiResult, 'rice_yield_prediction.unit', 'tons/hectare'),
            'uncertainty' => data_get($apiResult, 'rice_yield_prediction.uncertainty'),
            'weather_prediction' => $apiResult['weather_prediction'] ?? null,
            'api_confidence' => $apiResult['confidence_score'] ?? data_get($apiResult, 'weather_prediction.confidence'),
            'api_explanation' => $apiResult['explanation'] ?? data_get($apiResult, 'rice_yield_prediction.explanation'),
            'api_response_time_ms' => $apiResult['response_time_ms'] ?? null,
            'warnings' => $apiResult['warnings'] ?? [],
        ];
    }

    private function predictYieldWithLocalScript(array $modelInput): ?array
    {
        $process = Process::env($this->pythonEnvironment())->run([
            $this->pythonBinary(),
            base_path('python_scripts/predict.py'),
            json_encode($modelInput),
        ]);

        if (! $process->successful()) {
            return null;
        }

        $result = json_decode($process->output(), true);

        return is_array($result) ? $result : null;
    }

    private function modelInputFromForecast(array $weatherResult, string $farmType, CarbonImmutable $targetDate): array
    {
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
        $averageArea = RiceProduction::query()->avg('area_hectares');

        $rainfall = (float) ($predictions['rainfall'] ?? $averageRainfall ?? $latestClimate?->rainfall ?? 0);
        $temperature = (float) ($predictions['temperature'] ?? $averageTemperature ?? $latestClimate?->temperature ?? 0);
        $humidity = (float) ($predictions['humidity'] ?? $latestClimate?->humidity ?? 0);
        $windSpeed = (float) ($predictions['wind_speed'] ?? $latestClimate?->wind_speed ?? 0);
        $forecastSeason = $predictions['season'] ?? 'Wet';
        $season = in_array($forecastSeason, ['Wet', 'Dry'], true)
            ? $forecastSeason
            : ($latestClimate?->season ?? 'Wet');

        return [
            'rainfall' => round($rainfall, 2),
            'temp_avg' => round($temperature, 2),
            'temp_range' => round(max(0, $temperatureRange ?? 0), 2),
            'area' => round((float) ($averageArea ?? 0), 2),
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
}
