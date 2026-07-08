<?php

namespace App\Http\Controllers;

use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\DecisionSupportService;
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
        ]);

        $targetMonth = isset($validated['target_month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')->startOfMonth()
            : CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'result' => $forest->predict($targetMonth),
            'mlResult' => null,
        ]);
    }

    public function predict(Request $request, MonthlyWeatherRandomForest $forest, DecisionSupportService $decisionSupport): View
    {
        $validated = $request->validate([
            'rainfall' => ['required', 'numeric'],
            'temp_avg' => ['required', 'numeric'],
            'temp_range' => ['required', 'numeric'],
            'area' => ['required', 'numeric'],
            'previous_rainfall' => ['required', 'numeric'],
            'previous_temp' => ['required', 'numeric'],
            'rainfall_6m' => ['required', 'numeric'],
            'temp_3m' => ['required', 'numeric'],
            'temp_6m' => ['required', 'numeric'],
            'seasonal_rainfall' => ['required', 'numeric'],
            'seasonal_temp' => ['required', 'numeric'],
            'season' => ['required', 'in:Wet,Dry'],
            'farm_type' => ['required', 'in:Rainfed,Irrigated'],
        ]);

        $jsonInput = json_encode($validated);

        $scriptPath = base_path('python_scripts/predict.py');
        $targetMonth = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        $weatherResult = $forest->predict($targetMonth);

        $process = Process::env($this->pythonEnvironment())->run([
            $this->pythonBinary(),
            $scriptPath,
            $jsonInput,
        ]);

        if (! $process->successful()) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'result' => $weatherResult,
                'mlResult' => null,
                'error' => trim($process->errorOutput()) ?: 'The Python prediction script failed.',
            ]);
        }

        $mlResult = json_decode($process->output(), true);

        if (! is_array($mlResult) || ! array_key_exists('predicted_yield', $mlResult)) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'result' => $weatherResult,
                'mlResult' => null,
                'error' => 'The Python prediction script returned an invalid response: '.$process->output(),
            ]);
        }

        $decision = $decisionSupport->evaluate([
            'farm_type' => $validated['farm_type'],
            'rainfall' => $validated['rainfall'],
            'wind_speed' => $weatherResult['predictions']['wind_speed'] ?? 0,
            'humidity' => $weatherResult['predictions']['humidity'] ?? 0,
            'season' => $validated['season'],
            'predicted_yield' => $mlResult['predicted_yield'],
        ]);

        $mlResult = [
            ...$mlResult,
            'planting_advisory' => $decision['planting']['recommendation'],
            'irrigation_recommendation' => $decision['irrigation']['recommendation'],
            'notifications' => $decision['notifications'],
            'decision_support' => $decision,
        ];

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'result' => $weatherResult,
            'mlResult' => $mlResult,
            'error' => null,
        ]);
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
