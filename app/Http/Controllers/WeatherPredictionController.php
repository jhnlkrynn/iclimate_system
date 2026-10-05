<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\WeatherPredictionRequest;
use App\Models\AnalyticsRun;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\Prediction\ShortTermOutlookService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\MonthlyWeatherCalendarService;
use App\Services\Weather\WeatherFeedbackService;
use App\Services\WeatherApiService;
use App\Support\LianBarangays;
use App\Support\PredictionWording;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    private const LIAN_MUNICIPAL_PREVIEW_LABEL = 'Lian, Batangas municipal preview';

    public function index(
        Request $request,
        PredictionDateValidator $dateValidator,
        WeatherApiService $weatherApi,
        MonthlyWeatherCalendarService $calendarService,
        \App\Services\Weather\MonthlyWeatherOutlookService $outlookService,
        ShortTermOutlookService $shortTermOutlookService,
        PredictionOrchestrator $predictionOrchestrator,
        WeatherFeedbackService $feedbackService,
        FarmerDashboardWeatherService $sharedWeather,
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

        // Technical model/analytics details are for IT Expert accounts only.
        $isTechnical = $request->user()->role === User::ROLE_IT_EXPERT;

        // $errorInfo is always farmer-safe. The raw failure text stays in the
        // server log (and analytics_runs.error), and is exposed under
        // 'technical' to IT Expert accounts only.
        $errorInfo = null;
        if ($dateMessage = $dateValidator->validate($targetDate)) {
            $errorInfo = ['title' => 'Please Choose Another Date', 'detail' => $dateMessage, 'technical' => null];
        } elseif ($analyticsRun?->status === AnalyticsRun::STATUS_FAILED) {
            Log::warning('Prediction run failed.', [
                'analytics_run_id' => $analyticsRun->id,
                'type' => $analyticsRun->type,
                'error' => $analyticsRun->error,
            ]);
            $errorInfo = PredictionWording::friendlyFailure($analyticsRun->error)
                + ['technical' => $isTechnical ? $analyticsRun->error : null];
        }
        $error = $errorInfo['detail'] ?? null;

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
        // The Lian-wide reading is the same one the dashboards show; the
        // barangay reading is shown beside it (not instead of it).
        $lianWeather = rescue(fn () => $sharedWeather->current(), null, false);
        $liveWeather = rescue(function () use ($location, $sharedWeather, $weatherApi, $weatherResult) {
            return match (true) {
                // No usable barangay: show the Lian-wide reading instead of nothing.
                ! $location['coordinates'], $location['is_municipal_preview'] => $sharedWeather->alignForecast($weatherApi->forecast(
                    location: self::LIAN_MUNICIPAL_PREVIEW_LABEL,
                )) ?? data_get($weatherResult, 'live_weather'),
                default => $weatherApi->forecast(
                    latitude: $location['coordinates'][0],
                    longitude: $location['coordinates'][1],
                    location: $location['label'],
                ) ?? data_get($weatherResult, 'live_weather'),
            };
        }, null, false);

        // Keep both readings on the same update time: if they were fetched
        // more than two minutes apart, refresh both together.
        if (! $location['is_municipal_preview'] && $location['coordinates'] && $this->readingsOutOfSync($liveWeather, $lianWeather)) {
            $liveWeather = rescue(fn () => $weatherApi->forecast(
                refresh: true,
                latitude: $location['coordinates'][0],
                longitude: $location['coordinates'][1],
                location: $location['label'],
            ), null, false) ?? $liveWeather;
            $lianWeather = rescue(fn () => $sharedWeather->current(force: true), null, false) ?? $lianWeather;
        }
        $calendarMonth = isset($validated['calendar_month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['calendar_month'].'-01')
            : \App\Support\BusinessClock::now()->toImmutable()->startOfMonth();
        // Municipal calendar uses the shared Lian reading so its daily values
        // match the Farmer/MAO dashboards, login page and chatbot exactly.
        $calendarWeather = rescue(fn () => $sharedWeather->alignForecast($weatherApi->forecast(
            location: self::LIAN_MUNICIPAL_PREVIEW_LABEL,
        )), null, false);
        // A single whole-month estimate (not per-day) from the same monthly
        // Random Forest model already used by the barangay risk map — shown
        // in the calendar as its own clearly-labeled "Monthly Prediction"
        // summary, never written into individual day cells.
        //
        // Reads through PredictionOrchestrator: a persisted/cached forecast
        // (normally pre-generated by the `iclimate:refresh-predictions`
        // scheduler) is returned instantly; only a month nothing has been
        // generated for yet falls back to computing inline here. A genuine
        // failure (not just "not enough history yet", which the model
        // itself reports as a normal ready=false result) must never fatal
        // the whole page — MonthlyWeatherCalendarService already treats a
        // null monthly prediction as "no summary to show", the same as any
        // other not-ready result.
        try {
            $monthlyPrediction = $predictionOrchestrator->monthlyWeatherForecast($calendarMonth);
        } catch (\App\Exceptions\PredictionNotReady) {
            // Not an error: this request may only read prepared forecasts and the scheduler has not built this month yet.
            $monthlyPrediction = null;
        } catch (\Throwable $exception) {
            report($exception);
            $monthlyPrediction = null;
        }
        $weatherCalendar = $calendarService->build($calendarMonth, $calendarWeather, $monthlyPrediction);
        $monthlyOutlook = $outlookService->build($weatherCalendar);
        $liveWeatherError = match (true) {
            ! $location['coordinates'] => 'Your saved barangay is not recognized as one of Lian’s 19 barangays. Update your profile location.',
            ! $liveWeather && $isTechnical => 'Open-Meteo is temporarily unavailable and no saved forecast exists for your location.',
            ! $liveWeather => 'The forecast is not available right now. Please try again later.',
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
            'errorInfo' => $errorInfo,
            'isTechnical' => $isTechnical,
            'isFarmer' => $request->user()->role === User::ROLE_FARMER,
            'yieldSummary' => $mlResult
                ? $this->yieldSummary($mlResult, $location['barangay'], $targetDate, $targetMonth)
                : null,
            'weatherCheck' => rescue(fn () => $feedbackService->pendingPrompt($request->user()), null, false),
            'weatherCheckBarangayIssue' => $request->user()->role === User::ROLE_FARMER
                ? rescue(fn () => $feedbackService->barangayFor($request->user())[1] ?? null, null, false)
                : null,
            'liveWeather' => $liveWeather,
            'lianWeather' => ($lianWeather['success'] ?? false) === true && $location['coordinates'] && ! $location['is_municipal_preview'] ? $lianWeather : null,
            'liveWeatherError' => $liveWeatherError,
            'liveWeatherCacheMinutes' => max(30, min(60, (int) config('services.weather_api.refresh_minutes', 45))),
            'savedWeatherLocation' => $location,
            'weatherCalendar' => $weatherCalendar,
            'monthlyOutlook' => $monthlyOutlook,
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
     * Farmer-facing yield summary: expected yield, previous yield, plain
     * difference/status and one short recommendation. Built only from values
     * the backend already produced; no new prediction logic.
     *
     * @param  array<string, mixed>  $mlResult
     * @return array<string, mixed>
     */
    private function yieldSummary(array $mlResult, ?string $barangay, CarbonImmutable $targetDate, CarbonImmutable $targetMonth): array
    {
        // A missing yield is not 0 t/ha; without one there is no summary.
        if (! is_numeric($mlResult['predicted_yield'] ?? null)) {
            return [];
        }
        $expected = (float) $mlResult['predicted_yield'];
        $comparison = $barangay ? $this->yieldComparison($expected, $barangay, $targetDate) : null;
        $previous = ($comparison['has_data'] ?? false) ? (float) $comparison['prior_yield'] : null;
        $status = PredictionWording::yieldStatus($expected, $previous);

        return [
            'expected' => $expected,
            'period' => $targetMonth->format('F Y'),
            'previous' => $previous,
            'previous_year' => $comparison['year'] ?? ($targetDate->year - 1),
            'barangay' => $barangay,
            'status' => $status,
            'recommendation' => $mlResult['planting_advisory'] ?? data_get($mlResult, 'decision_support.overall_recommendation.planting'),
            'irrigation' => $mlResult['irrigation_recommendation'] ?? null,
            'risk' => PredictionWording::riskLabel($mlResult['risk_level'] ?? null),
        ];
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

    private function readingsOutOfSync(?array $barangayWeather, array $lianWeather): bool
    {
        $barangayFetchedAt = $barangayWeather['fetched_at'] ?? null;
        $lianFetchedAt = $lianWeather['server_fetched_at'] ?? null;

        if (! $barangayFetchedAt || ! $lianFetchedAt || ! empty($barangayWeather['is_cached_fallback']) || ($lianWeather['stale'] ?? false)) {
            return false;
        }

        return abs(Carbon::parse($barangayFetchedAt)->diffInSeconds(Carbon::parse($lianFetchedAt))) > 120;
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
                'coordinates' => \App\Support\LianLocation::point(),
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
