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
use App\Services\WeatherApiService;
use App\Support\LianBarangays;
use App\Support\PredictionWording;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WeatherPredictionController extends Controller
{
    private const LIAN_MUNICIPAL_PREVIEW_LABEL = 'Lian, Batangas municipal preview';

    /**
     * One Predictions page for every role (2026-10), from saved data only:
     *   - weather: [This Week] = days 1–7 or [Next 2 Weeks] = days 1–14 of the stored daily forecast (DailyForecastService);
     *     days it does not reach are "Tantiya" from the monthly model;
     *   - yield: this season's expected yield (auto-detected season). A farmer sees their own stored estimate (farm area
     *     and type from the profile); MAO and IT see the average of farmers' stored estimates for the area shown.
     * Farmers read words (no millimetres); MAO and IT also see numbers. MAO can pick a barangay; IT can open the
     * technical details. No date or month picker, no provider call, no model run.
     */
    public function index(Request $request, \App\Services\Weather\DailyForecastService $daily, \App\Services\Prediction\SeasonYield $seasonYield, \App\Services\Analytics\FarmerDashboardService $farmerHome): View
    {
        $user = $request->user();
        $role = $user->role;
        $range = $request->query('range') === '2weeks' ? '2weeks' : 'week';

        $barangay = match ($role) {
            User::ROLE_FARMER => $user->registeredBarangay(),
            User::ROLE_MAO => LianBarangays::canonicalName(is_string($request->query('barangay')) ? $request->query('barangay') : null),
            default => null,
        };

        $profile = $role === User::ROLE_FARMER ? $user->farmerProfile : null;

        return view('weather-predictions.index', [
            'role' => $role,
            'isFarmer' => $role === User::ROLE_FARMER,
            'range' => $range,
            'barangay' => $barangay,
            'barangays' => $role === User::ROLE_MAO ? LianBarangays::all() : [],
            'forecast' => $daily->forLocation($barangay, $range === '2weeks' ? 14 : 7),
            'yield' => $role === User::ROLE_FARMER ? $farmerHome->seasonEstimate($user) : $this->averageEstimate($barangay, $seasonYield),
            'profileFarm' => $profile ? [
                'area' => (float) ($profile->farm_area ?? 0) > 0 ? (float) $profile->farm_area : null,
                'type' => $profile->farm_type ? __('farmer.my_farm.water.'.strtolower((string) $profile->farm_type)) : null,
            ] : null,
            'technical' => $role === User::ROLE_IT_EXPERT ? $this->technicalDetails() : null,
            // Farmer – Naicel (2026-10): yesterday's forecast to ask the farmer about, once; null when answered or not saved.
            'forecastSurvey' => $role === User::ROLE_FARMER ? rescue(fn () => app(\App\Services\Weather\WeatherFeedbackService::class)->pendingDailyPrompt($user), null, false) : null,
        ]);
    }

    /**
     * MAO / IT: this season's expected yield for the area shown, as the average of the farmers' saved estimates
     * (each made by the background job from that farmer's own farm). Nothing is run here.
     *
     * @return array<string, mixed>
     */
    private function averageEstimate(?string $barangay, \App\Services\Prediction\SeasonYield $seasonYield): array
    {
        $now = CarbonImmutable::now('Asia/Manila');
        // One saved estimate per farm (each farm in its own barangay), read with one query for the farms.
        $estimates = \App\Services\Analytics\FarmerDashboardService::storedEstimates($barangay);
        $base = ['season' => $seasonYield->seasonLabel($now)];
        if ($estimates->isEmpty()) {
            return $base + ['state' => 'nodata', 'reason' => __('farmer.predictions.yield_none_staff')];
        }
        $average = (float) $estimates->avg(fn (array $e): float => (float) ($e['yield_tons_per_hectare'] ?? $e['predicted_yield']));
        $ranAt = $estimates->pluck('ran_at')->filter()->sort()->last();

        return $base + ['state' => 'ok', 'count' => $estimates->count()]
            + $seasonYield->describe($average, $now->month)
            + ['ran_at' => $ranAt ? CarbonImmutable::parse($ranAt)->timezone('Asia/Manila') : null];
    }

    /**
     * IT only: what made the numbers — the monthly weather model's version, last run and confidence, and the active
     * rice-yield model's registered version and test metrics (RMSE, MAE, R²). A value that was never recorded says so.
     *
     * @return array<string, mixed>
     */
    private function technicalDetails(): array
    {
        $month = \App\Support\BusinessClock::now()->toImmutable()->startOfMonth();
        $weather = rescue(fn () => app(PredictionOrchestrator::class)->preparedMonthlyWeatherForecasts([$month])[$month->format('Y-m')] ?? null, null, false);
        $model = rescue(fn () => \App\Models\MlModel::query()->where('purpose', \App\Models\MlModel::PURPOSE_RICE_YIELD)->where('is_active', true)->first(), null, false);
        $lastYieldRun = \App\Services\Analytics\FarmerDashboardService::storedEstimates()->pluck('ran_at')->filter()->sort()->last();

        return [
            'weather' => [
                'model' => 'Monthly weather Random Forest',
                'version' => $weather['weather_model'] ?? \App\Services\MachineLearning\MonthlyWeatherRandomForest::MODEL_VERSION,
                'last_run' => ! empty($weather['generated_at']) ? CarbonImmutable::parse($weather['generated_at'])->timezone('Asia/Manila') : null,
                'confidence' => data_get($weather, 'confidence.value'),
                'training' => ($weather['training_period']['from'] ?? null) ? $weather['training_period']['from'].' – '.$weather['training_period']['to'] : null,
                // The monthly model does not store test metrics; they are shown as not recorded rather than invented.
                'rmse' => null, 'mae' => null, 'r2' => null,
            ],
            'yield' => [
                'model' => $model?->name,
                'version' => $model?->version,
                'last_run' => $lastYieldRun ? CarbonImmutable::parse($lastYieldRun)->timezone('Asia/Manila') : null,
                'trained_at' => $model?->trained_at,
                'rmse' => $model?->rmse, 'mae' => $model?->mae, 'r2' => $model?->r2,
            ],
        ];
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

        // The stored forecast for this place (the run is queued; nothing is fetched while the person waits).
        $liveWeather = $location['is_municipal_preview']
            ? $this->municipalForecast($weatherApi)
            : $weatherApi->forecast(
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

        $run = $query->where('uuid', $request->query('run'))->firstOrFail();
        // Never show an endless "loading" for a request no worker finished in time.
        $run->expireIfStale();

        return $run;
    }

    /**
     * @return array{barangay: ?string, label: string, coordinates: ?array{0: float, 1: float}, is_municipal_preview: bool}
     */
    /**
     * The shared Lian forecast (the one iclimate:refresh-weather-cache stores for the dashboards), labelled as the
     * municipal preview. Read under its own label it would be a separate cache entry nothing keeps current.
     */
    private function municipalForecast(WeatherApiService $weatherApi): ?array
    {
        $forecast = $weatherApi->forecast();

        return $forecast === null ? null : array_merge($forecast, ['location' => self::LIAN_MUNICIPAL_PREVIEW_LABEL]);
    }

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
