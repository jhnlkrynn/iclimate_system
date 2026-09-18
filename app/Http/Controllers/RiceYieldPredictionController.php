<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\RiceYieldPredictionRequest;
use App\Models\FarmerProfile;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Prediction\PredictionDateValidator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class RiceYieldPredictionController extends Controller
{
    public function predict(
        RiceYieldPredictionRequest $request,
        PredictionDateValidator $dateValidator,
        AnalyticsDispatcher $analytics,
    ): RedirectResponse {
        $validated = $request->validated();
        $targetDate = CarbonImmutable::parse($validated['prediction_date']);

        if ($message = $dateValidator->validate($targetDate)) {
            return back()->withErrors(['prediction_date' => $message])->withInput();
        }

        $run = $analytics->dispatch('rice_yield_prediction', [
            'prediction_type' => PredictionType::RICE_YIELD->value,
            'target_date' => $targetDate->toDateString(),
            'farm_type' => $validated['farm_type'] ?? $request->user()?->farmerProfile?->farm_type ?? FarmerProfile::FARM_TYPE_RAINFED,
            'area' => (float) $validated['area'],
        ], $request->user());

        return redirect()->route('weather-predictions.index', ['run' => $run->uuid]);
    }
}
