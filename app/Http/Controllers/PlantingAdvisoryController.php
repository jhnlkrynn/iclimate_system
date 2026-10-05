<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryFeedback;
use App\Models\AnalyticsRun;
use App\Models\ExternalWeatherData;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Advisories\AdvisoryWeatherService;
use App\Services\Advisories\LocalFarmGuidanceService;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Advisory\AdvisoryVerificationService;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\BusinessClock;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use App\Support\PageCache;
use App\Support\SchemaCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantingAdvisoryController extends Controller
{
    private const TYPES = ['climate', 'planting', 'harvesting', 'irrigation'];

    private const SEVERITIES = ['information', 'low', 'moderate', 'high', 'critical'];

    private const STATUSES = ['pending_review', 'published', 'expired', 'rejected', 'archived'];

    public function index(Request $request, TyphoonSafetyService $typhoonSafety, LianBarangayWeatherService $barangayWeather): View
    {
        // A watched analytics run changes by the second, so that view is never reused.
        if ($request->query('run') !== null) {
            return view('advisories.index', $this->indexData($request, $typhoonSafety, $barangayWeather));
        }

        // Private to the person (their barangay, their own typhoon reply) and reused for a minute; any
        // advisory, feedback or profile change rebuilds it. About eight database round trips to build.
        return view('advisories.index', PageCache::forUser(
            (int) $request->user()->id,
            'advisories-index',
            60,
            fn (): array => $this->indexData($request, $typhoonSafety, $barangayWeather),
            // Every filter the page's form sends must be listed, or a filtered request is served the unfiltered page.
            PageCache::queryVary($request, ['page', 'type', 'advisory_type', 'severity', 'status', 'barangay', 'target_barangay', 'search', 'q']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Request $request, TyphoonSafetyService $typhoonSafety, LianBarangayWeatherService $barangayWeatherService): array
    {
        $pagasaSummary = Cache::get('pagasa-advisories:status', Cache::get(PagasaAdvisoryService::CHECK_KEY, []));
        $isFarmer = $request->user()->role === User::ROLE_FARMER;
        $farmerBarangay = $isFarmer ? $request->user()->registeredBarangay() : null;
        // Farmers get two short lists: their own barangay first, then municipality-wide advice.
        $rawTargetBarangay = is_string($request->query('target_barangay')) ? $request->query('target_barangay') : null;
        $selectedBarangay = LianBarangays::canonicalName($rawTargetBarangay) ?? $farmerBarangay;
        $farmerScope = fn ($query) => $query->active()->forBarangay($selectedBarangay)->latestRuns();
        $myBarangayAdvisories = collect();
        $lianWideAdvisories = collect();
        $officialPagasaAdvisories = collect();
        $additionalPagasaInformation = collect();
        $barangayAdvisory = null;
        $query = $this->filteredQuery($request);

        if ($isFarmer) {
            $farmerQuery = $farmerScope($query);
            $all = $farmerQuery->latest('valid_from')->latest('created_at')->get()->unique('id');
            $official = $all->filter(fn (PlantingAdvisory $advisory) => $advisory->isOfficialPagasa());
            $additionalPagasaInformation = $official->filter(fn (PlantingAdvisory $advisory) => str_contains(
                strtolower((string) data_get($advisory->metadata, 'scope_name')),
                'national outlook'
            ))->values();
            $officialPagasaAdvisories = $official->reject(fn (PlantingAdvisory $advisory) => $additionalPagasaInformation->contains('id', $advisory->id))
                ->sortBy(fn ($advisory) => ['barangay' => 1, 'municipality' => 2, 'province' => 3, 'regional' => 4][data_get($advisory->metadata, 'scope_type')] ?? 5)->values();
            $local = $all->reject(fn (PlantingAdvisory $advisory) => $advisory->isOfficialPagasa());
            $myBarangayAdvisories = $selectedBarangay ? $local->filter(fn ($advisory) => $advisory->target_barangay === $selectedBarangay)->values() : collect();
            $barangayAdvisory = $myBarangayAdvisories->first(fn (PlantingAdvisory $advisory) => data_get($advisory->metadata, 'source_type') === 'iclimate');
            $myBarangayAdvisories = $myBarangayAdvisories->reject(fn (PlantingAdvisory $advisory) => $barangayAdvisory && $advisory->is($barangayAdvisory))->values();
            $lianWideAdvisories = $local->reject(fn ($advisory) => $selectedBarangay !== null && $advisory->target_barangay === $selectedBarangay)
                ->sortBy(fn ($a) => ['municipality' => 1, 'province' => 2, 'regional' => 3][data_get($a->metadata, 'scope_type', 'municipality')] ?? 1)->values();
        }
        $barangayWeather = $selectedBarangay ? $barangayWeatherService->cachedForBarangay($selectedBarangay) : null;
        $activeTyphoonSafetyEvent = $isFarmer
            ? $typhoonSafety->activeEvent()
            : null;
        $summary = Arr::only($this->advisorySummary(), ['lastWeather', 'latestPagasaAdvisory', 'activeCount', 'pagasaAdvisoryCount']);
        $pagasaCheck = $pagasaSummary;
        $localCheck = Cache::get(LocalFarmGuidanceService::STATUS_KEY, []);
        $pagasaCheckedAt = is_array($pagasaCheck) ? ($pagasaCheck['checked_at'] ?? null) : null;
        $checkedAt = $pagasaCheckedAt ? Carbon::parse($pagasaCheckedAt) : null;
        $pagasaFailed = ! empty($pagasaCheck) && ! ($pagasaCheck['ok'] ?? count($pagasaCheck['errors'] ?? []) < ($pagasaCheck['sources_checked'] ?? 0));
        $pagasaComplete = ! empty($pagasaCheck) && ($pagasaCheck['sources_checked'] ?? 0) > 0 && empty($pagasaCheck['errors']);

        if ($isFarmer) {
            // These counts cover every visible, de-duplicated advisory across the clearly separated sections.
            $summary['activeCount'] = $all->count();
            $summary['pagasaAdvisoryCount'] = $official->count();
        }

        return [
            'advisories' => $isFarmer ? collect() : $query->latest('valid_from')->paginate(10)->withQueryString(),
            'myBarangayAdvisories' => $myBarangayAdvisories,
            'lianWideAdvisories' => $lianWideAdvisories,
            'officialPagasaAdvisories' => $officialPagasaAdvisories,
            'additionalPagasaInformation' => $additionalPagasaInformation,
            'barangayAdvisory' => $barangayAdvisory,
            'barangayWeather' => $barangayWeather,
            'farmerBarangay' => $farmerBarangay,
            'selectedBarangay' => $selectedBarangay,
            ...$summary,
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponse' => SchemaCache::hasTable('typhoon_safety_responses') && $activeTyphoonSafetyEvent
                ? TyphoonSafetyResponse::query()
                    ->where('user_id', $request->user()->id)
                    ->where('event_key', $activeTyphoonSafetyEvent['key'])
                    ->first()
                : null,
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            // Farmers only ever see published advisories, so the other statuses would always return nothing.
            'statuses' => $isFarmer ? [PlantingAdvisory::STATUS_PUBLISHED] : self::STATUSES,
            'barangays' => LianBarangays::all(),
            'canManage' => $this->canManage($request),
            'pagasaSummary' => $pagasaSummary,
            'advisoryCheck' => [
                'checked_at' => $checkedAt,
                'last_successful_check' => is_array($pagasaCheck) && ! empty($pagasaCheck['last_successful_check'])
                    ? Carbon::parse($pagasaCheck['last_successful_check'])
                    : null,
                'unavailable' => $pagasaFailed,
                'pagasa_connected' => ! $pagasaFailed && ! empty($pagasaCheck),
                'pagasa_complete' => $pagasaComplete,
                'weather_available' => $localCheck['available'] ?? ($summary['lastWeather'] && ! $summary['lastWeather']->isOutdated()),
                'weather_checked' => ! empty($localCheck),
                'weather_unavailable' => ! empty($localCheck) && ! ($localCheck['source_connected'] ?? false),
                'weather_checked_at' => ! empty($localCheck['checked_at']) ? Carbon::parse($localCheck['checked_at']) : null,
                'needs_refresh' => ! Cache::has(PagasaAdvisoryService::CHECK_KEY) || empty($localCheck)
                    || Carbon::parse($localCheck['checked_at'])->lt(now()->subMinutes(10))
                    || ($selectedBarangay && LocalFarmGuidanceService::needsBarangayRefresh($selectedBarangay)),
                'all_checked' => $pagasaComplete && ! empty($localCheck) && ($localCheck['source_connected'] ?? false),
            ],
            'analyticsRun' => $this->requestedAnalyticsRun($request),
        ];
    }

    /**
     * Header figures shared by both advisory pages: seven separate queries
     * (~300 ms each against Supabase) that only change when an advisory or the
     * weather record is saved. Cached for a minute, and any advisory save
     * retires it at once (DashboardData::version()).
     *
     * @return array<string, mixed>
     */
    private function advisorySummary(): array
    {
        return Cache::remember('advisories:summary:v'.DashboardData::version(), now()->addSeconds(60), fn (): array => [
            'lastWeather' => ExternalWeatherData::query()->latest('fetched_at')->first(),
            'latestPagasaAdvisory' => PlantingAdvisory::query()->fromPagasaOnline()->latest('published_at')->get()
                ->first(fn (PlantingAdvisory $advisory) => $advisory->isOfficialPagasa()),
            'activeCount' => PlantingAdvisory::query()->active()->count(),
            'pagasaAdvisoryCount' => PlantingAdvisory::query()->active()->fromPagasaOnline()->count(),
            'pendingCount' => PlantingAdvisory::query()->pendingReview()->count(),
            'highRiskCount' => PlantingAdvisory::query()->whereIn('severity', ['high', 'critical'])->whereIn('status', ['published', 'pending_review'])->count(),
            'expiredTodayCount' => PlantingAdvisory::query()->where('status', 'expired')->where('updated_at', '>=', BusinessClock::todayUtc())->where('updated_at', '<', BusinessClock::todayUtc()->addDay())->count(),
        ]);
    }

    public function adminIndex(Request $request): View
    {
        $this->authorizeManage($request);

        return view('advisories.management', [
            'advisories' => $this->filteredQuery($request)->latest('created_at')->paginate(10)->withQueryString(),
            ...$this->advisorySummary(),
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
            'analyticsRun' => $this->requestedAnalyticsRun($request),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage($request);

        return view('advisories.form', [
            'advisory' => new PlantingAdvisory(['status' => PlantingAdvisory::STATUS_PENDING_REVIEW]),
            'action' => route('planting-advisories.store'),
            'method' => 'POST',
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $advisory = PlantingAdvisory::query()->create($this->payload($request, $data) + [
            'posted_by' => $request->user()->id,
            'generated_automatically' => false,
            'requires_review' => $data['status'] !== PlantingAdvisory::STATUS_PUBLISHED,
            'published_at' => $data['status'] === PlantingAdvisory::STATUS_PUBLISHED ? now() : null,
        ]);
        SystemAuditLogger::forModel('created', $advisory, $request, [
            'target_barangay' => $advisory->target_barangay,
            'status' => $advisory->status,
        ]);

        return redirect()->route('planting-advisories.show', $advisory)->with('success', 'Advisory saved successfully.');
    }

    public function show(Request $request, PlantingAdvisory $plantingAdvisory, AdvisoryVerificationService $verification, AdvisoryWeatherService $advisoryWeather): View
    {
        if ($request->user()->role === User::ROLE_FARMER && (! $plantingAdvisory->isActive() || ! $this->isVisibleToFarmer($plantingAdvisory, $request->user()))) {
            abort(403);
        }

        $feedbackFeatureReady = SchemaCache::hasTable('advisory_feedback');
        $relations = ['weatherData', 'rule', 'approvedBy', 'postedBy'];

        if ($feedbackFeatureReady) {
            array_push($relations, 'feedback.user', 'feedback.respondedBy');
        }

        $plantingAdvisory->load($relations);

        // Keep advisory details available during a rolling deployment even if
        // the optional feedback migration has not reached this database yet.
        if (! $feedbackFeatureReady) {
            $plantingAdvisory->setRelation('feedback', collect());
        }

        // The Advisories page opens an advisory in a popup (?modal=1): the same data, without the page layout.
        // A separate URL, so a browser never shows the popup body in place of the full page.
        return view($request->boolean('modal') ? 'advisories._modal' : 'advisories.show', [
            'advisory' => $plantingAdvisory,
            'canManage' => $this->canManage($request),
            'verification' => $verification->score($plantingAdvisory),
            // Forecast for THIS advisory's place and validity window (never the current weather).
            'advisoryWeather' => $advisoryWeather->forAdvisory($plantingAdvisory),
            'feedbackFeatureReady' => $feedbackFeatureReady,
            'myFeedback' => $feedbackFeatureReady && $request->user()->role === User::ROLE_FARMER
                ? $plantingAdvisory->feedback->firstWhere('user_id', $request->user()->id)
                : null,
        ]);
    }

    /**
     * A farmer's "Helpful / Not Helpful" reaction on a published advisory —
     * one vote per farmer per advisory, changeable, with an optional
     * comment MAO can respond to (see respondToFeedback()). Serves both the
     * advisory show page's HTML form and the farmer dashboard's inline AJAX
     * widget, which expects a JSON response.
     */
    public function feedback(Request $request, PlantingAdvisory $plantingAdvisory): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_FARMER, 403);

        if (! SchemaCache::hasTable('advisory_feedback')) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Advisory feedback is temporarily unavailable while the database update is completed.'], 503);
            }

            return back()->with('error', 'Advisory feedback is temporarily unavailable while the database update is completed.');
        }

        $data = $request->validate([
            'is_helpful' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = AdvisoryFeedback::query()->updateOrCreate(
            ['planting_advisory_id' => $plantingAdvisory->id, 'user_id' => $request->user()->id],
            ['is_helpful' => $data['is_helpful'], 'comment' => $data['comment'] ?? null],
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'is_helpful' => $feedback->is_helpful]);
        }

        return back()->with('success', 'Thanks — your feedback on this advisory was saved.');
    }

    public function respondToFeedback(Request $request, int $advisoryFeedback): RedirectResponse
    {
        $this->authorizeManage($request);

        if (! SchemaCache::hasTable('advisory_feedback')) {
            return back()->with('error', 'Advisory feedback is temporarily unavailable while the database update is completed.');
        }

        $advisoryFeedback = AdvisoryFeedback::query()->findOrFail($advisoryFeedback);

        $data = $request->validate([
            'mao_response' => ['required', 'string', 'max:2000'],
        ]);

        $advisoryFeedback->update([
            'mao_response' => $data['mao_response'],
            'mao_responded_at' => now(),
            'mao_responded_by' => $request->user()->id,
        ]);

        Notification::query()->create([
            'user_id' => $advisoryFeedback->user_id,
            'title' => 'MAO responded to your advisory feedback',
            'message' => $data['mao_response'],
            'type' => 'Advisory',
            'is_read' => false,
        ]);

        return back()->with('success', 'Response sent to the farmer.');
    }

    public function edit(Request $request, PlantingAdvisory $plantingAdvisory): View
    {
        $this->authorizeManage($request);

        return view('advisories.form', [
            'advisory' => $plantingAdvisory,
            'action' => route('planting-advisories.update', $plantingAdvisory),
            'method' => 'PUT',
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
        ]);
    }

    public function update(Request $request, PlantingAdvisory $plantingAdvisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $plantingAdvisory->update($this->payload($request, $data) + [
            'published_at' => $data['status'] === PlantingAdvisory::STATUS_PUBLISHED
                ? ($plantingAdvisory->published_at ?? now())
                : $plantingAdvisory->published_at,
        ]);
        SystemAuditLogger::forModel('updated', $plantingAdvisory, $request, [
            'target_barangay' => $plantingAdvisory->target_barangay,
            'status' => $plantingAdvisory->status,
        ]);

        return redirect()->route('planting-advisories.show', $plantingAdvisory)->with('success', 'Advisory updated successfully.');
    }

    public function destroy(Request $request, PlantingAdvisory $plantingAdvisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $plantingAdvisory->update(['status' => PlantingAdvisory::STATUS_ARCHIVED]);
        SystemAuditLogger::forModel('archived', $plantingAdvisory, $request);

        return redirect()->route('planting-advisories.index')->with('success', 'Advisory archived.');
    }

    public function approve(Request $request, PlantingAdvisory $advisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'recommended_action' => ['nullable', 'string'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
        ]);

        $advisory->update(array_filter([
            'title' => $data['title'] ?? null,
            'content' => $data['message'] ?? null,
            'message' => $data['message'] ?? null,
            'recommended_action' => $data['recommended_action'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
        ], fn ($value) => $value !== null) + [
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'requires_review' => false,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'published_at' => now(),
            'source' => 'MAO-Reviewed Advisory',
        ]);
        SystemAuditLogger::forModel('published', $advisory, $request, [
            'target_barangay' => $advisory->target_barangay,
        ]);

        return back()->with('success', 'Advisory Published. The advisory is now visible to selected farmers.');
    }

    public function reject(Request $request, PlantingAdvisory $advisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $advisory->update([
            'status' => PlantingAdvisory::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
        ]);
        SystemAuditLogger::forModel('rejected', $advisory, $request, [
            'reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Advisory rejected.');
    }

    public function publish(Request $request, PlantingAdvisory $advisory): RedirectResponse
    {
        return $this->approve($request, $advisory);
    }

    public function archive(Request $request, PlantingAdvisory $advisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $advisory->update(['status' => PlantingAdvisory::STATUS_ARCHIVED]);
        SystemAuditLogger::forModel('archived', $advisory, $request);

        return back()->with('success', 'Advisory archived.');
    }

    public function refreshWeather(Request $request, AnalyticsDispatcher $analytics): RedirectResponse
    {
        $this->authorizeManage($request);
        $run = $analytics->dispatch('advisory_ai', ['reason' => 'weather_refresh'], $request->user());

        return redirect()->route('planting-advisories.index', ['run' => $run->uuid])
            ->with('success', 'Advisory analysis was queued for Python processing.');
    }

    public function regenerate(Request $request, AnalyticsDispatcher $analytics): RedirectResponse
    {
        $this->authorizeManage($request);
        $run = $analytics->dispatch('advisory_ai', ['reason' => 'manual_regeneration'], $request->user());

        return redirect()->route('management.advisories.index', ['run' => $run->uuid])
            ->with('success', 'Advisory generation was queued for Python processing.');
    }

    public function refreshPagasa(Request $request, PagasaAdvisoryService $pagasa, LocalFarmGuidanceService $local): RedirectResponse|JsonResponse
    {
        // Anyone signed in may refresh (the route is throttled). MAO and IT always fetch the PAGASA pages again;
        // a farmer reuses a fetch from the last few minutes (services.pagasa.cache_minutes) so PAGASA is not hammered.
        // This used to only queue a Python "advisory_ai" job, which never called PAGASA and needed a queue
        // worker plus the ML service to be running.
        $requestedBarangay = is_string($request->input('target_barangay'))
            ? LianBarangays::canonicalName($request->input('target_barangay'))
            : null;
        $barangay = $request->user()->role === User::ROLE_FARMER
            ? ($requestedBarangay ?? $request->user()->registeredBarangay())
            : $requestedBarangay;
        $summary = $pagasa->fetchAndStore($this->canManage($request));
        $weather = $local->refresh($barangay);
        PageCache::flushShared();

        if (! ($summary['ok'] ?? false) && ! ($weather['available'] ?? false)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Advisory information is temporarily unavailable. Please check official PAGASA and LGU announcements.', 'summary' => $summary], 503);
            }

            return redirect()->route('planting-advisories.index')
                ->with('error', 'Could not reach PAGASA right now. Please try again later.');
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Advisories refreshed.', 'summary' => $summary, 'weather' => $weather]);
        }

        return redirect()->route('planting-advisories.index')
            ->with('success', $summary['message']);
    }

    private function requestedAnalyticsRun(Request $request): ?AnalyticsRun
    {
        $run = is_string($request->query('run')) ? trim($request->query('run')) : '';
        if ($run === '') {
            return null;
        }

        return AnalyticsRun::query()
            ->where('uuid', $run)
            ->where('type', 'advisory_ai')
            ->where('requested_by', $request->user()->id)
            ->first();
    }

    private function filteredQuery(Request $request)
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $advisoryType = is_string($request->query('advisory_type')) ? trim($request->query('advisory_type')) : '';
        $type = is_string($request->query('type')) ? trim($request->query('type')) : '';
        $status = is_string($request->query('status')) ? trim($request->query('status')) : '';
        $severity = is_string($request->query('severity')) ? trim($request->query('severity')) : '';
        $targetBarangay = is_string($request->query('target_barangay')) ? trim($request->query('target_barangay')) : '';

        return PlantingAdvisory::query()
            ->with(['weatherData', 'rule'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('target_barangay', 'like', "%{$search}%");
                });
            })
            ->when($advisoryType !== '', fn ($query) => $query->where('advisory_type', $advisoryType))
            ->when($type !== '', fn ($query) => $query->where('advisory_type', strtolower($type)))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($severity !== '', fn ($query) => $query->where('severity', $severity))
            ->when($targetBarangay !== '', function ($query) use ($targetBarangay) {
                $query->forBarangay($targetBarangay);
            });
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required_without:content', 'nullable', 'string'],
            'content' => ['required_without:message', 'nullable', 'string'],
            'recommended_action' => ['nullable', 'string'],
            'advisory_type' => ['required_without:type', 'nullable', Rule::in(self::TYPES)],
            'type' => ['required_without:advisory_type', 'nullable', Rule::in(['Climate', 'Planting', 'Harvesting', 'Irrigation', 'climate', 'planting', 'harvesting', 'irrigation'])],
            'target_barangay' => ['nullable', 'string', Rule::in(LianBarangays::all())],
            'severity' => ['nullable', Rule::in(self::SEVERITIES)],
            'status' => ['required', Rule::in(array_merge(self::STATUSES, ['Draft', 'Published']))],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $data['message'] = $data['message'] ?? $data['content'];
        $data['advisory_type'] = $data['advisory_type'] ?? strtolower((string) $data['type']);
        $data['severity'] = $data['severity'] ?? 'information';

        return $data;
    }

    private function payload(Request $request, array $data): array
    {
        $status = match ($data['status']) {
            'Published' => PlantingAdvisory::STATUS_PUBLISHED,
            'Draft' => PlantingAdvisory::STATUS_PENDING_REVIEW,
            default => $data['status'],
        };

        return [
            'title' => $data['title'],
            'content' => $data['message'],
            'message' => $data['message'],
            'summary' => str($data['message'])->limit(180)->toString(),
            'recommended_action' => $data['recommended_action'] ?? null,
            'type' => str($data['advisory_type'])->headline()->toString(),
            'advisory_type' => $data['advisory_type'],
            'target_barangay' => $data['target_barangay'] ?? null,
            'target_scope' => empty($data['target_barangay']) ? 'municipality' : 'barangay',
            'severity' => $data['severity'],
            'priority' => $this->priorityForSeverity($data['severity']),
            'status' => $status,
            'valid_from' => $data['valid_from'] ?? now(),
            'valid_until' => $data['valid_until'] ?? now()->addDays(7),
            'source' => 'MAO-Reviewed Advisory',
        ];
    }

    private function priorityForSeverity(string $severity): int
    {
        return ['information' => 20, 'low' => 35, 'moderate' => 55, 'high' => 80, 'critical' => 100][$severity] ?? 50;
    }

    private function canManage(Request $request): bool
    {
        return in_array($request->user()->role, [User::ROLE_MAO, User::ROLE_IT_EXPERT], true);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function isVisibleToFarmer(PlantingAdvisory $advisory, User $farmer): bool
    {
        if ($advisory->isOfficialPagasa() || data_get($advisory->metadata, 'source_type') === 'iclimate') {
            return true; // Public weather advice can be opened after using Target Barangay.
        }

        return empty($advisory->target_barangay)
            || $advisory->target_scope === 'municipality'
            || $advisory->target_barangay === $farmer->registeredBarangay();
    }
}
