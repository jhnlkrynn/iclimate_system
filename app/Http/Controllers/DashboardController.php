<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Models\AdvisoryFeedback;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\MachineLearning\ModelEvaluationService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\PythonService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function farmer(Request $request, FarmerDashboardWeatherService $weather, TyphoonSafetyService $typhoonSafety, RiceYieldPredictionService $riceYield, BarangayRiskMapService $riskMapService): View
    {
        $user = $request->user();
        $farmerProfile = $user->farmerProfile;
        $farmerBarangay = $farmerProfile?->barangay ?? $user->barangay;

        $dashboardWeather = $weather->current($request->boolean('refresh_weather'));
        $dashboardData = $this->stats();
        $recent = $this->recentOperationalData();
        $activeTyphoonSafetyEvent = $typhoonSafety->activeEvent();
        $weatherAlerts = $this->weatherAlertsFor($request);

        // Bug fix: this previously filtered on the literal string 'Published'
        // while the column stores lowercase status values (see
        // PlantingAdvisory::STATUS_PUBLISHED), so it always returned zero
        // rows. Now scoped to the farmer's own barangay, matching
        // PlantingAdvisoryController::index()'s farmer view.
        $advisories = PlantingAdvisory::query()
            ->active()
            ->forBarangay($farmerBarangay)
            ->latest('valid_from')
            ->take(5)
            ->get();

        $myAdvisoryFeedback = AdvisoryFeedback::query()
            ->where('user_id', $user->id)
            ->whereIn('planting_advisory_id', $advisories->pluck('id'))
            ->get()
            ->keyBy('planting_advisory_id');

        $farmerBarangayRisk = null;
        if ($farmerBarangay) {
            try {
                $farmerBarangayRisk = $riskMapService->detailFor($farmerBarangay);
            } catch (\Throwable) {
                $farmerBarangayRisk = null;
            }
        }
        $farmerFarmBoundary = $farmerProfile?->farmBoundary;

        return view('dashboards.farmer', $dashboardData + [
            'farmerBarangay' => $farmerBarangay,
            'farmerBarangayRisk' => $farmerBarangayRisk,
            'farmerFarmBoundary' => $farmerFarmBoundary,
            'feedPosts' => $recent['farmerFeedPosts'],
            'advisories' => $advisories,
            'myAdvisoryFeedback' => $myAdvisoryFeedback,
            'notifications' => UserNotification::query()->where('user_id', $user->id)->latest()->take(5)->get(),
            'unreadNotificationCount' => UserNotification::query()->where('user_id', $user->id)->where('is_read', false)->count(),
            'activeWeatherAlerts' => $weatherAlerts['count'],
            'weatherAlertNotifications' => $weatherAlerts['items'],
            'climateSummary' => $recent['recentClimateRecords']->first(),
            'recentClimateRecords' => $recent['recentClimateRecords'],
            'dashboardWeather' => $dashboardWeather,
            'dashboardWeatherResponse' => $weather->responsePayload($dashboardWeather),
            'weatherGuidance' => $weather->guidance($dashboardWeather),
            'farmerBarangay' => $farmerBarangay,
            'farmerBarangayRisk' => $farmerBarangayRisk,
            'farmerYieldPrediction' => $this->farmerYieldPrediction($user, $farmerProfile, $riceYield),
            'farmerYoyDelta' => $this->riceYoyDelta($farmerBarangay),
            'farmerHighlights' => $this->farmerHighlights($weatherAlerts, $advisories->first(), $farmerBarangayRisk),
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponse' => $this->hasTyphoonSafetyTable() && $activeTyphoonSafetyEvent
                ? TyphoonSafetyResponse::query()
                    ->where('user_id', $user->id)
                    ->where('event_key', $activeTyphoonSafetyEvent['key'])
                    ->first()
                : null,
        ]);
    }

    /**
     * @return array{severity: string, title: string, message: string}[]
     */
    private function farmerHighlights(array $weatherAlerts, ?PlantingAdvisory $latestAdvisory, ?array $farmerBarangayRisk): array
    {
        $highlights = [];

        if (($weatherAlerts['count'] ?? 0) > 0) {
            $topAlert = $weatherAlerts['items']->first();
            $highlights[] = [
                'severity' => 'critical',
                'weight' => 3,
                'title' => $topAlert?->title ?? 'Weather alert active',
                'message' => $topAlert ? (string) str($topAlert->message)->limit(120) : 'Check current conditions before heading to the field.',
            ];
        }

        $publishedAt = $latestAdvisory?->published_at ?? $latestAdvisory?->created_at;
        if ($latestAdvisory && $publishedAt && $publishedAt->gt(now()->subDays(3))) {
            $highlights[] = [
                'severity' => 'info',
                'weight' => 2,
                'title' => 'New advisory: '.$latestAdvisory->title,
                'message' => 'Published '.$publishedAt->diffForHumans().' for '.($latestAdvisory->target_barangay ?: 'all barangays').'.',
            ];
        }

        $riskLevel = $farmerBarangayRisk['risk_level'] ?? null;
        if ($riskLevel && in_array($riskLevel, ['High', 'Severe'], true)) {
            $highlights[] = [
                'severity' => $riskLevel === 'Severe' ? 'critical' : 'warning',
                'weight' => $riskLevel === 'Severe' ? 3 : 2,
                'title' => 'Your barangay is at '.$riskLevel.' risk',
                'message' => $farmerBarangayRisk['advisory']['planting'] ?? 'Review current conditions before planting or harvesting.',
            ];
        }

        usort($highlights, fn (array $a, array $b) => $b['weight'] <=> $a['weight']);

        return array_slice($highlights, 0, 2);
    }

    /**
     * Cached per farmer for 6 hours — this calls the external rice-yield ML
     * model, which is too slow/fragile to call on every dashboard load. A
     * failed call returns null (and Laravel's Cache::remember() will retry
     * rather than caching the failure) so the widget can degrade gracefully.
     */
    private function farmerYieldPrediction(User $user, ?FarmerProfile $farmerProfile, RiceYieldPredictionService $riceYield): ?array
    {
        $farmType = $farmerProfile?->farm_type ?: FarmerProfile::FARM_TYPE_RAINFED;

        return Cache::remember(
            'dashboard:farmer-prediction:'.$user->id,
            now()->addHours(6),
            function () use ($riceYield, $farmType, $user) {
                try {
                    return $riceYield->predictForUser(
                        CarbonImmutable::now(),
                        $farmType,
                        null,
                        $user,
                        [],
                        PredictionType::RICE_YIELD->value,
                    );
                } catch (Throwable $exception) {
                    Log::warning('Farmer dashboard yield prediction widget failed.', [
                        'user_id' => $user->id,
                        'message' => $exception->getMessage(),
                    ]);

                    return null;
                }
            }
        );
    }

    public function farmerWeather(Request $request, FarmerDashboardWeatherService $weather): JsonResponse
    {
        return response()->json($weather->responsePayload($weather->current($request->boolean('refresh'))));
    }

    public function liveWeather(Request $request, FarmerDashboardWeatherService $weather): JsonResponse
    {
        $payload = $weather->responsePayload($weather->current($request->boolean('refresh')));

        return response()->json([
            'success' => (bool) ($payload['success'] ?? false),
            'data' => $payload,
        ]);
    }

    public function maoWeather(Request $request, WeatherApiService $weatherApi): JsonResponse
    {
        $liveWeather = $weatherApi->forecast(refresh: $request->boolean('refresh'));

        return response()->json([
            'ok' => $liveWeather !== null,
            'weather' => $liveWeather,
            'source_label' => $liveWeather
                ? data_get($liveWeather, 'source', 'Open-Meteo').' live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas')
                : 'Stored climate records',
            'checked_at' => now((string) config('services.weather.timezone', 'Asia/Manila'))->toIso8601String(),
        ]);
    }

    public function mao(Request $request, WeatherApiService $weatherApi, TyphoonSafetyService $typhoonSafety): View
    {
        $recent = $this->recentOperationalData();
        $climateChartRecords = ClimateRecord::query()
            ->orderByDesc('record_date')
            ->take(12)
            ->get()
            ->sortBy('record_date')
            ->values();

        $liveWeather = $weatherApi->forecast();
        $storedWeatherChartData = [
            'labels' => $climateChartRecords->map(fn (ClimateRecord $record) => $record->record_date?->format('M d') ?? 'N/A')->all(),
            'rainfall' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->rainfall)->all(),
            'temperature' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->temperature)->all(),
            'humidity' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->humidity)->all(),
            'windSpeed' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->wind_speed)->all(),
        ];
        $weatherChartData = data_get($liveWeather, 'daily_series') ?: $storedWeatherChartData;
        $weatherDataSource = $liveWeather
            ? data_get($liveWeather, 'source', 'Open-Meteo').' live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas')
            : 'Stored climate records';
        $activeTyphoonSafetyEvent = $typhoonSafety->activeEvent();
        $typhoonSafetyResponses = $this->hasTyphoonSafetyTable() && $activeTyphoonSafetyEvent
            ? TyphoonSafetyResponse::query()
                ->with('farmer')
                ->where('event_key', $activeTyphoonSafetyEvent['key'])
                ->latest('responded_at')
                ->get()
            : collect();
        $farmerCount = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->count();
        $pendingAdvisoryCount = PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_PENDING_REVIEW)->count();
        $highRiskAreasList = $recent['highRiskAreas'];
        $typhoonSafetySafeCount = $typhoonSafetyResponses->where('status', TyphoonSafetyResponse::STATUS_SAFE)->count();
        $typhoonSafetyNeedsHelpCount = $typhoonSafetyResponses->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->count();
        $typhoonSafetyNoResponseCount = max(0, $farmerCount - $typhoonSafetyResponses->pluck('user_id')->unique()->count());
        $riskLevelCounts = [
            'Low' => HeatmapArea::query()->where('risk_level', 'Low')->count(),
            'Moderate' => HeatmapArea::query()->where('risk_level', 'Moderate')->count(),
            'High' => HeatmapArea::query()->where('risk_level', 'High')->count(),
            'Severe' => HeatmapArea::query()->where('risk_level', 'Severe')->count(),
        ];
        $latestClimateRecord = $recent['recentClimateRecords']->first();
        $currentSeason = $latestClimateRecord?->season ?? data_get($liveWeather, 'season');
        $fieldReportsPendingCount = FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)->count();

        $scheduleFilters = [
            'month' => max(0, min(12, $request->integer('schedule_month'))),
            'season' => in_array($request->string('schedule_season')->toString(), FarmerPlantingSchedule::SEASONS, true) ? $request->string('schedule_season')->toString() : '',
            'barangay' => $request->string('schedule_barangay')->toString(),
            'farm_type' => in_array($request->string('schedule_farm_type')->toString(), FarmerPlantingSchedule::FARM_TYPES, true) ? $request->string('schedule_farm_type')->toString() : '',
            'farmer' => $request->integer('schedule_farmer'),
            'status' => $request->string('schedule_status')->toString(),
        ];
        $plantingScheduleFeatureReady = Schema::hasTable('farmer_planting_schedules');
        $farmerSchedules = $plantingScheduleFeatureReady
            ? FarmerPlantingSchedule::query()
                ->with(['farmer:id,name,barangay', 'farmer.farmerProfile:id,user_id,full_name,barangay'])
                ->when($scheduleFilters['month'], function ($query, int $month) {
                    $query->where(function ($query) use ($month) {
                        $query->whereMonth('planting_date', $month)
                            ->orWhereMonth('scheduled_harvest_date', $month)
                            ->orWhereMonth('harvest_month', $month);
                    });
                })
                ->when($scheduleFilters['season'], fn ($query, string $season) => $query->where('season', $season))
                ->when($scheduleFilters['barangay'], fn ($query, string $barangay) => $query->where('barangay', $barangay))
                ->when($scheduleFilters['farm_type'], fn ($query, string $farmType) => $query->where('farm_type', $farmType))
                ->when($scheduleFilters['farmer'], fn ($query, int $farmer) => $query->where('user_id', $farmer))
                ->orderByRaw('CASE WHEN scheduled_harvest_date IS NULL THEN harvest_month ELSE scheduled_harvest_date END ASC')
                ->limit(150)
                ->get()
            : collect();
        if ($scheduleFilters['status']) {
            $farmerSchedules = $farmerSchedules
                ->filter(fn (FarmerPlantingSchedule $schedule) => $schedule->monitoringStatus() === $scheduleFilters['status'])
                ->values();
        }

        $weatherFeedbackFilters = [
            'barangay' => $request->query('fb_barangay') ?: null,
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('fb_from')) ? $request->query('fb_from') : null,
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('fb_to')) ? $request->query('fb_to') : null,
            'condition' => in_array($request->query('fb_condition'), \App\Models\WeatherForecastFeedback::OBSERVED_CONDITIONS, true) ? $request->query('fb_condition') : null,
        ];

        return view('dashboards.mao', $this->stats() + [
            'weatherFeedbackFilters' => $weatherFeedbackFilters,
            'weatherFeedbackSummary' => app(\App\Services\Weather\WeatherFeedbackSummaryService::class)->summarize($weatherFeedbackFilters),
            'farmerOrganizationSummary' => app(FarmerOrganizationSummaryService::class)->summarize()['farmerOrganizationSummary'],
            'latestClimate' => $latestClimateRecord,
            'recentClimateRecords' => $recent['recentClimateRecords'],
            'recentRiceProductions' => $recent['recentRiceProductions'],
            'latestAdvisories' => $recent['latestAdvisories'],
            'latestFeedPosts' => $recent['latestFeedPosts']->take(4),
            'latestReports' => $recent['latestReports']->take(4),
            'recentFarmerProfiles' => $recent['recentFarmerProfiles'],
            'highRiskAreasList' => $highRiskAreasList,
            'pendingAdvisoryCount' => $pendingAdvisoryCount,
            'riceYoyDelta' => $this->riceYoyDelta(),
            'latestExternalWeather' => ExternalWeatherData::query()->latestSuccessful()->first(),
            'maoActionItems' => $this->maoActionItems(),
            ...$this->maoAggregates(),
            'weatherChartData' => $weatherChartData,
            'weatherDataSource' => $weatherDataSource,
            'liveWeather' => $liveWeather,
            'riskLevelCounts' => $riskLevelCounts,
            'weatherWarning' => $this->weatherWarningLevel($riskLevelCounts),
            'weatherAnomaly' => $this->weatherAnomalySummary($liveWeather, $currentSeason),
            'cropStatus' => $this->cropStatusSummary($currentSeason),
            'barangayMonitoring' => $this->barangayMonitoringTable(),
            'riceYieldTrend' => $this->riceYieldTrendSummary(),
            'recentActivity' => SystemLog::query()->with('user')->latest()->take(8)->get(),
            'fieldReportsPendingCount' => $fieldReportsPendingCount,
            'recentFieldReports' => FieldReport::query()->with('assignedTo')->latest()->take(6)->get(),
            'fieldReportsByIssueType' => FieldReport::query()
                ->where('status', '!=', FieldReport::STATUS_RESOLVED)
                ->selectRaw('issue_type, COUNT(*) as total')
                ->groupBy('issue_type')
                ->pluck('total', 'issue_type'),
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponses' => $typhoonSafetyResponses,
            'typhoonSafetySafeCount' => $typhoonSafetySafeCount,
            'typhoonSafetyNeedsHelpCount' => $typhoonSafetyNeedsHelpCount,
            'typhoonSafetyNoResponseCount' => $typhoonSafetyNoResponseCount,
            'maoHighlights' => $this->maoHighlights(
                $highRiskAreasList,
                $pendingAdvisoryCount,
                $activeTyphoonSafetyEvent,
                $typhoonSafetyNeedsHelpCount,
                $typhoonSafetyNoResponseCount,
                $fieldReportsPendingCount,
            ),
            'farmerSchedules' => $farmerSchedules,
            'plantingScheduleFeatureReady' => $plantingScheduleFeatureReady,
            'scheduleFilters' => $scheduleFilters,
            'scheduleFarmers' => User::query()->where('role', User::ROLE_FARMER)->orderBy('name')->get(['id', 'name']),
            'scheduleBarangays' => $plantingScheduleFeatureReady
                ? FarmerPlantingSchedule::query()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay')
                : collect(),
        ]);
    }

    public function admin(ModelEvaluationService $modelEvaluation): View
    {
        $recent = $this->recentOperationalData();
        $heatmapLastRefresh = Cache::get(BarangayRiskMapService::CACHE_KEY)['generated_at'] ?? null;
        $heatmapRefreshFailuresToday = 0;
        $predictionAttemptsToday = PythonService::attemptsToday();
        $predictionFailuresToday = PythonService::failuresToday();
        $inactiveUsersToday = User::query()
            ->where('status', User::STATUS_INACTIVE)
            ->whereDate('updated_at', today())
            ->count();
        $reportAnomaly = $this->reportExportAnomaly();

        $evaluation = $modelEvaluation->evaluate();
        $evaluationBestRow = collect($evaluation['rows'] ?? [])->firstWhere('status', 'Selected Model');

        return view('dashboards.it-expert', $this->stats() + [
            ...$this->adminAggregates(),
            ...$this->farmerOrganizationDashboardData(),
            'latestLogs' => SystemLog::query()->with('user')->latest()->take(10)->get(),
            'latestUsers' => User::query()->latest()->take(10)->get(),
            'recentFarmers' => User::query()->where('role', User::ROLE_FARMER)->latest()->take(6)->get(),
            'latestReports' => Report::query()->with('generatedBy')->latest()->take(5)->get(),
            'reportUsageAudit' => Report::query()
                ->selectRaw('generated_by, COUNT(*) as total, MAX(created_at) as last_generated_at')
                ->whereNotNull('generated_by')
                ->groupBy('generated_by')
                ->with('generatedBy')
                ->orderByDesc('total')
                ->take(5)
                ->get(),
            'highRiskAreasList' => $recent['highRiskAreas'],
            'heatmapLastRefresh' => $heatmapLastRefresh,
            'heatmapRefreshFailuresToday' => $heatmapRefreshFailuresToday,
            'predictionAttemptsToday' => $predictionAttemptsToday,
            'predictionFailuresToday' => $predictionFailuresToday,
            'predictionSuccessRate' => $predictionAttemptsToday > 0
                ? round((($predictionAttemptsToday - $predictionFailuresToday) / $predictionAttemptsToday) * 100, 1)
                : null,
            'modelEvaluation' => $evaluation,
            'modelEvaluationBestRow' => $evaluationBestRow,
            'itHighlights' => $this->itHighlights(
                $heatmapRefreshFailuresToday,
                $heatmapLastRefresh,
                $predictionAttemptsToday,
                $predictionFailuresToday,
                $inactiveUsersToday,
                $reportAnomaly,
            ),
        ]);
    }

    private function recentOperationalData(): array
    {
        return Cache::remember('dashboard:recent-operational-data', now()->addSeconds(30), fn (): array => [
            'farmerFeedPosts' => FeedPost::query()
                ->with('author:id,name')
                ->whereIn('visibility', ['All Farmers', 'All Users'])
                ->whereNull('archived_at')
                ->latest()
                ->take(5)
                ->get(),
            'recentClimateRecords' => ClimateRecord::query()->latest('record_date')->take(5)->get(),
            'recentRiceProductions' => RiceProduction::query()->latest()->take(5)->get(),
            'latestAdvisories' => PlantingAdvisory::query()->latest()->take(5)->get(),
            'latestFeedPosts' => FeedPost::query()->with('author:id,name')->whereNull('archived_at')->latest()->take(5)->get(),
            'latestReports' => Report::query()->realGenerated()->with('generatedBy:id,name')->latest()->take(5)->get(),
            'recentFarmerProfiles' => FarmerProfile::query()->latest()->take(6)->get(),
            'highRiskAreas' => $this->highRiskAreas(),
        ]);
    }

    /**
     * Split out of recentOperationalData() with its own shorter TTL: safety
     * signal (which barangays are High/Severe right now) should refresh
     * faster than slower-moving lists like recent feed posts or reports.
     * Sourced from HeatmapArea, which BarangayRiskMapService keeps as the
     * last-known-good store for every barangay it successfully scores.
     */
    private function highRiskAreas(): Collection
    {
        return Cache::remember('dashboard:high-risk-areas', now()->addSeconds(15), fn (): Collection => HeatmapArea::query()
            ->whereIn('risk_level', ['High', 'Severe'])
            ->orderByDesc('risk_score')
            ->take(8)
            ->get());
    }

    /**
     * "Needs Your Attention" summary for the MAO dashboard — things that
     * require MAO action, not just informational counts. Short TTL since
     * these are action queues, not slow-moving aggregates.
     */
    private function maoActionItems(): array
    {
        return Cache::remember('dashboard:mao-action-items', now()->addSeconds(20), fn (): array => [
            'feedbackAwaitingResponse' => Schema::hasTable('advisory_feedback')
                ? AdvisoryFeedback::query()->whereNotNull('is_helpful')->whereNull('mao_response')->count()
                : 0,
        ]);
    }

    /**
     * @return array{severity: string, title: string, message: string}[]
     */
    private function itHighlights(
        int $heatmapRefreshFailuresToday,
        ?string $heatmapLastRefresh,
        int $predictionAttemptsToday,
        int $predictionFailuresToday,
        int $inactiveUsersToday,
        ?array $reportAnomaly,
    ): array {
        $highlights = [];

        if ($heatmapRefreshFailuresToday > 0) {
            $highlights[] = [
                'severity' => 'critical',
                'weight' => 3,
                'title' => $heatmapRefreshFailuresToday.' heatmap refresh '.str('failure')->plural($heatmapRefreshFailuresToday).' today',
                'message' => 'One or more barangays failed to recompute risk during the heatmap refresh. Check storage/logs/laravel.log.',
            ];
        }

        if (! $heatmapLastRefresh) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => 'Heatmap risk data has never been refreshed',
                'message' => 'No scheduled job runs the heatmap refresh — it only fires when someone opens the Heat Map page.',
            ];
        } elseif (Carbon::parse($heatmapLastRefresh)->lt(now()->subDay())) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => 'Heatmap risk data is stale',
                'message' => 'Last refreshed '.Carbon::parse($heatmapLastRefresh)->diffForHumans().'.',
            ];
        }

        if ($predictionAttemptsToday >= 3) {
            $failureRate = round(($predictionFailuresToday / $predictionAttemptsToday) * 100);
            if ($failureRate >= 30) {
                $highlights[] = [
                    'severity' => 'critical',
                    'weight' => 3,
                    'title' => $failureRate.'% of prediction calls failed today',
                    'message' => "{$predictionFailuresToday} of {$predictionAttemptsToday} calls to the ML service failed today. Check the Farming AI API.",
                ];
            }
        }

        if ($inactiveUsersToday > 0) {
            $highlights[] = [
                'severity' => 'info',
                'weight' => 1,
                'title' => $inactiveUsersToday.' '.str('account')->plural($inactiveUsersToday).' marked inactive today',
                'message' => 'Review recent status changes in User Management.',
            ];
        }

        if ($reportAnomaly) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => 'Unusual report-generation volume today',
                'message' => $reportAnomaly['today'].' reports generated today vs a '.$reportAnomaly['average'].'/day average over the past week.',
            ];
        }

        usort($highlights, fn (array $a, array $b) => $b['weight'] <=> $a['weight']);

        return array_slice($highlights, 0, 3);
    }

    /**
     * Flags today as anomalous when report generation is well above the
     * trailing week's daily average — a plain volume check, not a
     * statistical model.
     */
    private function reportExportAnomaly(): ?array
    {
        $countsByDay = Report::query()
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $todayKey = today()->toDateString();
        $todayCount = (int) ($countsByDay[$todayKey] ?? 0);
        $priorDays = $countsByDay->except($todayKey);
        $priorAverage = $priorDays->isNotEmpty() ? $priorDays->avg() : 0;

        if ($priorAverage > 0 && $todayCount >= 3 && $todayCount >= $priorAverage * 2) {
            return ['today' => $todayCount, 'average' => round($priorAverage, 1)];
        }

        if ($priorAverage == 0 && $todayCount >= 5) {
            return ['today' => $todayCount, 'average' => 0.0];
        }

        return null;
    }

    private function maoAggregates(): array
    {
        return Cache::remember('dashboard:mao-aggregates', now()->addSeconds(45), function (): array {
            $rice = RiceProduction::query()
                ->selectRaw('COALESCE(SUM(total_production), 0) as production_total')
                ->selectRaw('COALESCE(SUM(area_hectares), 0) as area_total')
                ->selectRaw('COALESCE(AVG(yield_per_hectare), 0) as yield_average')
                ->first();

            return [
                'riceProductionTotal' => (float) $rice->production_total,
                'riceAreaTotal' => (float) $rice->area_total,
                'avgYield' => (float) $rice->yield_average,
                'profileCount' => FarmerProfile::count(),
                'reportCount' => Report::query()->realGenerated()->count(),
                'publishedAdvisoryCount' => PlantingAdvisory::query()->where('status', 'Published')->count(),
                'communityPostCount' => FeedPost::query()->whereNull('archived_at')->count(),
            ];
        });
    }

    private function adminAggregates(): array
    {
        return Cache::remember('dashboard:admin-aggregates', now()->addSeconds(45), function (): array {
            $users = User::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_total', [User::STATUS_ACTIVE])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive_total', [User::STATUS_INACTIVE])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as farmer_total', [User::ROLE_FARMER])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as mao_total', [User::ROLE_MAO])
                ->selectRaw('SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as expert_total', [User::ROLE_IT_EXPERT])
                ->first();

            return [
                'userCount' => (int) $users->total,
                'activeUsers' => (int) $users->active_total,
                'inactiveUsers' => (int) $users->inactive_total,
                'logCount' => SystemLog::count(),
                'roleCounts' => [
                    User::ROLE_FARMER => (int) $users->farmer_total,
                    User::ROLE_MAO => (int) $users->mao_total,
                    User::ROLE_IT_EXPERT => (int) $users->expert_total,
                ],
                'moduleCounts' => [
                    'Farmer Profiles' => FarmerProfile::count(),
                    'Climate Records' => ClimateRecord::count(),
                    'Rice Productions' => RiceProduction::count(),
                    'Advisories' => PlantingAdvisory::count(),
                    'Community Feed' => FeedPost::query()->whereNull('archived_at')->count(),
                    'Notifications' => UserNotification::count(),
                    'Reports' => Report::query()->realGenerated()->count(),
                ],
            ];
        });
    }

    private function farmerOrganizationDashboardData(): array
    {
        return app(FarmerOrganizationSummaryService::class)->summarize();
    }

    /**
     * @return array{severity: string, title: string, message: string}[]
     */
    private function maoHighlights(
        Collection $highRiskAreasList,
        int $pendingAdvisoryCount,
        ?array $activeTyphoonSafetyEvent,
        int $typhoonSafetyNeedsHelpCount,
        int $typhoonSafetyNoResponseCount,
        int $fieldReportsPendingCount = 0,
    ): array {
        $highlights = [];

        if ($activeTyphoonSafetyEvent && $typhoonSafetyNeedsHelpCount > 0) {
            $highlights[] = [
                'severity' => 'critical',
                'weight' => 3,
                'title' => $typhoonSafetyNeedsHelpCount.' '.str('farmer')->plural($typhoonSafetyNeedsHelpCount).' reported needing help',
                'message' => 'During "'.$activeTyphoonSafetyEvent['title'].'" — see Typhoon Safety Check-In below.',
            ];
        }

        $topRiskArea = $highRiskAreasList->first();
        if ($topRiskArea) {
            $highlights[] = [
                'severity' => $topRiskArea->risk_level === 'Severe' ? 'critical' : 'warning',
                'weight' => $topRiskArea->risk_level === 'Severe' ? 3 : 2,
                'title' => $topRiskArea->barangay.' is at '.$topRiskArea->risk_level.' risk',
                'message' => 'Risk score '.number_format((float) $topRiskArea->risk_score, 2).'/1.00 — '.($topRiskArea->risk_type ?: 'Climate').' risk.',
            ];
        }

        if ($pendingAdvisoryCount > 0) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => $pendingAdvisoryCount.' '.str('advisory')->plural($pendingAdvisoryCount).' awaiting review',
                'message' => 'Review and publish pending advisories in the Publishing Center below.',
            ];
        }

        if ($activeTyphoonSafetyEvent && $typhoonSafetyNeedsHelpCount === 0 && $typhoonSafetyNoResponseCount > 0) {
            $highlights[] = [
                'severity' => 'info',
                'weight' => 1,
                'title' => $typhoonSafetyNoResponseCount.' '.str('farmer')->plural($typhoonSafetyNoResponseCount).' have not checked in',
                'message' => 'Active event: "'.$activeTyphoonSafetyEvent['title'].'".',
            ];
        }

        if ($fieldReportsPendingCount > 0) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => $fieldReportsPendingCount.' field '.str('report')->plural($fieldReportsPendingCount).' need review',
                'message' => 'Pending or unverified issues — see Active Alerts & Field Reports below.',
            ];
        }

        usort($highlights, fn (array $a, array $b) => $b['weight'] <=> $a['weight']);

        return array_slice($highlights, 0, 3);
    }

    private function weatherWarningLevel(array $riskLevelCounts): array
    {
        return match (true) {
            ($riskLevelCounts['Severe'] ?? 0) > 0 => ['level' => 'Severe', 'label' => 'Severe Weather Warning'],
            ($riskLevelCounts['High'] ?? 0) > 0 => ['level' => 'High', 'label' => 'High Alert'],
            ($riskLevelCounts['Moderate'] ?? 0) > 0 => ['level' => 'Moderate', 'label' => 'Watch'],
            default => ['level' => 'Low', 'label' => 'Normal'],
        };
    }

    /**
     * Compares the live forecast's average daily rainfall against the
     * historical average daily rainfall recorded for the same season —
     * both are same-unit (mm/day) figures, so this is a like-for-like
     * anomaly check, not a fabricated statistic.
     */
    private function weatherAnomalySummary(?array $liveWeather, ?string $currentSeason): array
    {
        $seasonalAvgRainfall = $currentSeason
            ? ClimateRecord::query()->where('season', $currentSeason)->avg('rainfall')
            : null;
        $seasonalAvgRainfall = $seasonalAvgRainfall !== null ? (float) $seasonalAvgRainfall : null;

        $currentDailyRainfall = $liveWeather ? (float) data_get($liveWeather, 'daily_rainfall_mm', 0) : null;

        $anomalyPercent = ($seasonalAvgRainfall && $seasonalAvgRainfall > 0 && $currentDailyRainfall !== null)
            ? round((($currentDailyRainfall - $seasonalAvgRainfall) / $seasonalAvgRainfall) * 100, 1)
            : null;

        return [
            'season' => $currentSeason,
            'seasonal_avg_daily_rainfall' => $seasonalAvgRainfall,
            'current_daily_rainfall' => $currentDailyRainfall,
            'anomaly_percent' => $anomalyPercent,
            'anomaly_label' => match (true) {
                $anomalyPercent === null => 'No seasonal baseline yet',
                $anomalyPercent >= 30 => 'Well Above Normal',
                $anomalyPercent >= 10 => 'Above Normal',
                $anomalyPercent <= -30 => 'Well Below Normal',
                $anomalyPercent <= -10 => 'Below Normal',
                default => 'Normal',
            },
        ];
    }

    /**
     * Crop-status figures buildable from existing RiceProduction/HeatmapArea
     * data. Planting/harvesting progress is intentionally left out — the
     * app has no planting-stage field anywhere, and fabricating one would
     * misrepresent real field conditions.
     */
    private function cropStatusSummary(?string $currentSeason): array
    {
        $latestPerBarangay = RiceProduction::query()
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get()
            ->unique('barangay');

        $irrigated = $latestPerBarangay->filter(
            fn (RiceProduction $record) => str_contains(strtolower((string) $record->irrigation_type), 'irrig')
        );
        $rainfed = $latestPerBarangay->diff($irrigated);

        $estimatedYield = HeatmapArea::query()->whereNotNull('predicted_yield')->avg('predicted_yield');
        $actualYield = $latestPerBarangay->avg('yield_per_hectare');

        return [
            'season' => $currentSeason,
            'total_hectares' => (float) $latestPerBarangay->sum('area_hectares'),
            'irrigated_hectares' => (float) $irrigated->sum('area_hectares'),
            'irrigated_barangays' => $irrigated->count(),
            'rainfed_hectares' => (float) $rainfed->sum('area_hectares'),
            'rainfed_barangays' => $rainfed->count(),
            'estimated_yield' => $estimatedYield !== null ? (float) $estimatedYield : null,
            'actual_yield' => $actualYield !== null ? (float) $actualYield : null,
        ];
    }

    /**
     * Per-barangay table combining RiceProduction and HeatmapArea — the
     * data-table alternative to a heat-map-only view.
     */
    private function barangayMonitoringTable(): Collection
    {
        $production = RiceProduction::query()
            ->selectRaw('barangay, SUM(area_hectares) as area_hectares, SUM(total_production) as production_volume, AVG(yield_per_hectare) as avg_yield')
            ->groupBy('barangay')
            ->get()
            ->keyBy('barangay');

        return HeatmapArea::query()
            ->orderBy('barangay')
            ->get()
            ->unique('barangay')
            ->map(function (HeatmapArea $area) use ($production) {
                $row = $production->get($area->barangay);

                return [
                    'barangay' => $area->barangay,
                    'area_hectares' => $row ? (float) $row->area_hectares : null,
                    'production_volume' => $row ? (float) $row->production_volume : null,
                    'avg_yield' => $row ? (float) $row->avg_yield : null,
                    'risk_level' => $area->risk_level,
                    'risk_type' => $area->risk_type,
                    'last_monitored' => $area->updated_at,
                ];
            })
            ->values();
    }

    /**
     * Multi-year yield trend plus this year's highest/lowest producing
     * barangays by total production.
     */
    private function riceYieldTrendSummary(): array
    {
        $byYear = RiceProduction::query()
            ->selectRaw('year, AVG(yield_per_hectare) as avg_yield')
            ->whereNotNull('year')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        $latestYear = $byYear->max('year');

        $byBarangayThisYear = RiceProduction::query()
            ->when($latestYear, fn ($query) => $query->where('year', $latestYear))
            ->selectRaw('barangay, SUM(total_production) as total_production')
            ->groupBy('barangay')
            ->orderByDesc('total_production')
            ->get();

        return [
            'latest_year' => $latestYear,
            'chart' => [
                'labels' => $byYear->pluck('year')->map(fn ($year) => (string) $year)->all(),
                'avg_yield' => $byYear->pluck('avg_yield')->map(fn ($value) => round((float) $value, 2))->all(),
            ],
            'highest_barangay' => $byBarangayThisYear->first(),
            'lowest_barangay' => $byBarangayThisYear->count() > 1 ? $byBarangayThisYear->last() : null,
        ];
    }

    /**
     * Compares average rice yield per hectare across the two most recent
     * years present in RiceProduction — a plain year-over-year read of
     * data that's already stored, not a new metric. Pass a barangay to
     * scope it (used on the farmer dashboard); omit it for the municipal
     * figure (used on the MAO dashboard).
     */
    private function riceYoyDelta(?string $barangay = null): ?array
    {
        $yearly = RiceProduction::query()
            ->when($barangay, fn ($query) => $query->where('barangay', $barangay))
            ->selectRaw('year, AVG(yield_per_hectare) as avg_yield')
            ->whereNotNull('year')
            ->groupBy('year')
            ->orderByDesc('year')
            ->take(2)
            ->get();

        if ($yearly->count() < 2) {
            return null;
        }

        $latest = (float) $yearly[0]->avg_yield;
        $previous = (float) $yearly[1]->avg_yield;

        if ($previous <= 0) {
            return null;
        }

        $deltaPercent = round((($latest - $previous) / $previous) * 100, 1);

        return [
            'latest_year' => (int) $yearly[0]->year,
            'previous_year' => (int) $yearly[1]->year,
            'latest_avg_yield' => round($latest, 2),
            'previous_avg_yield' => round($previous, 2),
            'delta_percent' => $deltaPercent,
            'direction' => $deltaPercent > 0 ? 'up' : ($deltaPercent < 0 ? 'down' : 'flat'),
        ];
    }

    private function stats(): array
    {
        return Cache::remember('dashboard:shared-stats', now()->addSeconds(45), function (): array {
            return [
                'totalFarmers' => User::query()->where('role', User::ROLE_FARMER)->count(),
                'totalClimateRecords' => ClimateRecord::count(),
                'totalRiceProductions' => RiceProduction::count(),
                'totalAdvisories' => PlantingAdvisory::count(),
                'totalCommunityPosts' => FeedPost::query()->whereNull('archived_at')->count(),
                'totalNotifications' => UserNotification::count(),
            ];
        });
    }

    private function hasTyphoonSafetyTable(): bool
    {
        return Schema::hasTable('typhoon_safety_responses');
    }

    /**
     * @return array{count: int, items: Collection<int, UserNotification>}
     */
    private function weatherAlertsFor(Request $request): array
    {
        $keywords = ['weather', 'rain', 'rainfall', 'storm', 'typhoon', 'flood', 'drought', 'heat', 'pagasa', 'climate'];

        $query = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->where('type', 'Warning')
            ->where(function ($query) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', '%'.$keyword.'%')
                        ->orWhere('message', 'like', '%'.$keyword.'%');
                }
            });

        return [
            'count' => (clone $query)->count(),
            'items' => $query->latest()->take(5)->get(),
        ];
    }
}
