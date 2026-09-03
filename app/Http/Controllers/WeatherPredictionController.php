<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\PredictionEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    public function index(Request $request, PredictionDateValidator $dateValidator, PredictionEngine $engine): View
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

        $engineResult = $engine->predict($targetDate, $this->defaultFarmType($request), null, $request->user());

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $engineResult['weather'],
            'defaultModelInput' => $engineResult['model_input'],
            'mlResult' => $this->buildMlResult($engineResult),
            'error' => null,
        ]);
    }

    public function predict(Request $request, PredictionDateValidator $dateValidator, PredictionEngine $engine): View
    {
        $validated = $request->validate([
            'prediction_date' => ['required', 'date'],
            'farm_type' => ['nullable', 'in:Rainfed,Irrigated'],
        ]);

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

        $engineResult = $engine->predict($targetDate, $validated['farm_type'] ?? $this->defaultFarmType($request), null, $request->user());

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $engineResult['weather'],
            'defaultModelInput' => $engineResult['model_input'],
            'mlResult' => $this->buildMlResult($engineResult),
            'error' => null,
        ]);
    }

    /**
     * Same fallback chain as PredictionService uses for the AI Assistant, so a
     * logged-in farmer's own profile decides the default farm type here too.
     */
    private function defaultFarmType(Request $request): string
    {
        return (string) ($request->user()?->farmerProfile?->farm_type ?? FarmerProfile::FARM_TYPE_RAINFED);
    }

    private function buildMlResult(array $engineResult): array
    {
        $yield = $engineResult['yield'];
        $decision = $engineResult['decision_support'];

        return [
            ...$yield,
            'model_input' => $engineResult['model_input'],
            'source' => $yield['source_name'],
            'planting_advisory' => $decision['planting']['recommendation'],
            'irrigation_recommendation' => $decision['irrigation']['recommendation'],
            'notifications' => $decision['notifications'],
            'decision_support' => $decision,
            'api_error' => $engineResult['api_error'],
        ];
    }
}
