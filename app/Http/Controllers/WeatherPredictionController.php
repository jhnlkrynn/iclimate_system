<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\WeatherPredictionRequest;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\WeatherPredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    public function index(Request $request, PredictionDateValidator $dateValidator, WeatherPredictionService $weatherPrediction): View
    {
        $validated = $request->validate([
            'target_month' => ['nullable', 'date_format:Y-m'],
            'target_date' => ['nullable', 'date'],
        ]);

        $targetDate = isset($validated['target_date'])
            ? CarbonImmutable::parse($validated['target_date'])
            : (isset($validated['target_month'])
                ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')
                : PredictionDateValidator::defaultTargetDate());

        $targetMonth = isset($validated['target_month']) && ! isset($validated['target_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')->startOfMonth()
            : $targetDate->startOfMonth();

        if ($message = $dateValidator->validate($targetDate)) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'targetDate' => $targetDate,
                'result' => null,
                'defaultModelInput' => [],
                'mlResult' => null,
                'error' => $message,
            ]);
        }

        $weatherResult = $weatherPrediction->predict($targetDate, PredictionType::WEATHER->value);

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $weatherResult['model_input'],
            'mlResult' => null,
            'error' => null,
        ]);
    }

    public function predict(WeatherPredictionRequest $request, PredictionDateValidator $dateValidator, WeatherPredictionService $weatherPrediction): View
    {
        $validated = $request->validated();
        $targetDate = CarbonImmutable::parse($validated['prediction_date']);
        $targetMonth = $targetDate->startOfMonth();

        if ($message = $dateValidator->validate($targetDate)) {
            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'targetDate' => $targetDate,
                'result' => null,
                'defaultModelInput' => [],
                'mlResult' => null,
                'error' => $message,
            ]);
        }

        $weatherResult = $weatherPrediction->predict($targetDate, PredictionType::WEATHER->value);

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $weatherResult['model_input'],
            'mlResult' => null,
            'error' => null,
        ]);
    }
}
