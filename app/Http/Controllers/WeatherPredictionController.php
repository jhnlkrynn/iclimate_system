<?php

namespace App\Http\Controllers;

use App\Services\DecisionSupportService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    public function index(Request $request, MonthlyWeatherRandomForest $forest): View
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

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $forest->predict($targetMonth),
            'mlResult' => null,
        ]);
    }

    public function predict(Request $request, MonthlyWeatherRandomForest $forest, DecisionSupportService $decisionSupport): View
    {
        $validated = $request->validate([
            'prediction_date' => ['required', 'date'],
            'farm_type' => ['nullable', 'in:Rainfed,Irrigated'],
        ]);

        $scriptPath = base_path('python_scripts/predict.py');
        $targetDate = isset($validated['prediction_date'])
            ? CarbonImmutable::parse($validated['prediction_date'])
            : CarbonImmutable::now()->addMonthNoOverflow();
        $targetMonth = $targetDate->startOfMonth();
        $weatherResult = $forest->predict($targetMonth);
        $modelInput = $this->modelInputFromForecast($weatherResult, $validated['farm_type'] ?? 'Rainfed');
        $jsonInput = json_encode($modelInput);

        $process = Process::env($this->pythonEnvironment())->run([
            $this->pythonBinary(),
            $scriptPath,
            $jsonInput,
        ]);

        if (! $process->successful()) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'targetDate' => $targetDate,
                'result' => $weatherResult,
                'mlResult' => null,
                'error' => trim($process->errorOutput()) ?: 'The Python prediction script failed.',
            ]);
        }

        $mlResult = json_decode($process->output(), true);

        if (! is_array($mlResult) || ! array_key_exists('predicted_yield', $mlResult)) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'targetDate' => $targetDate,
                'result' => $weatherResult,
                'mlResult' => null,
                'error' => 'The Python prediction script returned an invalid response: '.$process->output(),
            ]);
        }

        $decision = $decisionSupport->evaluate([
            'farm_type' => $modelInput['farm_type'],
            'rainfall' => $modelInput['rainfall'],
            'wind_speed' => $weatherResult['predictions']['wind_speed'] ?? 0,
            'humidity' => $weatherResult['predictions']['humidity'] ?? 0,
            'season' => $modelInput['season'],
            'predicted_yield' => $mlResult['predicted_yield'],
        ]);

        $mlResult = [
            ...$mlResult,
            'model_input' => $modelInput,
            'planting_advisory' => $decision['planting']['recommendation'],
            'irrigation_recommendation' => $decision['irrigation']['recommendation'],
            'notifications' => $decision['notifications'],
            'decision_support' => $decision,
        ];

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'mlResult' => $mlResult,
            'error' => null,
        ]);
    }

    private function modelInputFromForecast(array $weatherResult, string $farmType): array
    {
        $predictions = $weatherResult['predictions'] ?? [];
        $rainfall = (float) ($predictions['rainfall'] ?? 180);
        $temperature = (float) ($predictions['temperature'] ?? 29);
        $forecastSeason = $predictions['season'] ?? 'Wet';
        $season = in_array($forecastSeason, ['Wet', 'Dry'], true)
            ? $forecastSeason
            : 'Wet';

        return [
            'rainfall' => round($rainfall, 2),
            'temp_avg' => round($temperature, 2),
            'temp_range' => 8,
            'area' => 120,
            'previous_rainfall' => round(max(0, $rainfall * 0.9), 2),
            'previous_temp' => round($temperature, 2),
            'rainfall_6m' => round(max(0, $rainfall), 2),
            'temp_3m' => round($temperature, 2),
            'temp_6m' => round($temperature, 2),
            'seasonal_rainfall' => round(max(0, $rainfall * 6), 2),
            'seasonal_temp' => round($temperature, 2),
            'season' => $season,
            'farm_type' => $farmType,
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
