<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\WeatherPredictionRequest;
use App\Models\AnalyticsRun;
use App\Models\RiceProduction;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\ShortTermOutlookService;
use App\Services\Weather\MonthlyWeatherCalendarService;
use App\Services\WeatherApiService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    private const LIAN_MUNICIPAL_COORDINATES = [14.0389, 120.6503];

    private const LIAN_MUNICIPAL_PREVIEW_LABEL = 'Lian, Batangas municipal preview';

    public function index(
        Request $request,
        PredictionDateValidator $dateValidator,
        WeatherApiService $weatherApi,
        MonthlyWeatherCalendarService $calendarService,
        ShortTermOutlookService $shortTermOutlookService,
    ): View {
        $validated = $request->validate([
            'target_month' => ['nullable', 'date_format:Y-m'],
            'target_date' => ['nullable', 'date'],
            'calendar_month' => ['nullable', 'date_format:Y-m'],
            'run' => ['nullable', 'uuid'],
        ]);
        $analyticsRun = $this->runForRequest($request);
        $requestedDate = data_get($analyticsRun?->input, 'target_date');
        $targetDate = $requestedDate
            ? CarbonImmutable::parse($requestedDate)
            : (isset($validated['target_date'])
                ? CarbonImmutable::parse($validated['target_date'])
                : (isset($validated['target_month'])
                    ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')
                    : PredictionDateValidator::defaultTargetDate()));
        $targetMonth = $targetDate->startOfMonth();

        $error = $dateValidator->validate($targetDate);
        if (! $error && $analyticsRun?->status === AnalyticsRun::STATUS_FAILED) {
            $error = $analyticsRun->error;
        }

        $stored = $analyticsRun?->status === AnalyticsRun::STATUS_COMPLETED
            ? (array) $analyticsRun->result
            : [];
        $weatherResult = $analyticsRun?->type === 'rice_yield_prediction'
            ? data_get($stored, 'weather_result')
            : ($stored ?: null);
        $mlResult = $analyticsRun?->type === 'rice_yield_prediction'
            ? data_get($stored, 'ml_result')
            : null;
        $location = $this->savedLocation($request);
        $liveWeather = $location['coordinates']
            ? ($weatherApi->forecast(
                latitude: $location['coordinates'][0],
                longitude: $location['coordinates'][1],
                location: $location['label'],
            ) ?? data_get($weatherResult, 'live_weather'))
            : null;
        $calendarMonth = isset($validated['calendar_month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['calendar_month'].'-01')
            : CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $calendarWeather = $weatherApi->forecast(
            latitude: self::LIAN_MUNICIPAL_COORDINATES[0],
            longitude: self::LIAN_MUNICIPAL_COORDINATES[1],
            location: self::LIAN_MUNICIPAL_PREVIEW_LABEL,
        );
        $weatherCalendar = $calendarService->build($calendarMonth, $calendarWeather);
        $liveWeatherError = match (true) {
            ! $location['coordinates'] => 'Your saved barangay is not recognized as one of Lian’s 19 barangays. Update your profile location.',
            ! $liveWeather => 'Open-Meteo is temporarily unavailable and no saved forecast exists for your location.',
            default => null,
        };

        $defaultModelInput = (array) data_get($weatherResult, 'model_input', []);

        return view('weather-predictions.index', [
            'targetMonth' => $targetMonth,
            'targetDate' => $targetDate,
            'result' => $weatherResult,
            'defaultModelInput' => $defaultModelInput,
            'mlResult' => $mlResult,
            'shortTermOutlook' => $defaultModelInput !== []
                ? $shortTermOutlookService->build($defaultModelInput, $targetMonth)
                : null,
            'yieldComparison' => ($mlResult && $location['barangay'])
                ? $this->yieldComparison((float) $mlResult['predicted_yield'], $location['barangay'], $targetDate)
                : null,
            'analyticsRun' => $analyticsRun,
            'error' => $error,
            'liveWeather' => $liveWeather,
            'liveWeatherError' => $liveWeatherError,
            'liveWeatherCacheMinutes' => max(30, min(60, (int) config('services.weather_api.refresh_minutes', 45))),
            'savedWeatherLocation' => $location,
            'weatherCalendar' => $weatherCalendar,
            'latestPredictionSavedAt' => $analyticsRun?->status === AnalyticsRun::STATUS_COMPLETED
                ? $analyticsRun->completed_at
                : null,
        ]);
    }

    public function predict(
        WeatherPredictionRequest $request,
        PredictionDateValidator $dateValidator,
        AnalyticsDispatcher $analytics,
        WeatherApiService $weatherApi,
    ): RedirectResponse {
        $validated = $request->validated();
        $targetDate = CarbonImmutable::parse($validated['prediction_date']);

        if ($message = $dateValidator->validate($targetDate)) {
            return back()->withErrors(['prediction_date' => $message])->withInput();
        }

        $location = $this->savedLocation($request);

        if (! $location['coordinates']) {
            return back()->withErrors([
                'prediction_date' => 'Add a valid Lian barangay to your profile before requesting a location-based prediction.',
            ])->withInput();
        }

        $liveWeather = $weatherApi->forecast(
            latitude: $location['coordinates'][0],
            longitude: $location['coordinates'][1],
            location: $location['label'],
        );
        $run = $analytics->dispatch('weather_prediction', [
            'prediction_type' => PredictionType::WEATHER->value,
            'target_date' => $targetDate->toDateString(),
            'location' => $location['label'],
            'barangay' => $location['barangay'],
            'live_weather' => $liveWeather,
        ], $request->user());

        return redirect()->route('weather-predictions.index', ['run' => $run->uuid]);
    }

    /**
     * Compares a predicted yield against the same barangay's most recent
     * prior-year RiceProduction record, tiering the result so a meaningful
     * decrease surfaces as an actionable note rather than a bare number.
     *
     * @return array<string, mixed>
     */
    private function yieldComparison(float $currentYield, string $barangay, CarbonImmutable $targetDate): array
    {
        $lastYear = $targetDate->year - 1;
        $prior = RiceProduction::query()
            ->where('barangay', $barangay)
            ->where('year', $lastYear)
            ->whereNotNull('yield_per_hectare')
            ->orderByDesc('id')
            ->first();

        if (! $prior) {
            return [
                'has_data' => false,
                'barangay' => $barangay,
                'year' => $lastYear,
            ];
        }

        $priorYield = (float) $prior->yield_per_hectare;
        $deltaPercent = $priorYield > 0 ? round((($currentYield - $priorYield) / $priorYield) * 100, 1) : null;

        $tier = match (true) {
            $deltaPercent === null => 'unknown',
            $deltaPercent <= -15 => 'significant_decrease',
            $deltaPercent < 0 => 'minor_decrease',
            default => 'stable_or_up',
        };

        $message = match ($tier) {
            'significant_decrease' => 'Yield is trending well below '.$lastYear.' ('.$deltaPercent.'%). Review the irrigation and planting guidance above before finalizing plans.',
            'minor_decrease' => 'Yield is slightly below '.$lastYear.' ('.$deltaPercent.'%). Worth monitoring, but not yet a major concern.',
            'stable_or_up' => 'Yield is stable or improving compared to '.$lastYear.' ('.($deltaPercent >= 0 ? '+' : '').$deltaPercent.'%).',
            default => 'A prior-year record exists, but a percentage comparison could not be computed.',
        };

        return [
            'has_data' => true,
            'barangay' => $barangay,
            'year' => $lastYear,
            'prior_yield' => $priorYield,
            'current_yield' => $currentYield,
            'delta_percent' => $deltaPercent,
            'tier' => $tier,
            'message' => $message,
        ];
    }

    private function runForRequest(Request $request): ?AnalyticsRun
    {
        if (! $request->filled('run')) {
            return null;
        }

        $query = AnalyticsRun::query()
            ->where('requested_by', $request->user()->id)
            ->whereIn('type', ['weather_prediction', 'rice_yield_prediction']);

        return $query->where('uuid', $request->query('run'))->firstOrFail();
    }

    /**
     * @return array{barangay: ?string, label: string, coordinates: ?array{0: float, 1: float}, is_municipal_preview: bool}
     */
    private function savedLocation(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('farmerProfile');
        $savedBarangay = $user->farmerProfile?->barangay ?: $user->barangay;
        $barangay = LianBarangays::canonicalName($savedBarangay);

        if (blank($savedBarangay)) {
            return [
                'barangay' => null,
                'label' => self::LIAN_MUNICIPAL_PREVIEW_LABEL,
                'coordinates' => self::LIAN_MUNICIPAL_COORDINATES,
                'is_municipal_preview' => true,
            ];
        }

        return [
            'barangay' => $barangay,
            'label' => $barangay ? $barangay.', Lian, Batangas' : 'Saved location unavailable',
            'coordinates' => $barangay ? LianBarangays::coordinateFor($barangay) : null,
            'is_municipal_preview' => false,
        ];
    }
}
