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
use App\Services\Analytics\MaoHomeService;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\Risk\HeatMapPanel;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\WeatherFeedbackSummaryService;
use App\Services\WeatherApiService;
use App\Support\BusinessClock;
use App\Support\DashboardData;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
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

    private const ADMIN_LATEST_KEY = 'dashboard:it:overview:3:latest';

    /** The IT overview shows when each section was built, so its last copy may be shown for up to an hour while it rebuilds. */
    private const IT_MAX_STALE_SECONDS = 3600;

    /** Sections the MAO refresh button re-renders (partials under dashboards/mao). */
    private const MAO_LIVE_SLOTS = ['kpis', 'attention', 'conditions', 'forecast', 'watch', 'planting', 'coverage', 'production'];

    /** Sections the IT refresh button re-renders (partials under dashboards/it). */
    private const IT_LIVE_SLOTS = ['kpis', 'health', 'brief', 'pipeline', 'jobs', 'security', 'roles', 'usage', 'ai', 'resources', 'integrity', 'ops', 'recent'];

    /** The Farmer Home parts (partials under dashboards/farmer), in page order; the page and the refresh render the same list. */
    public const FARMER_SLOTS = ['message', 'typhoon', 'weather', 'risk', 'harvest', 'week'];

    /** ?retry=harvest-check: the yield card's placeholder asks whether the background estimate is saved yet (reads only). */
    private const FARMER_RETRY = [
        'harvest-check' => ['refresh' => [], 'slots' => ['harvest']],
    ];

    /** MAO overview: shared payload (cached briefly) plus what depends on who is signed in. */
    /** MAO Dashboard (2026-10): four KPI tiles, "Kailangan ng Aksyon", a Heat Map preview and one chart, from saved data. */
    public function mao(Request $request, MaoHomeService $home): View
    {
        return view('dashboards.mao', [
            'mao' => StaleCache::remember($this->maoHomeCacheKey(), self::MAO_OVERVIEW_CACHE_SECONDS, fn (): array => $home->payload()),
        ]);
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

        // First, because it may store ratings (which retires the dashboard copies made before it): the IT dashboard's
        // map reads the stored barangay ratings, which the old MAO dashboard used to load as a side effect of its warm-up.
        rescue(fn () => RiskMapInitialData::payloads(false), null, false);

        $started = microtime(true);
        StaleCache::put($this->maoHomeCacheKey(), self::MAO_OVERVIEW_CACHE_SECONDS, app(MaoHomeService::class)->payload());
        $timings['MAO'] = round(microtime(true) - $started, 1);

        $started = microtime(true);
        StaleCache::put($this->adminCacheKey(), self::IT_PAGE_CACHE_SECONDS, app(ItDashboardService::class)->payload(), self::ADMIN_LATEST_KEY);
        // The Overview's 30-day activity chart comes with the page (see admin()): prepared here too.
        app(ItDashboardService::class)->activity(30);
        $timings['IT'] = round(microtime(true) - $started, 1);

        return $timings;
    }

    private function maoHomeCacheKey(): string
    {
        return 'dashboard:mao:home:v'.DashboardData::version();
    }

    private function maoOverviewCacheKey(): string
    {
        return 'dashboard:mao:overview:v'.DashboardData::version();
    }

    private function adminCacheKey(): string
    {
        return 'dashboard:it:overview:3:v'.DashboardData::version();
    }

    /** Farmer Home: five plain parts from saved data (FarmerDashboardService), in one request. */
    public function farmer(Request $request, FarmerDashboardService $farmer): View
    {
        $user = $request->user();
        // Farmer – Naicel (2026-10): missing farm information goes to the notification bell, and ?farm= opens Home
        // for one of the farmer's other farms (the selector is shown with two or more farms).
        app(\App\Services\Farmer\FarmerMissingInfoNotifier::class)->syncOnPageLoad($user);
        $farmId = $this->farmerOtherFarmId($request);

        $key = FarmerDashboardService::cacheKey($user, null, $farmId);
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            $payload = $farmer->payload($user, farmId: $farmId);
            Cache::put($key, $payload, now()->addSeconds(self::FARMER_OVERVIEW_CACHE_SECONDS));
        }

        return view('dashboards.farmer', ['f' => $payload]);
    }

    /** Farmer – Naicel: ?farm= when it is one of the farmer's own active farms other than the primary one; else null. */
    private function farmerOtherFarmId(Request $request): ?int
    {
        if (! is_numeric($request->query('farm'))) {
            return null;
        }
        $farms = app(\App\Services\Farmer\FarmService::class);
        $farm = $farms->selected($request->user(), $request->query('farm'));

        return $farm !== null && ! $farms->isPrimary($request->user(), $farm) ? (int) $farm->id : null;
    }

    /**
     * Heat Map (2026-10): the map with one panel beside it. Both ranges ([This Week], [2 Weeks]) come with the page,
     * from the saved daily forecast only, so the toggle and a barangay click never wait for a request.
     */
    public function heatMap(Request $request, HeatMapPanel $panel): View
    {
        $user = $request->user();
        $farmer = $user?->role === User::ROLE_FARMER;
        $locale = $farmer ? FarmerLanguage::resolve($request) : 'en';

        return view('heat-map.index', [
            'ranges' => $panel->ranges($locale),
            'range' => $request->query('range') === '2weeks' ? '2weeks' : 'week',
            'locale' => $locale,
            // The farmer's own barangay is outlined; every role still sees and can click all 19.
            'ownBarangay' => $farmer ? LianBarangays::canonicalName($user->registeredBarangay()) : null,
            // Farmers read plain words; MAO/IT also get the forecast numbers.
            'showNumbers' => ! $farmer,
        ]);
    }

    /**
     * The page's timed refresh and the Refresh button (?refresh=1, which also asks for the latest weather): every part's
     * HTML from saved data. ?retry=harvest-check returns only the yield card, never running the model.
     */
    public function farmerLive(Request $request, FarmerDashboardService $farmer): JsonResponse
    {
        $user = $request->user();
        $retry = self::FARMER_RETRY[$request->query('retry')] ?? null;

        $farmId = $this->farmerOtherFarmId($request);
        $payload = $farmer->payload($user, forceWeather: $request->boolean('refresh'), refresh: $retry['refresh'] ?? [], farmId: $farmId);
        Cache::put(FarmerDashboardService::cacheKey($user, null, $farmId), $payload, now()->addSeconds(self::FARMER_OVERVIEW_CACHE_SECONDS));

        $slots = [];
        foreach ($retry['slots'] ?? self::FARMER_SLOTS as $slot) {
            // A placeholder never replaces itself: the check only answers once the estimate is saved.
            if ($slot === 'harvest' && $retry !== null && ($payload['harvest']['state'] ?? null) === 'loading') {
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

    /** IT overview: everything comes from ItDashboardService and is cached for a short time (the warm-up job refreshes it). */
    public function admin(Request $request, ItDashboardService $it): View
    {
        // Expired or invalidated: the last good overview is shown and rebuilt after the response (the cold build is
        // ~80 Supabase round trips, longer than a request may run).
        $payload = StaleCache::remember($this->adminCacheKey(), self::IT_PAGE_CACHE_SECONDS, fn (): array => $it->payload(), self::ADMIN_LATEST_KEY, self::IT_MAX_STALE_SECONDS);

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
        $fresh = Cache::get($this->adminCacheKey());
        if (is_array($fresh)) {
            return $fresh;
        }
        $latest = Cache::get(self::ADMIN_LATEST_KEY);

        return is_array($latest) && is_array($latest['value'] ?? null) ? $latest['value'] : null;
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

}
