<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryFeedback;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\MonthlyWeatherPrediction;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Services\Analytics\DashboardKpiService;
use App\Services\Analytics\DashboardOverviewService;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Analytics\ItDashboardInsights;
use App\Services\Analytics\ItDashboardService;
use App\Services\Analytics\MaoDashboardService;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\WeatherFeedbackSummaryService;
use App\Services\WeatherApiService;
use App\Support\BusinessClock;
use App\Support\DashboardData;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use App\Support\StaleCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    /** Per-farmer overview: short, because it carries live weather. */
    private const FARMER_OVERVIEW_CACHE_SECONDS = 60;

    /** Shared MAO / IT overviews: short, because they carry live weather and health checks. The warm-up job refreshes them every minute. */
    private const MAO_OVERVIEW_CACHE_SECONDS = 90;

    private const IT_PAGE_CACHE_SECONDS = 60;

    private const ADMIN_LATEST_KEY = 'dashboard:it:overview:2:latest';

    /** The IT overview shows when each section was built, so its last copy may be shown for up to an hour while it rebuilds. */
    private const IT_MAX_STALE_SECONDS = 3600;

    /** Sections the MAO refresh button re-renders (partials under dashboards/mao). */
    private const MAO_LIVE_SLOTS = ['kpis', 'attention', 'conditions', 'forecast', 'watch', 'coverage', 'production', 'feedback', 'activity'];

    /** Sections the IT refresh button re-renders (partials under dashboards/it). */
    private const IT_LIVE_SLOTS = ['summary', 'kpis', 'alerts', 'health', 'pipeline', 'security', 'roles', 'usage', 'ai', 'resources', 'integrity', 'ops', 'recent'];

    /** Tabs of the IT dashboard (?tab=...). */
    private const IT_TABS = ['overview', 'heatmap', 'security', 'users', 'resources', 'activity'];

    /** Sections the Farmer refresh button re-renders (partials under dashboards/farmer), in page order. */
    private const FARMER_LIVE_SLOTS = ['header', 'today', 'highlights', 'weather', 'schedule', 'risk', 'harvest', 'advisories', 'farm', 'updates', 'quick'];

    /**
     * A card's own "Try again" button: what to rebuild from its source, and which sections that changes.
     * Weather feeds the header, the "My Farm Today" numbers, the highlights and the weather card; the others stand alone.
     */
    private const FARMER_RETRY = [
        'weather' => ['refresh' => ['weather'], 'slots' => ['header', 'today', 'highlights', 'weather']],
        'today' => ['refresh' => ['weather'], 'slots' => ['header', 'today', 'highlights', 'weather']],
        'harvest' => ['refresh' => ['harvest'], 'slots' => ['harvest', 'today']],
        'risk' => ['refresh' => ['risk'], 'slots' => ['risk', 'today', 'highlights']],
        // After a dashboard pop-up saves: the sections that read what it changed. The profile and map ones leave the slow
        // harvest card out (the page asks for it separately when the profile changed).
        'planting' => ['refresh' => ['harvest'], 'slots' => ['today', 'highlights', 'schedule', 'harvest', 'farm', 'updates', 'quick']],
        'profile' => ['refresh' => ['risk'], 'slots' => ['header', 'today', 'highlights', 'risk', 'advisories', 'farm', 'updates', 'quick'], 'defer_harvest' => true],
        'map' => ['refresh' => [], 'slots' => ['farm', 'updates', 'quick'], 'defer_harvest' => true],
        'all' => ['refresh' => ['weather'], 'slots' => self::FARMER_LIVE_SLOTS],
    ];

    /** MAO overview: shared payload (cached briefly) plus what depends on who is signed in. */
    public function mao(Request $request, MaoDashboardService $mao): View
    {
        $payload = StaleCache::remember($this->maoOverviewCacheKey(), self::MAO_OVERVIEW_CACHE_SECONDS, fn (): array => $mao->payload());

        return view('dashboards.mao', ['mao' => $mao->forUser($payload, $request->user())]);
    }

    /** Refresh button: rebuilds the live sections (weather is re-fetched) and returns each section's HTML. */
    public function maoLive(Request $request, MaoDashboardService $mao): JsonResponse
    {
        $payload = $mao->payload(forceWeather: $request->boolean('refresh'));
        Cache::put($this->maoOverviewCacheKey(), $payload, now()->addSeconds(self::MAO_OVERVIEW_CACHE_SECONDS));
        $data = $mao->forUser($payload, $request->user());

        $slots = [];
        foreach (self::MAO_LIVE_SLOTS as $slot) {
            $slots[$slot] = view('dashboards.mao.'.$slot, ['mao' => $data])->render();
        }

        return response()->json(['slots' => $slots, 'refreshed_at' => $data['generated_at']->format('g:i A')]);
    }

    /**
     * Rebuilds the shared MAO and IT dashboard caches (run every minute by iclimate:warm-dashboards) so the next person
     * to open a dashboard does not wait for it.
     *
     * @return array<string, float> dashboard name => seconds it took
     */
    public function warmSharedDashboards(): array
    {
        $timings = [];

        $started = microtime(true);
        StaleCache::put($this->maoOverviewCacheKey(), self::MAO_OVERVIEW_CACHE_SECONDS, app(MaoDashboardService::class)->payload());
        $timings['MAO'] = round(microtime(true) - $started, 1);

        $started = microtime(true);
        StaleCache::put($this->adminCacheKey(), self::IT_PAGE_CACHE_SECONDS, app(ItDashboardService::class)->payload(), self::ADMIN_LATEST_KEY);
        $timings['IT'] = round(microtime(true) - $started, 1);

        return $timings;
    }

    private function maoOverviewCacheKey(): string
    {
        return 'dashboard:mao:overview:v'.DashboardData::version();
    }

    private function adminCacheKey(): string
    {
        return 'dashboard:it:overview:2:v'.DashboardData::version();
    }

    /**
     * Simple, farmer-friendly overview. Everything comes from FarmerDashboardService; each card handles its own failure.
     * The slow harvest model is not waited for: with no stored estimate the card is a placeholder the page fills in next.
     */
    public function farmer(Request $request, FarmerDashboardService $farmer): View
    {
        $user = $request->user();
        $key = FarmerDashboardService::cacheKey($user);
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            $payload = $farmer->payload($user, deferHarvest: true);
            // Kept even with a harvest placeholder: the page's follow-up request replaces it with the finished card (and this copy).
            Cache::put($key, $payload, now()->addSeconds(self::FARMER_OVERVIEW_CACHE_SECONDS));
        }

        return view('dashboards.farmer', ['f' => $payload]);
    }

    /** Shared barangay heat map available from every authenticated account. */
    public function heatMap(Request $request): View
    {
        $user = $request->user();
        $barangay = $user?->role === User::ROLE_FARMER
            ? $user->registeredBarangay()
            : null;

        return view('heat-map.index', [
            'highlightBarangay' => $barangay ? LianBarangays::canonicalName($barangay) : null,
            // Farmers get the short, plain-language forecast panel; MAO/IT keep the detailed one.
            'forecastAudience' => $user?->role === User::ROLE_FARMER ? 'farmer' : 'staff',
        ]);
    }

    /**
     * Refresh button and each card's "Try again": rebuilds the live sections (weather is re-fetched) and returns each
     * section's HTML. ?retry=<card> rebuilds only that card's source and the sections that depend on it.
     */
    public function farmerLive(Request $request, FarmerDashboardService $farmer): JsonResponse
    {
        $user = $request->user();
        $retry = self::FARMER_RETRY[$request->query('retry')] ?? null;
        $forceAll = $request->boolean('refresh');

        $payload = $farmer->payload(
            $user,
            forceWeather: $forceAll,
            refresh: $retry['refresh'] ?? [],
            // The page's own timed refresh must not stall on the harvest model; an explicit refresh or retry may.
            deferHarvest: ! $forceAll && ($retry === null || ($retry['defer_harvest'] ?? false)),
        );
        // A timed refresh that deferred the harvest must not overwrite a finished card in the stored copy with a placeholder.
        if (($payload['harvest']['state'] ?? null) !== 'loading') {
            Cache::put(FarmerDashboardService::cacheKey($user), $payload, now()->addSeconds(self::FARMER_OVERVIEW_CACHE_SECONDS));
        }

        $slots = [];
        foreach ($retry['slots'] ?? self::FARMER_LIVE_SLOTS as $slot) {
            // A timed refresh leaves a finished harvest card alone, and a placeholder is never pushed over it.
            if ($slot === 'harvest' && ($payload['harvest']['state'] ?? null) === 'loading') {
                continue;
            }
            $slots[$slot] = view('dashboards.farmer.'.$slot, ['f' => $payload])->render();
        }

        return response()->json(['slots' => $slots, 'updated' => $payload['generated_at']->format('g:i A')]);
    }

    /** The dashboard's language selector: saves English or Filipino for this farmer, then the page shows it. */
    public function farmerLanguage(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', Rule::in(array_keys(FarmerLanguage::SUPPORTED))],
        ]);
        FarmerLanguage::store($request, $request->user(), $validated['language']);

        return $request->expectsJson()
            ? response()->json(['language' => $validated['language']])
            : redirect()->route('farmer.dashboard');
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

    public function maoWeather(Request $request, WeatherApiService $weatherApi, FarmerDashboardWeatherService $sharedWeather): JsonResponse
    {
        $refresh = $request->boolean('refresh');
        try {
            $liveWeather = $sharedWeather->alignForecast(
                $weatherApi->forecast(refresh: $refresh),
                $refresh,
            );
        } catch (Throwable $e) {
            Log::warning('MAO weather fetch failed: '.$e->getMessage());
            $liveWeather = null;
        }

        return response()->json([
            'ok' => $liveWeather !== null,
            'weather' => $liveWeather,
            'source_label' => $liveWeather
                ? data_get($liveWeather, 'source', 'Open-Meteo').' live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas')
                : 'Stored climate records',
            'checked_at' => now((string) config('services.weather.timezone', 'Asia/Manila'))->toIso8601String(),
        ]);
    }

    /**
     * MAO Operations page. Each part of the page is prepared by its own method below (weather chart, typhoon check-in,
     * field reports, planting schedules, weather feedback); this method only puts them together for the view.
     */
    public function maoOperations(Request $request, WeatherApiService $weatherApi, FarmerDashboardWeatherService $sharedWeather, TyphoonSafetyService $typhoonSafety, DashboardKpiService $kpis): View
    {
        $recent = $this->recentOperationalData();
        $environment = $this->maoOperationsEnvironment($weatherApi, $sharedWeather, $recent);
        $safety = $this->maoOperationsSafety($typhoonSafety);
        $weatherFeedbackFilters = $this->weatherFeedbackFilters($request);

        return view('dashboards.mao-operations', $this->stats()
            + $this->maoOperationsRecentData($kpis, $recent, $environment, $weatherFeedbackFilters)
            + $this->maoOperationsMonitoringData($request, $kpis, $recent, $environment, $safety)
            + $safety
            + $this->maoScheduleData($request));
    }

    private function maoOperationsEnvironment(WeatherApiService $weatherApi, FarmerDashboardWeatherService $sharedWeather, array $recent): array
    {
        $records = ClimateRecord::query()->orderByDesc('record_date')->take(12)->get()->sortBy('record_date')->values();
        $liveWeather = rescue(fn () => $sharedWeather->alignForecast($weatherApi->forecast()), null, false);
        $currentRiskLevels = rescue(fn () => app(DashboardData::class)->currentRiskRatings(), collect(), false);
        $latestClimate = $recent['recentClimateRecords']->first();

        return [
            'latestClimate' => $latestClimate,
            'liveWeather' => $liveWeather,
            'weatherChartData' => data_get($liveWeather, 'daily_series') ?: $this->storedWeatherChartData($records),
            'weatherDataSource' => $liveWeather
                ? data_get($liveWeather, 'source', 'Open-Meteo').' live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas')
                : 'Stored climate records',
            // One rating per barangay, so the counts add up to the barangays on the map.
            'riskLevelCounts' => collect(['Low', 'Moderate', 'High', 'Severe'])
                ->mapWithKeys(fn (string $level) => [$level => $currentRiskLevels->where('risk_level', $level)->count()])
                ->all(),
            'currentSeason' => $latestClimate?->season ?? data_get($liveWeather, 'season'),
            'pendingAdvisoryCount' => PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_PENDING_REVIEW)->count(),
        ];
    }

    private function maoOperationsSafety(TyphoonSafetyService $typhoonSafety): array
    {
        $event = rescue(fn () => $typhoonSafety->activeEvent(), null, false);
        $responses = $this->hasTyphoonSafetyTable() && $event
            ? TyphoonSafetyResponse::query()->with('farmer')->where('event_key', $event['key'])->latest('responded_at')->get()
            : collect();
        $farmerCount = User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->count();

        return [
            'activeTyphoonSafetyEvent' => $event,
            'typhoonSafetyResponses' => $responses,
            'typhoonSafetySafeCount' => $responses->where('status', TyphoonSafetyResponse::STATUS_SAFE)->count(),
            'typhoonSafetyNeedsHelpCount' => $responses->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->count(),
            'typhoonSafetyNoResponseCount' => max(0, $farmerCount - $responses->pluck('user_id')->unique()->count()),
        ];
    }

    private function maoOperationsRecentData(DashboardKpiService $kpis, array $recent, array $environment, array $weatherFeedbackFilters): array
    {
        return [
            'maoKpis' => rescue(fn () => $kpis->mao(), [], false),
            'weatherFeedbackFilters' => $weatherFeedbackFilters,
            'weatherFeedbackSummary' => rescue(fn () => app(WeatherFeedbackSummaryService::class)->summarize($weatherFeedbackFilters), [], false),
            'farmerOrganizationSummary' => rescue(fn () => app(FarmerOrganizationSummaryService::class)->summarize()['farmerOrganizationSummary'] ?? [], [], false),
            'latestClimate' => $environment['latestClimate'],
            'recentClimateRecords' => $recent['recentClimateRecords'],
            'recentRiceProductions' => $recent['recentRiceProductions'],
            'latestAdvisories' => $recent['latestAdvisories'],
            'latestFeedPosts' => $recent['latestFeedPosts']->take(4),
            'latestReports' => $recent['latestReports']->take(4),
            'recentFarmerProfiles' => $recent['recentFarmerProfiles'],
            'highRiskAreasList' => $recent['highRiskAreas'],
            'pendingAdvisoryCount' => $environment['pendingAdvisoryCount'],
            'riceYoyDelta' => rescue(fn () => $kpis->yieldYoy(), null, false),
            'latestExternalWeather' => ExternalWeatherData::query()->latestSuccessful()->first(),
            'maoActionItems' => $this->maoActionItems(),
            ...$this->maoAggregates($kpis),
        ];
    }

    private function maoOperationsMonitoringData(Request $request, DashboardKpiService $kpis, array $recent, array $environment, array $safety): array
    {
        $weatherWarning = $this->weatherWarningLevel($environment['riskLevelCounts']);
        $fieldReportsPendingCount = FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)->count();

        return [
            'weatherChartData' => $environment['weatherChartData'],
            'weatherDataSource' => $environment['weatherDataSource'],
            'liveWeather' => $environment['liveWeather'],
            'riskLevelCounts' => $environment['riskLevelCounts'],
            'weatherWarning' => $weatherWarning,
            'overview' => rescue(fn () => app(DashboardOverviewService::class)->mao($request->integer('year') ?: null, $weatherWarning['label'] ?? null), [], false),
            'weatherAnomaly' => $this->weatherAnomalySummary($environment['liveWeather'], $environment['currentSeason']),
            'cropStatus' => $this->cropStatusSummary($environment['currentSeason']),
            'barangayMonitoring' => $this->barangayMonitoringTable(),
            'riceYieldTrend' => rescue(fn () => $this->riceYieldTrendSummary($kpis), ['latest_year' => null, 'chart' => ['labels' => [], 'avg_yield' => []], 'highest_barangay' => null, 'lowest_barangay' => null], false),
            'recentActivity' => SystemLog::query()->with('user')->latest()->take(8)->get(),
            'fieldReportsPendingCount' => $fieldReportsPendingCount,
            'recentFieldReports' => FieldReport::query()->with('assignedTo')->latest()->take(6)->get(),
            'fieldReportsByIssueType' => FieldReport::query()
                ->where('status', '!=', FieldReport::STATUS_RESOLVED)
                ->selectRaw('issue_type, COUNT(*) as total')
                ->groupBy('issue_type')
                ->pluck('total', 'issue_type'),
            'maoHighlights' => $this->maoHighlights(
                $recent['highRiskAreas'],
                $environment['pendingAdvisoryCount'],
                $safety['activeTyphoonSafetyEvent'],
                $safety['typhoonSafetyNeedsHelpCount'],
                $safety['typhoonSafetyNoResponseCount'],
                $fieldReportsPendingCount,
            ),
        ];
    }

    /**
     * @param  Collection<int, ClimateRecord>  $records
     * @return array{labels: array<int, string>, rainfall: array<int, float|null>, temperature: array<int, float|null>, humidity: array<int, float|null>, windSpeed: array<int, float|null>}
     */
    private function storedWeatherChartData(Collection $records): array
    {
        return [
            'labels' => $records->map(fn (ClimateRecord $record) => $record->record_date?->format('M d') ?? 'N/A')->all(),
            // A missing reading stays null (a gap in the chart), never 0 mm / 0 °C / 0 %.
            'rainfall' => $records->map(fn (ClimateRecord $record) => $record->rainfall !== null ? (float) $record->rainfall : null)->all(),
            'temperature' => $records->map(fn (ClimateRecord $record) => $record->temperature !== null ? (float) $record->temperature : null)->all(),
            'humidity' => $records->map(fn (ClimateRecord $record) => $record->humidity !== null ? (float) $record->humidity : null)->all(),
            'windSpeed' => $records->map(fn (ClimateRecord $record) => $record->wind_speed !== null ? (float) $record->wind_speed : null)->all(),
        ];
    }

    private function maoScheduleData(Request $request): array
    {
        $season = $this->stringQuery($request, 'schedule_season');
        $farmType = $this->stringQuery($request, 'schedule_farm_type');
        $filters = [
            'month' => max(0, min(12, $request->integer('schedule_month'))),
            'season' => in_array($season, FarmerPlantingSchedule::SEASONS, true) ? $season : '',
            'barangay' => $this->stringQuery($request, 'schedule_barangay'),
            'farm_type' => in_array($farmType, FarmerPlantingSchedule::FARM_TYPES, true) ? $farmType : '',
            'farmer' => $request->integer('schedule_farmer'),
            'status' => $this->stringQuery($request, 'schedule_status'),
        ];
        $featureReady = Schema::hasTable('farmer_planting_schedules');
        $schedules = $featureReady
            ? FarmerPlantingSchedule::query()
                ->with(['farmer:id,name,barangay', 'farmer.farmerProfile:id,user_id,full_name,barangay'])
                ->when($filters['month'], function ($query, int $month) {
                    $query->where(function ($query) use ($month) {
                        $query->whereMonth('planting_date', $month)
                            ->orWhereMonth('scheduled_harvest_date', $month)
                            ->orWhereMonth('harvest_month', $month);
                    });
                })
                ->when($filters['season'], fn ($query, string $value) => $query->where('season', $value))
                ->when($filters['barangay'], fn ($query, string $value) => $query->where('barangay', $value))
                ->when($filters['farm_type'], fn ($query, string $value) => $query->where('farm_type', $value))
                ->when($filters['farmer'], fn ($query, int $value) => $query->where('user_id', $value))
                ->orderByRaw('CASE WHEN scheduled_harvest_date IS NULL THEN harvest_month ELSE scheduled_harvest_date END ASC')
                ->limit(150)
                ->get()
            : collect();

        if ($filters['status'] !== '') {
            $schedules = $schedules
                ->filter(fn (FarmerPlantingSchedule $schedule) => $schedule->monitoringStatus() === $filters['status'])
                ->values();
        }

        return [
            'farmerSchedules' => $schedules,
            'plantingScheduleFeatureReady' => $featureReady,
            'scheduleFilters' => $filters,
            'scheduleFarmers' => User::query()->where('role', User::ROLE_FARMER)->orderBy('name')->get(['id', 'name']),
            'scheduleBarangays' => $featureReady
                ? FarmerPlantingSchedule::query()->whereNotNull('barangay')->distinct()->orderBy('barangay')->pluck('barangay')
                : collect(),
        ];
    }

    /** @return array{barangay: string|null, from: string|null, to: string|null, condition: string|null} */
    private function weatherFeedbackFilters(Request $request): array
    {
        $barangay = $this->stringQuery($request, 'fb_barangay');
        $from = $this->dateQuery($request, 'fb_from');
        $to = $this->dateQuery($request, 'fb_to');
        $condition = $this->stringQuery($request, 'fb_condition');

        return [
            'barangay' => $barangay !== '' ? $barangay : null,
            'from' => $from,
            'to' => $to,
            'condition' => in_array($condition, WeatherForecastFeedback::OBSERVED_CONDITIONS, true) ? $condition : null,
        ];
    }

    private function stringQuery(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }

    private function dateQuery(Request $request, string $key): ?string
    {
        $value = $this->stringQuery($request, $key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    /** IT overview: everything comes from ItDashboardService and is cached for a short time (the warm-up job refreshes it). */
    public function admin(Request $request, ItDashboardService $it): View
    {
        // Expired or invalidated: the last good overview is shown and rebuilt after the response (the cold build is
        // ~80 Supabase round trips, longer than a request may run).
        $payload = StaleCache::remember($this->adminCacheKey(), self::IT_PAGE_CACHE_SECONDS, fn (): array => $it->payload(), self::ADMIN_LATEST_KEY, self::IT_MAX_STALE_SECONDS);

        $tab = in_array($request->query('tab'), self::IT_TABS, true) ? $request->query('tab') : 'overview';

        return view('dashboards.it-expert', ['it' => $payload, 'tab' => $tab]);
    }

    /** Refresh button: rebuilds the live sections and returns each section's HTML. */
    public function adminLive(ItDashboardService $it): JsonResponse
    {
        // An explicit rebuild of every section: allowed the time a full rebuild takes.
        StaleCache::allowTime(StaleCache::COLD_BUILD_SECONDS);
        $payload = $it->payload(fresh: true);
        StaleCache::put($this->adminCacheKey(), self::IT_PAGE_CACHE_SECONDS, $payload, self::ADMIN_LATEST_KEY);

        $slots = [];
        foreach (self::IT_LIVE_SLOTS as $slot) {
            $slots[$slot] = view('dashboards.it.'.$slot, ['it' => $payload])->render();
        }

        return response()->json([
            'slots' => $slots,
            'refreshed_at' => $payload['checked_at']->format('g:i:s A'),
            'weather_synced_at' => $payload['weather_synced_at']?->format('M j, g:i A'),
            'level' => $payload['overall']['level'],
            'status' => $payload['overall']['label'],
        ]);
    }

    /** The Retry button on "Failed jobs by class": puts that class's failed jobs back on the queue. */
    public function adminRetryFailed(Request $request): RedirectResponse
    {
        $class = (string) $request->input('class');
        $ids = ItDashboardInsights::failedJobIds($class);
        if ($ids !== []) {
            SystemAuditLogger::record('Retry Failed Jobs', $request, ['job_class' => $class, 'jobs' => count($ids), 'retried_by' => $request->user()->name]);
            Artisan::call('queue:retry', ['id' => $ids]);
            Cache::forget($this->adminCacheKey());
        }

        return redirect()->route('admin.dashboard')->with('status', count($ids).' failed '.(count($ids) === 1 ? 'job was' : 'jobs were').' put back on the queue.');
    }

    /** Recent System Activity with its filters (role, action type, date range); returns the table's HTML. */
    public function adminRecent(Request $request, ItDashboardService $it): JsonResponse
    {
        $payload = ['recent' => $it->recentActivity($request->only(['role', 'action', 'from', 'to']))];

        return response()->json(['html' => view('dashboards.it.recent-rows', ['it' => $payload])->render()]);
    }

    /** Daily counts per kind of event for the System Activity chart (range 7, 30 or 90 days; anything else is 30). */
    public function adminActivity(Request $request, ItDashboardService $it): JsonResponse
    {
        return response()->json($it->activity((int) $request->query('range', 30)));
    }

    private function recentOperationalData(): array
    {
        return StaleCache::remember('dashboard:recent-operational-data', 30, fn (): array => [
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
        return StaleCache::remember('dashboard:high-risk-areas', 15, fn (): Collection => HeatmapArea::query()
            ->computed()                                              // install placeholders are not ratings
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
        return StaleCache::remember('dashboard:mao-action-items', 20, fn (): array => [
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
            ->where('created_at', '>=', BusinessClock::startOfDayUtc(7))
            ->selectRaw(''.BusinessClock::sqlDate('created_at').' as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $todayKey = BusinessClock::today()->toDateString();
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

    /**
     * Rice figures are for the latest year on record only — summing every
     * year would count the same hectares several times — and yield is
     * area-weighted (total tons / total hectares).
     */
    private function maoAggregates(DashboardKpiService $kpis): array
    {
        return StaleCache::remember('dashboard:mao-aggregates', 45, function () use ($kpis): array {
            $latest = rescue(fn () => $kpis->yieldByYear()->first(), null, false);

            return [
                'riceLatestYear' => $latest['year'] ?? null,
                // null = no verified harvest data (never shown as 0 tons)
                'riceProductionTotal' => isset($latest['production']) ? (float) $latest['production'] : null,
                'riceAreaTotal' => isset($latest['area']) ? (float) $latest['area'] : null,
                'avgYield' => isset($latest['yield']) ? (float) $latest['yield'] : null,
                'profileCount' => FarmerProfile::count(),
                'reportCount' => Report::query()->realGenerated()->count(),
                'publishedAdvisoryCount' => PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_PUBLISHED)->count(),
                'communityPostCount' => FeedPost::query()->whereNull('archived_at')->count(),
            ];
        });
    }

    private function adminAggregates(): array
    {
        return StaleCache::remember('dashboard:admin-aggregates', 45, function (): array {
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
                    'Predictions' => MonthlyWeatherPrediction::count(),
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

        $estimatedYield = HeatmapArea::query()->computed()->whereNotNull('predicted_yield')->avg('predicted_yield');
        $latestArea = (float) $latestPerBarangay->sum('area_hectares');
        $actualYield = $latestArea > 0 ? (float) $latestPerBarangay->sum('total_production') / $latestArea : null;

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
            ->selectRaw('barangay, SUM(area_hectares) as area_hectares, SUM(total_production) as production_volume, SUM(total_production) / NULLIF(SUM(area_hectares), 0) as avg_yield')
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
                    'avg_yield' => $row && $row->avg_yield !== null ? (float) $row->avg_yield : null,
                    // A barangay with only an install placeholder shows "No Data" (the table's own wording), never a level.
                    'risk_level' => $area->isSeedPlaceholder() ? null : $area->risk_level,
                    'risk_type' => $area->isSeedPlaceholder() ? null : $area->risk_type,
                    'last_monitored' => $area->isSeedPlaceholder() ? null : $area->updated_at,
                ];
            })
            ->values();
    }

    /**
     * Multi-year yield trend plus this year's highest/lowest producing
     * barangays by total production.
     */
    private function riceYieldTrendSummary(DashboardKpiService $kpis): array
    {
        // Area-weighted yield per year (total tons / total hectares).
        $byYear = rescue(
            fn () => $kpis->yieldByYear()->filter(fn (array $year) => ($year['yield'] ?? null) !== null)->sortBy('year')->values(),
            collect(),
            false
        );

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
                'avg_yield' => $byYear->pluck('yield')->map(fn ($value) => round((float) $value, 2))->all(),
            ],
            'highest_barangay' => $byBarangayThisYear->first(),
            'lowest_barangay' => $byBarangayThisYear->count() > 1 ? $byBarangayThisYear->last() : null,
        ];
    }

    private function stats(): array
    {
        return StaleCache::remember('dashboard:shared-stats', 45, function (): array {
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
