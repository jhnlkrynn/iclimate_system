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
use App\Services\Analytics\PreparedDashboards;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Analytics\ItDashboardInsights;
use App\Services\Analytics\ItDashboardService;
use App\Services\Analytics\MaoDashboardService;
use App\Services\Analytics\MaoHomeService;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\Risk\HeatMapPanel;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Services\Weather\WeatherFeedbackSummaryService;
use App\Services\WeatherApiService;
use App\Support\Aggregates;
use App\Support\BuildMemo;
use App\Support\BusinessClock;
use App\Support\CacheScope;
use App\Support\DashboardData;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use App\Support\RiskMapInitialData;
use App\Support\StaleCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    /** Per-farmer overview: short, because it carries live weather. */
    private const FARMER_OVERVIEW_CACHE_SECONDS = 60;

    /** The saved-data MAO home payload is shared and can be prepared ahead of the first visit. */
    private const MAO_HOME_CACHE_SECONDS = 90;

    /** Sections the MAO refresh button re-renders (partials under dashboards/mao). */
    private const MAO_LIVE_SLOTS = ['kpis', 'attention', 'conditions', 'forecast', 'watch', 'planting', 'coverage', 'production'];

    /** Sections the IT refresh button re-renders (partials under dashboards/it). */
    private const IT_LIVE_SLOTS = ['kpis', 'health', 'brief', 'pipeline', 'jobs', 'security', 'roles', 'usage', 'ai', 'resources', 'integrity', 'ops', 'recent'];

    /** The Farmer Home parts (partials under dashboards/farmer), in page order; the page and the refresh render the same list. */
    public const FARMER_SLOTS = ['message', 'typhoon', 'weather', 'risk', 'harvest', 'week'];

    /** ?retry=harvest-check: the yield card's placeholder asks whether the background estimate is saved yet (reads only). */
    private const FARMER_RETRY = [
        'harvest-check' => ['slots' => ['harvest']],
    ];

    /** MAO overview: the incoming saved-data home contract, retained behind the shared stale cache. */
    public function mao(Request $request, MaoHomeService $home): View
    {
        return view('dashboards.mao', [
            'mao' => StaleCache::scoped(
                'mao-home',
                fn (string $token): string => $this->maoHomeCacheKey($token),
                self::MAO_HOME_CACHE_SECONDS,
                fn (): array => $home->payload(),
            ),
        ]);
    }

    /**
     * The page's timed refresh reads the prepared payload (a stale one is rebuilt after the response). The Refresh
     * button (?refresh=1) rebuilds it now with the latest weather, once even if several people press it together.
     */
    public function maoLive(Request $request, MaoDashboardService $mao, PreparedDashboards $dashboards): JsonResponse
    {
        $payload = $request->boolean('refresh') ? $dashboards->refreshMao() : $dashboards->mao();
        $data = $mao->forUser($payload, $request->user());

        $slots = [];
        foreach (self::MAO_LIVE_SLOTS as $slot) {
            $slots[$slot] = view('dashboards.mao.'.$slot, ['mao' => $data])->render();
        }

        return response()->json(['slots' => $slots, 'refreshed_at' => $data['generated_at']->format('g:i A')]);
    }

    /**
     * Prepares the shared payloads that are due (MAO, IT, MAO Operations) so the next person to open one does not wait
     * (run by iclimate:warm-dashboards).
     *
     * @return array<string, string> payload => what happened
     */
    public function warmSharedDashboards(): array
    {
        // Populate the saved risk-map inputs before the prepared dashboard payloads are warmed.
        rescue(fn () => RiskMapInitialData::payloads(false), null, false);
        StaleCache::prepareScoped(
            'mao-home',
            fn (string $token): string => $this->maoHomeCacheKey($token),
            self::MAO_HOME_CACHE_SECONDS,
            fn (): array => app(MaoHomeService::class)->payload(),
        );
        return app(PreparedDashboards::class)->warm();
    }

    private function maoHomeCacheKey(?string $token = null): string
    {
        return 'dashboard:mao:home:v2'.($token === null ? '' : ':'.$token);
    }

    /** Farmer Home: five plain parts from saved data (FarmerDashboardService), in one request. */
    public function farmer(Request $request, FarmerDashboardService $farmer): View
    {
        $payload = $this->preparedFarmerPayload($request->user(), $farmer);
        // Farmer – Naicel (2026-10): missing farm information goes to the notification bell (at most once a minute).
        // After the page data is read: a new notification clears the farmer's stored page, so it must not run first.
        app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->syncOnPageLoad($request->user());

        return view('dashboards.farmer', ['f' => $payload]);
    }

    /** The farmer's prepared Home (built from saved data when there is none for the current data yet). */
    private function preparedFarmerPayload(User $user, FarmerDashboardService $farmer): array
    {
        $snapshot = CacheScope::snapshot('farmer-dashboard');
        $payload = Cache::get(FarmerDashboardService::cacheKey($user, token: CacheScope::tokenAt($snapshot)));
        if (! is_array($payload)) {
            $payload = $farmer->payload($user);
            // Stored under the token that includes the build's own writes (a weather snapshot), so the next visit finds it.
            Cache::put(FarmerDashboardService::cacheKey($user, token: CacheScope::settledToken($snapshot)), $payload, now()->addSeconds(self::FARMER_OVERVIEW_CACHE_SECONDS));
        }

        return $payload;
    }

    /**
     * Heat Map (2026-10): the map with one panel beside it. Both ranges ([This Week], [2 Weeks]) come with the page,
     * from the saved daily forecast only, so the toggle and a barangay click never wait for a request.
     */
    public function heatMap(Request $request, HeatMapPanel $panel, LianBarangayWeatherService $weather): View
    {
        $user = $request->user();
        $farmer = $user?->role === User::ROLE_FARMER;
        $locale = $farmer ? FarmerLanguage::resolve($request) : 'en';
        $ownBarangay = $farmer ? LianBarangays::canonicalName($user->registeredBarangay()) : null;

        // The page shows the saved forecast at once. When the shared barangay weather is due (the scheduler is behind
        // or has not run yet), the one shared refresh — and the risk rating from it — runs after this response; it is
        // locked and at most once a minute, however many people open the page.
        rescue(fn () => $weather->refreshIfDue($ownBarangay), report: false);
        $ranges = $panel->ranges($locale);

        return view('heat-map.index', [
            'ranges' => $ranges,
            'range' => $request->query('range') === '2weeks' ? '2weeks' : 'week',
            'locale' => $locale,
            // The farmer's own barangay is outlined; every role still sees and can click all 19.
            'ownBarangay' => $ownBarangay,
            // Farmers read plain words; MAO/IT also get the forecast numbers.
            'showNumbers' => ! $farmer,
            // A barangay without a forecast while new weather is on its way: heat-map.js reads the page again shortly.
            'updating' => collect($ranges)->contains(fn (array $r): bool => $r['counts']['nodata'] > 0)
                && rescue(fn (): bool => $weather->isDue(), false, false),
        ]);
    }

    /**
     * The page's timed refresh and the Refresh button (?refresh=1, which also asks for the latest weather): every part's
     * HTML from saved data. ?retry=harvest-check returns only the yield card from the stored estimate, never running the
     * model or rebuilding the page.
     */
    public function farmerLive(Request $request, FarmerDashboardService $farmer): JsonResponse
    {
        $user = $request->user();
        $retry = self::FARMER_RETRY[$request->query('retry')] ?? null;

        if ($retry !== null && ! $request->boolean('refresh')) {
            // Only the yield card, from the stored estimate: no dashboard rebuild. While it is still being prepared nothing
            // is sent, so the placeholder stays; a ready estimate, or the reason there is none, replaces it.
            $harvest = $farmer->seasonEstimate($user);

            return response()->json([
                'slots' => $harvest['state'] === 'loading' ? [] : ['harvest' => view('dashboards.farmer.harvest', ['f' => ['harvest' => $harvest]])->render()],
                'updated' => now(FarmerDashboardService::TIMEZONE)->format('g:i A'),
            ]);
        }

        if ($request->boolean('refresh')) {
            // The Refresh button: rebuilt now with the latest weather, once even if the farmer presses it twice.
            $payload = StaleCache::refreshScoped('farmer-dashboard', fn (string $token): string => FarmerDashboardService::cacheKey($user, token: $token),
                self::FARMER_OVERVIEW_CACHE_SECONDS, fn (): array => $farmer->payload($user, forceWeather: true), waitSeconds: 30);
        } else {
            // The timed refresh reads the prepared Home.
            $payload = $this->preparedFarmerPayload($user, $farmer);
        }

        $slots = [];
        foreach (self::FARMER_SLOTS as $slot) {
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
     * Everything the MAO Operations page shows, for this request's filters (about 100 queries cold). Called by
     * PreparedDashboards, from a page request or the warm-up command; nothing in it depends on who is signed in.
     *
     * @return array<string, mixed>
     */
    public function maoOperationsData(Request $request): array
    {
        // One build: a figure several parts read is read once (BuildMemo), and the single counts and averages of
        // this controller are read together in one statement before the parts ask for them (Performance Phase 4).
        return BuildMemo::run(function () use ($request): array {
            Aggregates::fetch($this->maoOperationsReads());

            return $this->buildMaoOperationsData($request);
        });
    }

    /**
     * Every single count or average the MAO Operations parts of this controller read, by part. Each part reads its
     * own entries again (from BuildMemo inside the build); listed once here so both always send the same SQL.
     *
     * @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}>
     */
    private function maoOperationsReads(): array
    {
        $reads = [];
        foreach ([
            'stats' => $this->statsReads(),
            'aggregates' => $this->maoAggregateReads(),
            'action' => $this->maoActionItemReads(),
            'ops' => $this->maoOperationsCountReads(),
            'season' => $this->seasonalRainfallReads(ClimateRecord::seasonForMonth(BusinessClock::month())),
            'crop' => $this->estimatedYieldReads(),
        ] as $part => $partReads) {
            foreach ($partReads as $name => $read) {
                $reads[$part.'.'.$name] = $read;
            }
        }

        return $reads;
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function maoOperationsCountReads(): array
    {
        return [
            'pending' => Aggregates::count(PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_PENDING_REVIEW)),
            'activeFarmers' => Aggregates::count(User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)),
            'openFieldReports' => Aggregates::count(FieldReport::query()->where('status', '!=', FieldReport::STATUS_RESOLVED)),
        ];
    }

    private function buildMaoOperationsData(Request $request): array
    {
        $weatherApi = app(WeatherApiService::class);
        $sharedWeather = app(FarmerDashboardWeatherService::class);
        $typhoonSafety = app(TyphoonSafetyService::class);
        $kpis = app(DashboardKpiService::class);

        $recent = $this->recentOperationalData();
        $environment = $this->maoOperationsEnvironment($weatherApi, $sharedWeather, $recent);
        $safety = $this->maoOperationsSafety($typhoonSafety);
        $weatherFeedbackFilters = $this->weatherFeedbackFilters($request);

        return $this->stats()
            + $this->maoOperationsRecentData($kpis, $recent, $environment, $weatherFeedbackFilters)
            + $this->maoOperationsMonitoringData($request, $kpis, $recent, $environment, $safety)
            + $safety
            + $this->maoScheduleData($request);
    }

    private function maoOperationsEnvironment(WeatherApiService $weatherApi, FarmerDashboardWeatherService $sharedWeather, array $recent): array
    {
        $records = $this->maoOperationsClimateRecords();
        $liveWeather = $this->maoOperationsLiveWeather($weatherApi, $sharedWeather);

        return [
            'latestClimate' => $recent['recentClimateRecords']->first(),
            'liveWeather' => $liveWeather,
            'weatherChartData' => $this->maoOperationsWeatherChartData($liveWeather, $records),
            'weatherDataSource' => $this->maoOperationsWeatherDataSource($liveWeather),
            'riskLevelCounts' => $this->maoOperationsRiskLevelCounts(),
            'currentSeason' => $this->maoOperationsCurrentSeason(),
            'pendingAdvisoryCount' => $this->maoOperationsPendingAdvisoryCount(),
        ];
    }

    /** @return Collection<int, ClimateRecord> */
    private function maoOperationsClimateRecords(): Collection
    {
        return ClimateRecord::query()->orderByDesc('record_date')->take(12)->get()->sortBy('record_date')->values();
    }

    private function maoOperationsLiveWeather(WeatherApiService $weatherApi, FarmerDashboardWeatherService $sharedWeather): ?array
    {
        return rescue(fn () => $sharedWeather->alignForecast($weatherApi->forecast()), null, false);
    }

    /** @param Collection<int, ClimateRecord> $records */
    private function maoOperationsWeatherChartData(?array $liveWeather, Collection $records): array
    {
        return data_get($liveWeather, 'daily_series') ?: $this->storedWeatherChartData($records);
    }

    private function maoOperationsWeatherDataSource(?array $liveWeather): string
    {
        return $liveWeather
            ? data_get($liveWeather, 'source', 'Open-Meteo').' live weather for '.data_get($liveWeather, 'location', 'Lian, Batangas')
            : 'Stored climate records';
    }

    private function maoOperationsRiskLevelCounts(): array
    {
        $currentRiskLevels = rescue(fn () => app(DashboardData::class)->currentRiskRatings(), collect(), false);

        // One rating per barangay, so the counts add up to the barangays on the map.
        return collect(['Low', 'Moderate', 'High', 'Severe'])
            ->mapWithKeys(fn (string $level) => [$level => $currentRiskLevels->where('risk_level', $level)->count()])
            ->all();
    }

    private function maoOperationsCurrentSeason(): string
    {
        // Use today's month, not the newest stored record, which can belong to a different season.
        return ClimateRecord::seasonForMonth(BusinessClock::month());
        
    }

    private function maoOperationsPendingAdvisoryCount(): int
    {
        return (int) Aggregates::fetch(['pending' => $this->maoOperationsCountReads()['pending']])['pending'];
    }

    private function maoOperationsSafety(TyphoonSafetyService $typhoonSafety): array
    {
        $event = rescue(fn () => $typhoonSafety->activeEvent(), null, false);
        $responses = $this->hasTyphoonSafetyTable() && $event
            ? TyphoonSafetyResponse::query()->with('farmer')->where('event_key', $event['key'])->latest('responded_at')->get()
            : collect();
        $farmerCount = Aggregates::fetch(['farmers' => $this->maoOperationsCountReads()['activeFarmers']])['farmers'];

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
        $fieldReportsPendingCount = Aggregates::fetch(['open' => $this->maoOperationsCountReads()['openFieldReports']])['open'];

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
            // A missing reading stays null (a gap in the chart), never 0 mm / 0 �C / 0 %.
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
        $featureReady = SchemaCache::hasTable('farmer_planting_schedules');
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
    public function admin(Request $request, ItDashboardService $it, PreparedDashboards $dashboards): View
    {
        // Expired or invalidated: the last good overview is shown and rebuilt after the response (the cold build is
        // ~80 Supabase round trips, longer than a request may run).
        $payload = $dashboards->it();

        // IT Dashboard (2026-10): one screen — System Status, Users by Role, Errors and Failed Jobs, Security. No tabs.
        return view('dashboards.it-expert', ['it' => $payload]);
    }

    /**
     * The IT overview that is already prepared (by the scheduled warm-up, the IT dashboard or its Refresh button),
     * for the System Health page, which carries Usage Patterns, Resources and the Data Pipeline since 2026-10.
     * Read only: it never builds the overview, so that page calls no service and queues nothing. Null when no copy
     * has been prepared yet.
     *
     * @return array<string, mixed>|null
     */
    public function preparedItOverview(): ?array
    {
        $fresh = Cache::get(PreparedDashboards::itKey(CacheScope::token('it-dashboard')));
        if (is_array($fresh)) {
            return $fresh;
        }
        $latest = Cache::get(PreparedDashboards::IT_LATEST_KEY);

        return is_array($latest) && is_array($latest['value'] ?? null) ? $latest['value'] : null;
    }

    /**
     * The page's timed refresh reads the prepared overview; the Refresh button (?refresh=1) rebuilds every section now,
     * once even if several people press it together. Each section's HTML is returned.
     */
    public function adminLive(Request $request, PreparedDashboards $dashboards): JsonResponse
    {
        $payload = $request->boolean('refresh') ? $dashboards->refreshIt() : $dashboards->it();

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
            Cache::forget(PreparedDashboards::itKey(CacheScope::token('it-dashboard')));
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
     * "Needs Your Attention" summary for the MAO dashboard � things that
     * require MAO action, not just informational counts. Short TTL since
     * these are action queues, not slow-moving aggregates.
     */
    private function maoActionItems(): array
    {
        return StaleCache::remember('dashboard:mao-action-items', 20, fn (): array => [
            'feedbackAwaitingResponse' => ($reads = $this->maoActionItemReads()) ? Aggregates::fetch($reads)['feedbackAwaitingResponse'] : 0,
        ]);
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function maoActionItemReads(): array
    {
        return SchemaCache::hasTable('advisory_feedback')
            ? ['feedbackAwaitingResponse' => Aggregates::count(AdvisoryFeedback::query()->whereNotNull('is_helpful')->whereNull('mao_response'))]
            : [];
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
                'message' => 'One or more barangays failed to recompute risk during the heatmap refresh. Check the newest file in storage/logs (laravel*.log).',
            ];
        }

        if (! $heatmapLastRefresh) {
            $highlights[] = [
                'severity' => 'warning',
                'weight' => 2,
                'title' => 'Heatmap risk data has never been refreshed',
                'message' => 'No scheduled job runs the heatmap refresh � it only fires when someone opens the Heat Map page.',
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
     * trailing week's daily average � a plain volume check, not a
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
     * Rice figures are for the latest year on record only � summing every
     * year would count the same hectares several times � and yield is
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
                ...Aggregates::fetch($this->maoAggregateReads()),
            ];
        });
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function maoAggregateReads(): array
    {
        return [
            'profileCount' => Aggregates::count(FarmerProfile::query()),
            'reportCount' => Aggregates::count(Report::query()->realGenerated()),
            'publishedAdvisoryCount' => Aggregates::count(PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_PUBLISHED)),
            'communityPostCount' => Aggregates::count(FeedPost::query()->whereNull('archived_at')),
        ];
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
                'message' => 'During "'.$activeTyphoonSafetyEvent['title'].'" � see Typhoon Safety Check-In below.',
            ];
        }

        $topRiskArea = $highRiskAreasList->first();
        if ($topRiskArea) {
            $highlights[] = [
                'severity' => $topRiskArea->risk_level === 'Severe' ? 'critical' : 'warning',
                'weight' => $topRiskArea->risk_level === 'Severe' ? 3 : 2,
                'title' => $topRiskArea->barangay.' is at '.$topRiskArea->risk_level.' risk',
                'message' => 'Risk score '.number_format((float) $topRiskArea->risk_score, 2).'/1.00 � '.($topRiskArea->risk_type ?: 'Climate').' risk.',
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
                'message' => 'Pending or unverified issues � see Active Alerts & Field Reports below.',
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
     * historical average daily rainfall recorded for the same season �
     * both are same-unit (mm/day) figures, so this is a like-for-like
     * anomaly check, not a fabricated statistic.
     */
    private function weatherAnomalySummary(?array $liveWeather, ?string $currentSeason): array
    {
        $seasonalAvgRainfall = $currentSeason
            ? Aggregates::fetch($this->seasonalRainfallReads($currentSeason))['rainfall']
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
     * data. Planting/harvesting progress is intentionally left out � the
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

        $estimatedYield = Aggregates::fetch($this->estimatedYieldReads())['yield'];
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
     * Per-barangay table combining RiceProduction and HeatmapArea � the
     * data-table alternative to a heat-map-only view.
     */
    private function barangayMonitoringTable(): Collection
    {
        $production = RiceProduction::query()
            ->selectRaw('barangay, SUM(area_hectares) as area_hectares, SUM(total_production) as production_volume, SUM(total_production) / NULLIF(SUM(area_hectares), 0) as avg_yield')
            ->groupBy('barangay')
            ->get()
            ->keyBy('barangay');

        // The same rows (same query) the risk figures of this build already loaded.
        return app(DashboardData::class)->heatmapAreas()
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
        return StaleCache::remember('dashboard:shared-stats', 45, fn (): array => Aggregates::fetch($this->statsReads()));
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function statsReads(): array
    {
        return [
            'totalFarmers' => Aggregates::count(User::query()->where('role', User::ROLE_FARMER)),
            'totalClimateRecords' => Aggregates::count(ClimateRecord::query()),
            'totalRiceProductions' => Aggregates::count(RiceProduction::query()),
            'totalAdvisories' => Aggregates::count(PlantingAdvisory::query()),
            'totalCommunityPosts' => Aggregates::count(FeedPost::query()->whereNull('archived_at')),
            'totalNotifications' => Aggregates::count(UserNotification::query()),
        ];
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function seasonalRainfallReads(?string $season): array
    {
        return $season ? ['rainfall' => Aggregates::avg(ClimateRecord::query()->where('season', $season), 'rainfall')] : [];
    }

    /** @return array<string, array{0: \Illuminate\Database\Query\Builder, 1: string}> */
    private function estimatedYieldReads(): array
    {
        return ['yield' => Aggregates::avg(HeatmapArea::query()->computed()->whereNotNull('predicted_yield'), 'predicted_yield')];
    }

    private function hasTyphoonSafetyTable(): bool
    {
        return SchemaCache::hasTable('typhoon_safety_responses');
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
