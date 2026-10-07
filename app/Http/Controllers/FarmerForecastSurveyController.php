<?php

namespace App\Http\Controllers;

use App\Models\WeatherForecastFeedback;
use App\Services\Weather\WeatherFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Forecast page's small survey (2026-10, Farmer – Naicel): "Tama ba ang taya ng panahon kahapon?" — Tama / Medyo /
 * Mali and what really happened. It reuses the weather feedback backend (WeatherFeedbackService, the
 * weather_forecast_feedback table) with forecast_type "daily". Stored data only: nothing is retrained or changed.
 */
class FarmerForecastSurveyController extends Controller
{
    /** What the farmer can say really happened (a subset of WeatherForecastFeedback::OBSERVED_CONDITIONS). */
    public const OBSERVED = ['Sunny', 'Cloudy', 'Light Rain', 'Heavy Rain', 'Strong Wind'];

    public function store(Request $request, WeatherFeedbackService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'forecast_date' => ['required', 'date_format:Y-m-d'],
            'accuracy' => ['required', Rule::in(WeatherForecastFeedback::ACCURACY_VALUES)],
            'observed_condition' => ['nullable', Rule::in(self::OBSERVED)],
        ], [
            '*' => __('farmer.forecast_survey.failed'),
        ]);

        $outcome = $service->submitDaily($request->user(), $validated['forecast_date'], $validated['accuracy'], $validated['observed_condition'] ?? null);
        // Answered twice (two tabs, a double tap): the first answer stands and the farmer is simply thanked.
        $ok = in_array($outcome['result'], [WeatherFeedbackService::RESULT_SAVED, WeatherFeedbackService::RESULT_DUPLICATE], true);
        $message = $ok ? __('farmer.forecast_survey.thanks') : __('farmer.forecast_survey.failed');

        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return redirect()->route('weather-predictions.index')->with($ok ? 'success' : 'error', $message);
    }
}
