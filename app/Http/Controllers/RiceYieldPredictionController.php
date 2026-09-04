<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\RiceYieldPredictionRequest;
use App\Models\FarmerProfile;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\WeatherPredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class RiceYieldPredictionController extends Controller
{
    public function predict(
        RiceYieldPredictionRequest $request,
        PredictionDateValidator $dateValidator,
        RiceYieldPredictionService $riceYield,
        WeatherPredictionService $weatherPrediction,
    ): View {
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

        try {
            $weatherResult = $weatherPrediction->predict($targetDate, PredictionType::WEATHER->value);
            $sharedResult = $riceYield->predictForUser(
                $targetDate,
                $validated['farm_type'] ?? $this->defaultFarmType($request),
                (float) $validated['area'],
                $request->user(),
                [],
                PredictionType::RICE_YIELD->value,
            );
        } catch (Throwable $exception) {
            Log::error('Rice yield prediction failed.', [
                'user_id' => $request->user()?->id,
                'prediction_date' => $targetDate->toDateString(),
                'farm_type' => $validated['farm_type'] ?? $this->defaultFarmType($request),
                'area' => $validated['area'] ?? null,
                'message' => $exception->getMessage(),
            ]);

            return view('weather-predictions.index', [
                'targetMonth' => $targetMonth,
                'targetDate' => $targetDate,
                'result' => null,
                'defaultModelInput' => [],
                'mlResult' => null,
                'error' => 'Rice-yield prediction could not be generated. Please verify the farm area and try again.',
            ]);
        }

        $engineResult = $sharedResult['engine_result'];

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $engineResult['model_input'],
            'mlResult' => $this->buildMlResult($sharedResult),
            'error' => null,
        ]);
    }

    private function defaultFarmType(RiceYieldPredictionRequest $request): string
    {
        return (string) ($request->user()?->farmerProfile?->farm_type ?? FarmerProfile::FARM_TYPE_RAINFED);
    }

    /**
     * @param  array<string, mixed>  $sharedResult
     * @return array<string, mixed>
     */
    private function buildMlResult(array $sharedResult): array
    {
        unset($sharedResult['engine_result']);

        return [
            ...$sharedResult,
            'source' => $sharedResult['source_name'] ?? $sharedResult['source'],
        ];
    }
}
