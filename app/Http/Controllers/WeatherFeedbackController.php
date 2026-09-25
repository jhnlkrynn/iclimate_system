<?php

namespace App\Http\Controllers;

use App\Models\WeatherForecastFeedback;
use App\Services\Weather\WeatherFeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stores a farmer's "Was this forecast accurate?" answer. Farmer-only (route
 * middleware) and throttled. Stored data only: never retrains or edits
 * predictions, ClimateRecord or model files.
 */
class WeatherFeedbackController extends Controller
{
    public function store(Request $request, WeatherFeedbackService $service): RedirectResponse
    {
        $validated = $request->validate([
            'forecast_date' => ['required', 'date_format:Y-m-d'],
            'accuracy' => ['required', Rule::in(WeatherForecastFeedback::ACCURACY_VALUES)],
            'observed_condition' => ['nullable', Rule::in(WeatherForecastFeedback::OBSERVED_CONDITIONS)],
        ], [
            'accuracy.required' => 'Please choose one answer.',
            'accuracy.in' => 'Please choose one of the answers shown.',
            'observed_condition.in' => 'Please choose one of the weather options shown.',
        ]);

        $outcome = $service->submit(
            $request->user(),
            $validated['forecast_date'],
            $validated['accuracy'],
            $validated['observed_condition'] ?? null,
        );

        $redirect = redirect()->route('weather-predictions.index', ['tab' => 'weather-forecast']);

        return $outcome['result'] === WeatherFeedbackService::RESULT_SAVED
            ? $redirect->with('weather_check_status', WeatherFeedbackService::message($outcome['result']))
            : $redirect->with('weather_check_error', WeatherFeedbackService::message($outcome['result']));
    }
}
