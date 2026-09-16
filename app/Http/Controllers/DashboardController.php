<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryFeedback;
use App\Models\ClimateRecord;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\HeatmapRiskService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\WeatherApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function farmer(Request $request, FarmerDashboardWeatherService $weather, TyphoonSafetyService $typhoonSafety): View
    {
        $dashboardWeather = $weather->current($request->boolean('refresh_weather'));
        $dashboardData = $this->stats();
        $recent = $this->recentOperationalData();
        $activeTyphoonSafetyEvent = $typhoonSafety->activeEvent();
        $weatherAlerts = $this->weatherAlertsFor($request);

        return view('dashboards.farmer', $dashboardData + [
            'feedPosts' => $recent['farmerFeedPosts'],
            'advisories' => $recent['publishedAdvisories'],
            'notifications' => UserNotification::query()->where('user_id', $request->user()->id)->latest()->take(5)->get(),
            'unreadNotificationCount' => UserNotification::query()->where('user_id', $request->user()->id)->where('is_read', false)->count(),
            'activeWeatherAlerts' => $weatherAlerts['count'],
            'weatherAlertNotifications' => $weatherAlerts['items'],
            'climateSummary' => $recent['recentClimateRecords']->first(),
            'recentClimateRecords' => $recent['recentClimateRecords'],
            'dashboardWeather' => $dashboardWeather,
            'dashboardWeatherResponse' => $weather->responsePayload($dashboardWeather),
            'weatherGuidance' => $weather->guidance($dashboardWeather),
            'highRiskAreasList' => $recent['highRiskAreas'],
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponse' => $this->hasTyphoonSafetyTable() && $activeTyphoonSafetyEvent
                ? TyphoonSafetyResponse::query()
                    ->where('user_id', $request->user()->id)
                    ->where('event_key', $activeTyphoonSafetyEvent['key'])
                    ->first()
                : null,
        ]);
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

        $heatmapAreas = HeatmapArea::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('barangay')
            ->get()
            ->unique('barangay')
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

        return view('dashboards.mao', $this->stats() + [
            'farmerOrganizationSummary' => app(FarmerOrganizationSummaryService::class)->summarize()['farmerOrganizationSummary'],
            'latestClimate' => $recent['recentClimateRecords']->first(),
            'recentClimateRecords' => $recent['recentClimateRecords'],
            'recentRiceProductions' => $recent['recentRiceProductions'],
            'latestAdvisories' => $recent['latestAdvisories'],
            'latestFeedPosts' => $recent['latestFeedPosts']->take(4),
            'latestHeatmapAreas' => $recent['latestHeatmapAreas'],
            'latestReports' => $recent['latestReports']->take(4),
            'recentFarmerProfiles' => $recent['recentFarmerProfiles'],
            'highRiskAreasList' => $recent['highRiskAreas'],
            'maoActionItems' => $this->maoActionItems(),
            ...$this->maoAggregates(),
            'weatherChartData' => $weatherChartData,
            'weatherDataSource' => $weatherDataSource,
            'liveWeather' => $liveWeather,
            'riskLevelCounts' => [
                'Low' => (clone $heatmapAreas)->where('risk_level', 'Low')->count(),
                'Moderate' => (clone $heatmapAreas)->where('risk_level', 'Moderate')->count(),
                'High' => (clone $heatmapAreas)->where('risk_level', 'High')->count(),
                'Severe' => (clone $heatmapAreas)->where('risk_level', 'Severe')->count(),
            ],
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponses' => $typhoonSafetyResponses,
            'typhoonSafetySafeCount' => $typhoonSafetyResponses->where('status', TyphoonSafetyResponse::STATUS_SAFE)->count(),
            'typhoonSafetyNeedsHelpCount' => $typhoonSafetyResponses->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->count(),
            'typhoonSafetyNoResponseCount' => max(0, $farmerCount - $typhoonSafetyResponses->pluck('user_id')->unique()->count()),
            'dashboardMapAreas' => $heatmapAreas->map(fn (HeatmapArea $area) => [
                'barangay' => $area->barangay,
                'latitude' => (float) $area->latitude,
                'longitude' => (float) $area->longitude,
                'risk_level' => $area->risk_level,
                'risk_score' => (float) $area->risk_score,
                'risk_type' => $area->risk_type,
                'predicted_yield' => $area->predicted_yield !== null ? (float) $area->predicted_yield : null,
                'predicted_yield_source' => str_contains(strtolower((string) $area->description), 'trained rice yield model') ? 'Trained rice yield model' : 'Stored production record',
                'rainfall_status' => $area->rainfall_status,
                'planting_advisory' => $area->planting_advisory,
                'irrigation_recommendation' => $area->irrigation_recommendation,
                'description' => $area->description,
                'updated_at' => $area->updated_at,
            ])->values(),
            'farmerSchedules' => $farmerSchedules,
            'plantingScheduleFeatureReady' => $plantingScheduleFeatureReady,
            'scheduleFilters' => $scheduleFilters,
            'scheduleFarmers' => User::query()->where('role', User::ROLE_FARMER)->orderBy('name')->get(['id', 'name']),
            'scheduleBarangays' => $plantingScheduleFeatureReady
                ? FarmerPlantingSchedule::query()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay')
                : collect(),
        ]);
    }

    public function admin(): View
    {
        $recent = $this->recentOperationalData();

        return view('dashboards.it-expert', $this->stats() + [
            ...$this->adminAggregates(),
            ...$this->farmerOrganizationDashboardData(),
            'latestLogs' => SystemLog::query()->with('user')->latest()->take(6)->get(),
            'latestUsers' => User::query()->latest()->take(5)->get(),
            'recentFarmers' => User::query()->where('role', User::ROLE_FARMER)->latest()->take(6)->get(),
            'latestReports' => $recent['latestReports'],
            'highRiskAreasList' => $recent['highRiskAreas'],
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
            'publishedAdvisories' => PlantingAdvisory::query()->where('status', 'Published')->latest()->take(5)->get(),
            'recentClimateRecords' => ClimateRecord::query()->latest('record_date')->take(5)->get(),
            'recentRiceProductions' => RiceProduction::query()->latest()->take(5)->get(),
            'latestAdvisories' => PlantingAdvisory::query()->latest()->take(5)->get(),
            'latestFeedPosts' => FeedPost::query()->with('author:id,name')->whereNull('archived_at')->latest()->take(5)->get(),
            'latestHeatmapAreas' => HeatmapArea::query()->latest()->take(5)->get(),
            'latestReports' => Report::query()->realGenerated()->with('generatedBy:id,name')->latest()->take(5)->get(),
            'recentFarmerProfiles' => FarmerProfile::query()->latest()->take(6)->get(),
            'highRiskAreas' => $this->highRiskAreas(),
        ]);
    }

    /**
     * Split out of recentOperationalData() with its own shorter TTL: safety
     * signal (which barangays are High/Severe right now) should refresh
     * faster than slower-moving lists like recent feed posts or reports.
     */
    private function highRiskAreas(): Collection
    {
        return Cache::remember('dashboard:high-risk-areas', now()->addSeconds(15), fn (): Collection => HeatmapArea::query()
            ->whereIn('risk_level', ['High', 'Severe'])
            ->orderByDesc('risk_score')
            ->take(8)
            ->get()
            ->each(function (HeatmapArea $area): void {
                $area->setAttribute('risk_breakdown', app(HeatmapRiskService::class)->riskBreakdownFor($area->barangay));
            }));
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
            'highRiskAreaCount' => HeatmapArea::query()->whereIn('risk_level', ['High', 'Severe'])->count(),
        ]);
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
                'heatMapCount' => HeatmapArea::count(),
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
                    'Heat Map Areas' => HeatmapArea::count(),
                    'Reports' => Report::query()->realGenerated()->count(),
                ],
            ];
        });
    }

    private function farmerOrganizationDashboardData(): array
    {
        return app(FarmerOrganizationSummaryService::class)->summarize();
    }

    private function stats(): array
    {
        return Cache::remember('dashboard:shared-stats', now()->addSeconds(45), function (): array {
            $heatmap = HeatmapArea::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN risk_level IN ('High', 'Severe') THEN 1 ELSE 0 END) as high_risk_total")
                ->first();

            return [
                'totalFarmers' => User::query()->where('role', User::ROLE_FARMER)->count(),
                'totalClimateRecords' => ClimateRecord::count(),
                'totalRiceProductions' => RiceProduction::count(),
                'totalAdvisories' => PlantingAdvisory::count(),
                'totalCommunityPosts' => FeedPost::query()->whereNull('archived_at')->count(),
                'totalNotifications' => UserNotification::count(),
                'totalHeatMapAreas' => (int) $heatmap->total,
                'highRiskHeatMapAreas' => (int) $heatmap->high_risk_total,
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
