<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\RiceYieldPredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    public function index(Request $request, PredictionDateValidator $dateValidator, RiceYieldPredictionService $riceYield): View
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

        $sharedResult = $riceYield->predictForUser($targetDate, $this->defaultFarmType($request), null, $request->user());
        $engineResult = $sharedResult['engine_result'];

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $engineResult['weather'],
            'defaultModelInput' => $engineResult['model_input'],
            'mlResult' => $this->buildMlResult($sharedResult),
            'error' => null,
        ]);
    }

    public function predict(Request $request, PredictionDateValidator $dateValidator, RiceYieldPredictionService $riceYield): View
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

        $sharedResult = $riceYield->predictForUser($targetDate, $validated['farm_type'] ?? $this->defaultFarmType($request), null, $request->user());
        $engineResult = $sharedResult['engine_result'];

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $engineResult['weather'],
            'defaultModelInput' => $engineResult['model_input'],
            'mlResult' => $this->buildMlResult($sharedResult),
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

    private function buildMlResult(array $sharedResult): array
    {
        unset($sharedResult['engine_result']);

        return [
            ...$sharedResult,
            'source' => $sharedResult['source_name'] ?? $sharedResult['source'],
        ];
    }
}
