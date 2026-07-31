<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
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
use App\Services\TyphoonSafetyService;
use App\Services\Weather\OpenMeteoService;
use App\Services\WeatherApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function farmer(Request $request, OpenMeteoService $openMeteo, TyphoonSafetyService $typhoonSafety): View
    {
        $weatherTimezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');
        $today = now($weatherTimezone)->toDateString();
        if (! $request->boolean('cached_weather')) {
            $forecastResult = $openMeteo->fetchForecast(true);
        } else {
            $storedForecast = $openMeteo->latestStoredForecast();
            $forecastResult = [
                'ok' => true,
                'source' => 'Open-Meteo',
                'freshness' => $storedForecast->first()?->freshnessLabel() ?? 'unavailable',
                'records' => $storedForecast,
                'records_saved' => 0,
                'fetched_at' => $storedForecast->first()?->fetched_at,
                'message' => 'Using the latest stored Open-Meteo weather update for faster dashboard loading.',
                'cached_dashboard_load' => true,
            ];
        }
        $forecastRecords = collect($forecastResult['records'] ?? [])->sortBy('forecast_date')->values();
        $latestForecast = $forecastRecords
            ->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
            ?? $forecastRecords->first();

        if (! $latestForecast || empty(data_get($latestForecast->raw_response, 'current'))) {
            $forecastResult = $openMeteo->fetchForecast(true);
            $forecastRecords = collect($forecastResult['records'] ?? [])->sortBy('forecast_date')->values();
            $latestForecast = $forecastRecords
                ->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
                ?? $forecastRecords->first();
        }

        $dashboardData = $this->stats();
        $dashboardData['highRiskHeatMapAreas'] = HeatmapArea::query()->whereIn('risk_level', ['High', 'Severe'])->count();
        $activeTyphoonSafetyEvent = $typhoonSafety->activeEvent();

        return view('dashboards.farmer', $dashboardData + [
            'feedPosts' => FeedPost::query()
                ->with('author')
                ->whereIn('visibility', ['All Farmers', 'All Users'])
                ->whereNull('archived_at')
                ->latest()
                ->take(5)
                ->get(),
            'advisories' => PlantingAdvisory::query()->where('status', 'Published')->latest()->take(5)->get(),
            'notifications' => UserNotification::query()->where('user_id', $request->user()->id)->latest()->take(5)->get(),
            'unreadNotificationCount' => UserNotification::query()->where('user_id', $request->user()->id)->where('is_read', false)->count(),
            'climateSummary' => ClimateRecord::query()->latest('record_date')->first(),
            'recentClimateRecords' => ClimateRecord::query()->latest('record_date')->take(5)->get(),
            'latestForecast' => $latestForecast,
            'forecastResult' => $forecastResult,
            'highRiskAreasList' => HeatmapArea::query()
                ->whereIn('risk_level', ['High', 'Severe'])
                ->orderByDesc('risk_score')
                ->take(8)
                ->get(),
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponse' => $this->hasTyphoonSafetyTable() && $activeTyphoonSafetyEvent
                ? TyphoonSafetyResponse::query()
                    ->where('user_id', $request->user()->id)
                    ->where('event_key', $activeTyphoonSafetyEvent['key'])
                    ->first()
                : null,
        ]);
    }

    public function mao(WeatherApiService $weatherApi, TyphoonSafetyService $typhoonSafety): View
    {
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

        $liveWeather = $weatherApi->forecast(refresh: true);
        $storedWeatherChartData = [
            'labels' => $climateChartRecords->map(fn (ClimateRecord $record) => $record->record_date?->format('M d') ?? 'N/A')->all(),
            'rainfall' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->rainfall)->all(),
            'temperature' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->temperature)->all(),
            'humidity' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->humidity)->all(),
            'windSpeed' => $climateChartRecords->map(fn (ClimateRecord $record) => (float) $record->wind_speed)->all(),
        ];
        $weatherChartData = data_get($liveWeather, 'daily_series') ?: $storedWeatherChartData;
        $weatherDataSource = $liveWeather
            ? 'Live '.data_get($liveWeather, 'source', 'OpenWeather').' forecast for '.data_get($liveWeather, 'location', 'Lian, Batangas')
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

        return view('dashboards.mao', $this->stats() + [
            'latestClimate' => ClimateRecord::query()->latest('record_date')->first(),
            'recentClimateRecords' => ClimateRecord::query()->latest('record_date')->take(5)->get(),
            'recentRiceProductions' => RiceProduction::query()->latest()->take(5)->get(),
            'latestAdvisories' => PlantingAdvisory::query()->latest()->take(5)->get(),
            'latestFeedPosts' => FeedPost::query()->with('author')->whereNull('archived_at')->latest()->take(4)->get(),
            'latestHeatmapAreas' => HeatmapArea::query()->latest()->take(5)->get(),
            'latestReports' => Report::query()->with('generatedBy')->latest()->take(4)->get(),
            'recentFarmerProfiles' => FarmerProfile::query()->latest()->take(6)->get(),
            'highRiskAreasList' => HeatmapArea::query()
                ->whereIn('risk_level', ['High', 'Severe'])
                ->orderByDesc('risk_score')
                ->take(8)
                ->get(),
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
            ])->values(),
        ]);
    }

    public function admin(): View
    {
        return view('dashboards.it-expert', $this->stats() + [
            ...$this->adminAggregates(),
            'latestLogs' => SystemLog::query()->with('user')->latest()->take(6)->get(),
            'latestUsers' => User::query()->latest()->take(5)->get(),
            'recentFarmers' => User::query()->where('role', User::ROLE_FARMER)->latest()->take(6)->get(),
            'latestReports' => Report::query()->with('generatedBy')->latest()->take(5)->get(),
            'highRiskAreasList' => HeatmapArea::query()
                ->whereIn('risk_level', ['High', 'Severe'])
                ->orderByDesc('risk_score')
                ->take(8)
                ->get(),
        ]);
    }

    private function maoAggregates(): array
    {
        return Cache::remember('dashboard:mao-aggregates', now()->addSeconds(45), fn () => [
            'riceProductionTotal' => RiceProduction::query()->sum('total_production'),
            'riceAreaTotal' => RiceProduction::query()->sum('area_hectares'),
            'avgYield' => RiceProduction::query()->avg('yield_per_hectare') ?: 0,
            'profileCount' => FarmerProfile::count(),
            'reportCount' => Report::count(),
            'heatMapCount' => HeatmapArea::count(),
            'publishedAdvisoryCount' => PlantingAdvisory::query()->where('status', 'Published')->count(),
            'communityPostCount' => FeedPost::query()->whereNull('archived_at')->count(),
        ]);
    }

    private function adminAggregates(): array
    {
        return Cache::remember('dashboard:admin-aggregates', now()->addSeconds(45), fn () => [
            'userCount' => User::count(),
            'activeUsers' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'inactiveUsers' => User::query()->where('status', User::STATUS_INACTIVE)->count(),
            'logCount' => SystemLog::count(),
            'roleCounts' => [
                User::ROLE_FARMER => User::query()->where('role', User::ROLE_FARMER)->count(),
                User::ROLE_MAO => User::query()->where('role', User::ROLE_MAO)->count(),
                User::ROLE_IT_EXPERT => User::query()->where('role', User::ROLE_IT_EXPERT)->count(),
            ],
            'moduleCounts' => [
                'Farmer Profiles' => FarmerProfile::count(),
                'Climate Records' => ClimateRecord::count(),
                'Rice Productions' => RiceProduction::count(),
                'Advisories' => PlantingAdvisory::count(),
                'Community Feed' => FeedPost::query()->whereNull('archived_at')->count(),
                'Notifications' => UserNotification::count(),
                'Heat Map Areas' => HeatmapArea::count(),
                'Reports' => Report::count(),
            ],
        ]);
    }

    private function stats(): array
    {
        return Cache::remember('dashboard:shared-stats', now()->addSeconds(45), fn () => [
            'totalFarmers' => User::query()->where('role', User::ROLE_FARMER)->count(),
            'totalClimateRecords' => ClimateRecord::count(),
            'totalRiceProductions' => RiceProduction::count(),
            'totalAdvisories' => PlantingAdvisory::count(),
            'totalCommunityPosts' => FeedPost::query()->whereNull('archived_at')->count(),
            'totalNotifications' => UserNotification::count(),
            'totalHeatMapAreas' => HeatmapArea::count(),
            'highRiskHeatMapAreas' => HeatmapArea::query()->whereIn('risk_level', ['High', 'Severe'])->count(),
        ]);
    }

    private function hasTyphoonSafetyTable(): bool
    {
        return Schema::hasTable('typhoon_safety_responses');
    }
}
