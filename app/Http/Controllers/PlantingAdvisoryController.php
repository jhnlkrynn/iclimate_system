<?php

namespace App\Http\Controllers;

use App\Models\AdvisoryFeedback;
use App\Models\AnalyticsRun;
use App\Models\ExternalWeatherData;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Advisories\AdvisoryPublishedNotifier;
use App\Services\Advisories\AdvisoryWeatherService;
use App\Services\Advisories\LocalFarmGuidanceService;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Advisory\AdvisoryVerificationService;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Risk\HeatMapPanel;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\BusinessClock;
use App\Support\CacheScope;
use App\Support\FarmerAdvisoryView;
use App\Support\FarmerLanguage;
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

    /** The staff list's views: what farmers see now, what waits for approval, or everything on file. */
    private const VIEWS = ['active', 'pending', 'all'];

    /** How many advisories the staff "All" view lists (newest first). */
    private const ALL_VIEW_LIMIT = 100;

    /**
     * Advisories (2026-10): one list. Each item shows a severity colour, the barangay, the valid dates, one sentence of
     * recommendation and one "What to do". Planting is the type shown first; another type appears only as a chip
     * when an advisory of that type exists.
     */
    public function index(Request $request): View
    {
        // A watched analytics run changes by the second, so that view is never reused.
        if ($request->query('run') !== null) {
            return view('advisories.index', $this->indexData($request));
        }

        // Private to the person (their barangay) and reused for a minute; any advisory, feedback or profile change
        // rebuilds it.
        return view('advisories.index', PageCache::forUser(
            (int) $request->user()->id,
            'advisories-index',
            60,
            fn (): array => $this->indexData($request),
            // Everything the page's links send must be listed, or a filtered request is served the unfiltered page.
            PageCache::queryVary($request, ['type', 'advisory_type', 'show', 'target_barangay']) + ['locale' => FarmerLanguage::resolve($request)],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Request $request): array
    {
        $user = $request->user();
        $isFarmer = $user->role === User::ROLE_FARMER;
        $canManage = $this->canManage($request);
        $locale = $isFarmer ? FarmerLanguage::resolve($request) : 'en';
        $farmerBarangay = $isFarmer ? LianBarangays::canonicalName($user->registeredBarangay()) : null;
        // A farmer always reads their own barangay (plus Lian-wide advice); staff may pick one.
        $barangay = $isFarmer ? $farmerBarangay
            : LianBarangays::canonicalName(is_string($request->query('target_barangay')) ? $request->query('target_barangay') : null);
        $show = ! $isFarmer && in_array($request->query('show'), self::VIEWS, true) ? $request->query('show') : 'active';

        $rows = PlantingAdvisory::query()
            ->with(['weatherData'])
            ->when($show === 'active', fn ($query) => $query->active()->latestRuns())
            ->when($show === 'pending', fn ($query) => $query->pendingReview())
            ->when($isFarmer || $barangay, fn ($query) => $query->forBarangay($barangay))
            ->latest('valid_from')->latest('created_at')
            ->when($show === 'all', fn ($query) => $query->limit(self::ALL_VIEW_LIMIT))
            ->get();
        if ($show === 'active') {
            // Each provider check stores its own copy of an unchanged advisory; the list shows it once.
            $rows = $rows->unique(fn (PlantingAdvisory $a): string => $a->title.'|'.$a->displaySummary().'|'.$a->target_barangay)->values();
        }

        $typeOf = fn (PlantingAdvisory $a): string => strtolower((string) ($a->advisory_type ?: $a->type)) ?: PlantingAdvisory::TYPE_PLANTING;
        $counts = $rows->countBy($typeOf);
        // Planting first and always there; the other types only when they are in use.
        $types = collect([PlantingAdvisory::TYPE_PLANTING => (int) ($counts[PlantingAdvisory::TYPE_PLANTING] ?? 0)])
            ->union($counts->except(PlantingAdvisory::TYPE_PLANTING)->sortKeys());
        // ?advisory_type= is the older name of ?type= and still works (saved links).
        $asked = $request->query('type') ?? $request->query('advisory_type');
        $type = match (true) {
            is_string($asked) && $types->has(strtolower($asked)) => strtolower($asked),
            // No planting advisory at all: open on the first kind that has one, never on an empty page.
            $types[PlantingAdvisory::TYPE_PLANTING] === 0 && $types->count() > 1 => (string) $types->keys()->get(1),
            default => PlantingAdvisory::TYPE_PLANTING,
        };
        $rank = ['critical' => 0, 'high' => 1, 'moderate' => 2, 'low' => 3, 'information' => 4];

        return [
            'items' => $rows->filter(fn (PlantingAdvisory $a): bool => $typeOf($a) === $type)
                // Most severe first; within a severity the nearest place first (own barangay, Lian, province, region,
                // national outlook), then newest.
                ->sortBy(fn (PlantingAdvisory $a): array => [$rank[$a->severity] ?? 5, match (true) {
                    $barangay !== null && $a->target_barangay === $barangay => 0,
                    str_contains(strtolower((string) data_get($a->metadata, 'scope_name')), 'national') => 5,
                    default => ['barangay' => 1, 'municipality' => 2, 'province' => 3, 'regional' => 4, 'national' => 5][data_get($a->metadata, 'scope_type', 'municipality')] ?? 2,
                }])
                ->map(fn (PlantingAdvisory $a): array => $this->listItem($a, $locale))->values(),
            'types' => $types,
            'type' => $type,
            'show' => $show,
            'pendingCount' => $isFarmer ? 0 : PlantingAdvisory::query()->pendingReview()->count(),
            'barangay' => $barangay,
            'barangays' => LianBarangays::all(),
            'farmerBarangay' => $farmerBarangay,
            'isFarmer' => $isFarmer,
            'canManage' => $canManage,
            'locale' => $locale,
            // Farmers read MAO posts here as read-only Announcements (no reactions, comments or posting).
            'announcements' => $isFarmer
                ? \App\Models\FeedPost::query()->with('author:id,name')->whereNull('archived_at')->latest()->take(10)->get()
                : collect(),
            'analyticsRun' => $this->requestedAnalyticsRun($request),
            'check' => $this->lastCheck(),
            // Typhoon Safety Check-Ins (moved here from the former Operations page): staff only.
            'checkIns' => $isFarmer ? null : $this->typhoonCheckIns(),
        ];
    }

    /**
     * While a typhoon advisory is in effect: how many farmers answered Safe / Needs Help / not yet, and who needs help.
     *
     * @return array<string, mixed>
     */
    private function typhoonCheckIns(): array
    {
        $event = rescue(fn () => app(TyphoonSafetyService::class)->activeEvent(), null, false);
        if (! $event || ! SchemaCache::hasTable('typhoon_safety_responses')) {
            return ['event' => null];
        }
        $responses = rescue(fn () => TyphoonSafetyResponse::query()->with('farmer.farmerProfile:id,user_id,barangay')->where('event_key', $event['key'])->latest('responded_at')->get(), collect(), false);
        $farmers = rescue(fn (): int => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->count(), 0, false);

        return [
            'event' => $event,
            'safe' => $responses->where('status', TyphoonSafetyResponse::STATUS_SAFE)->count(),
            'needs_help' => $responses->where('status', TyphoonSafetyResponse::STATUS_NEEDS_HELP)->values(),
            'no_response' => max(0, $farmers - $responses->pluck('user_id')->unique()->count()),
        ];
    }

    /**
     * Whether the saved advisories are current: the scheduler checks PAGASA and the forecast, and the page only says
     * what the last check found. An unreachable source is never shown as "no advisories".
     *
     * @return array{state: string, checked_at: ?Carbon}
     */
    private function lastCheck(): array
    {
        $pagasa = Cache::get('pagasa-advisories:status', Cache::get(PagasaAdvisoryService::CHECK_KEY, []));
        $pagasa = is_array($pagasa) ? $pagasa : [];
        $local = Cache::get(LocalFarmGuidanceService::STATUS_KEY, []);
        $local = is_array($local) ? $local : [];

        $pagasaDown = $pagasa !== [] && ! ($pagasa['ok'] ?? count($pagasa['errors'] ?? []) < ($pagasa['sources_checked'] ?? 0));
        $forecastDown = $local !== [] && ! ($local['source_connected'] ?? ($local['available'] ?? false));
        $times = array_filter([$pagasa['checked_at'] ?? null, $local['checked_at'] ?? null]);

        return [
            'state' => match (true) {
                $pagasa === [] && $local === [] => 'unchecked',
                $pagasaDown && ($forecastDown || $local === []) => 'unavailable',
                $pagasaDown => 'pagasa_down',
                $forecastDown => 'forecast_down',
                default => 'ok',
            },
            'checked_at' => $times === [] ? null : rescue(fn () => Carbon::parse(max($times)), null, false),
        ];
    }

    /**
     * One row of the list: colour, place, dates, one sentence of recommendation and one "What to do".
     *
     * @return array<string, mixed>
     */
    private function listItem(PlantingAdvisory $advisory, string $locale): array
    {
        $view = new FarmerAdvisoryView($advisory);
        $tz = BusinessClock::timezone();
        $from = $advisory->valid_from?->copy()->timezone($tz);
        $until = $advisory->valid_until?->copy()->timezone($tz);
        $level = match ($advisory->severity) {
            PlantingAdvisory::SEVERITY_CRITICAL, PlantingAdvisory::SEVERITY_HIGH => 'high',
            PlantingAdvisory::SEVERITY_MODERATE => 'moderate',
            default => 'low',
        };
        $activityDate = rescue(fn () => filled(data_get($advisory->metadata, 'mao_activity_date')) ? Carbon::parse(data_get($advisory->metadata, 'mao_activity_date')) : null, null, false);

        return [
            'advisory' => $advisory,
            'level' => $level,
            'level_label' => trans('farmer.heat_map.level.'.$level, [], $locale),
            // An official bulletin keeps its own geographic scope (for example "Batangas Province"): never renamed to Lian.
            'area' => $advisory->target_barangay ?: (data_get($advisory->metadata, 'scope_name') ?: trans('farmer.advisories_page.all_lian', [], $locale)),
            'valid' => match (true) {
                $from && $until => $from->isSameDay($until) ? $from->format('M j, Y') : $from->format('M j').' – '.$until->format('M j, Y'),
                (bool) $until => trans('farmer.advisories_page.until', ['date' => $until->format('M j, Y')], $locale),
                (bool) $from => trans('farmer.advisories_page.from', ['date' => $from->format('M j, Y')], $locale),
                default => null,
            },
            'recommendation' => self::oneSentence($view->farmerEffect()),
            'action' => self::oneSentence($view->farmerAction()),
            'activity' => filled(data_get($advisory->metadata, 'mao_activity')) ? (string) data_get($advisory->metadata, 'mao_activity') : null,
            'activity_date' => $activityDate?->format('M j, Y'),
            'official' => $view->isPagasa(),
            'source' => $view->farmerSourceLabel(),
        ];
    }

    /** The first sentence of a text, so every row stays one line of advice. */
    public static function oneSentence(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (preg_match('/^(.+?[.!?])(\s|$)/u', $text, $m)) {
            $text = $m[1];
        }

        return (string) str($text)->limit(220);
    }

    /**
     * Header figures shared by both advisory pages: seven separate queries
     * (~300 ms each against Supabase) that only change when an advisory or the
     * weather record is saved. Cached for a minute, and changes to the data it
     * reads retire it through the scoped invalidation token.
     *
     * @return array<string, mixed>
     */
    private function advisorySummary(): array
    {
        return Cache::remember('advisories:summary:'.CacheScope::token('advisories-summary'), now()->addSeconds(60), fn (): array => [
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
            'advisories' => $this->filteredQuery($request)->latest('created_at')->paginate(CrudController::PER_PAGE)->withQueryString(),
            ...$this->advisorySummary(),
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
            'analyticsRun' => $this->requestedAnalyticsRun($request),
        ]);
    }

    /**
     * The MAO's form opens already filled in from the system's recommendation for the barangay (this week's Heat Map
     * rating), so the MAO reviews it, changes what is needed, then approves and publishes.
     */
    public function create(Request $request, HeatMapPanel $panel): View
    {
        $this->authorizeManage($request);
        $suggestions = $this->suggestions($panel);
        $barangay = LianBarangays::canonicalName(is_string($request->query('barangay')) ? $request->query('barangay') : null);
        $suggested = $suggestions[$barangay ?? ''] ?? null;

        return view('advisories.form', [
            'advisory' => new PlantingAdvisory([
                'status' => PlantingAdvisory::STATUS_PENDING_REVIEW,
                'advisory_type' => PlantingAdvisory::TYPE_PLANTING,
                'target_barangay' => $barangay,
                'message' => $suggested['recommendation'] ?? null,
                'recommended_action' => $suggested['action'] ?? null,
                'severity' => $suggested['severity'] ?? PlantingAdvisory::SEVERITY_LOW,
                'valid_until' => BusinessClock::today()->addDays(6),
            ]),
            'action' => route('planting-advisories.store'),
            'method' => 'POST',
            'types' => self::TYPES,
            'barangays' => LianBarangays::all(),
            'suggestions' => $suggestions,
            'prefilled' => $suggested !== null,
        ]);
    }

    /**
     * The system's recommendation per barangay ('' = all of Lian, taken from the highest-rated barangay), from the
     * same saved forecast rating the Heat Map shows. Empty when nothing is saved yet.
     *
     * @return array<string, array{recommendation: string, action: string, severity: string}>
     */
    private function suggestions(HeatMapPanel $panel): array
    {
        $week = rescue(fn (): array => $panel->ranges('en')['week'] ?? [], [], false);
        $rank = ['high' => 3, 'moderate' => 2, 'low' => 1];
        $suggestions = [];
        $worst = null;
        foreach ((array) ($week['barangays'] ?? []) as $name => $b) {
            if (! isset($rank[$b['level']])) {
                continue;
            }
            $suggestions[$name] = ['recommendation' => $b['meaning'], 'action' => $b['action'], 'severity' => $b['level']];
            if ($worst === null || $rank[$b['level']] > $rank[$suggestions[$worst]['severity']]) {
                $worst = $name;
            }
        }

        return $worst === null ? [] : ['' => $suggestions[$worst]] + $suggestions;
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $payload = $this->payload($request, $data);
        $published = $payload['status'] === PlantingAdvisory::STATUS_PUBLISHED;

        $advisory = PlantingAdvisory::query()->create($payload + [
            'posted_by' => $request->user()->id,
            'generated_automatically' => false,
            'requires_review' => ! $published,
            'published_at' => $published ? now() : null,
            'approved_by' => $published ? $request->user()->id : null,
            'approved_at' => $published ? now() : null,
        ]);
        SystemAuditLogger::forModel('created', $advisory, $request, [
            'target_barangay' => $advisory->target_barangay,
            'status' => $advisory->status,
        ]);

        return redirect()->route('planting-advisories.show', $advisory)
            ->with('success', $published ? $this->publishedMessage($advisory) : 'Advisory saved for review. Farmers do not see it yet.');
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
            'barangays' => LianBarangays::all(),
            'suggestions' => [],
            'prefilled' => false,
        ]);
    }

    public function update(Request $request, PlantingAdvisory $plantingAdvisory): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);

        $payload = $this->payload($request, $data, $plantingAdvisory);
        $nowPublished = $payload['status'] === PlantingAdvisory::STATUS_PUBLISHED;
        $newlyPublished = $nowPublished && $plantingAdvisory->status !== PlantingAdvisory::STATUS_PUBLISHED;

        $plantingAdvisory->update($payload + [
            'published_at' => $nowPublished ? ($plantingAdvisory->published_at ?? now()) : $plantingAdvisory->published_at,
        ] + ($newlyPublished ? ['requires_review' => false, 'approved_by' => $request->user()->id, 'approved_at' => now()] : []));
        SystemAuditLogger::forModel('updated', $plantingAdvisory, $request, [
            'target_barangay' => $plantingAdvisory->target_barangay,
            'status' => $plantingAdvisory->status,
        ]);

        return redirect()->route('planting-advisories.show', $plantingAdvisory)
            ->with('success', $newlyPublished ? $this->publishedMessage($plantingAdvisory) : 'Advisory updated successfully.');
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

        $newlyPublished = $advisory->status !== PlantingAdvisory::STATUS_PUBLISHED;

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

        return back()->with('success', $newlyPublished ? $this->publishedMessage($advisory) : 'Advisory Published. The advisory is now visible to selected farmers.');
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
        // PAGASA plus the local weather refresh can take longer than PHP's default 30 seconds on a slow line.
        if (! app()->runningInConsole()) {
            @set_time_limit(75);
        }

        $requestedBarangay = is_string($request->input('target_barangay'))
            ? LianBarangays::canonicalName($request->input('target_barangay'))
            : null;
        $barangay = $request->user()->role === User::ROLE_FARMER
            ? ($requestedBarangay ?? $request->user()->registeredBarangay())
            : $requestedBarangay;
        $summary = $pagasa->fetchAndStore($this->canManage($request), (int) config('services.pagasa.request_budget', 15));
        $weather = $local->refresh($barangay);
        // The provider services retire their own dependent cache scopes; keep unrelated
        // prepared pages warm after a refresh.

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
            // The form's order: Barangay, Recommendation, What to do, Valid until, Severity, then the optional MAO Activity.
            'target_barangay' => ['nullable', 'string', Rule::in(LianBarangays::all())],
            'message' => ['required_without:content', 'nullable', 'string', 'max:2000'],
            'content' => ['required_without:message', 'nullable', 'string', 'max:2000'],
            'recommended_action' => ['nullable', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'severity' => ['nullable', Rule::in(self::SEVERITIES)],
            'mao_activity' => ['nullable', 'string', 'max:255'],
            'mao_activity_date' => ['nullable', 'date', 'required_with:mao_activity'],
            // Filled in by the system when the form leaves them out: a planting advisory, titled by its barangay.
            'title' => ['nullable', 'string', 'max:255'],
            'advisory_type' => ['nullable', Rule::in(self::TYPES)],
            'type' => ['nullable', Rule::in(['Climate', 'Planting', 'Harvesting', 'Irrigation', 'climate', 'planting', 'harvesting', 'irrigation'])],
            'status' => ['nullable', Rule::in(array_merge(self::STATUSES, ['Draft', 'Published']))],
        ], [
            'message.required_without' => 'Write the recommendation.',
            'mao_activity_date.required_with' => 'Choose the date of the MAO activity.',
        ]);

        $data['message'] = $data['message'] ?? $data['content'];
        $data['advisory_type'] = $data['advisory_type'] ?? (filled($data['type'] ?? null) ? strtolower((string) $data['type']) : PlantingAdvisory::TYPE_PLANTING);
        $data['severity'] = $data['severity'] ?? 'information';
        $data['status'] = $data['status'] ?? PlantingAdvisory::STATUS_PENDING_REVIEW;
        $data['title'] = filled($data['title'] ?? null)
            ? $data['title']
            : str($data['advisory_type'])->headline().' Advisory – '.(($data['target_barangay'] ?? null) ?: 'All of Lian');

        return $data;
    }

    private function payload(Request $request, array $data, ?PlantingAdvisory $existing = null): array
    {
        $status = match ($data['status']) {
            'Published' => PlantingAdvisory::STATUS_PUBLISHED,
            'Draft' => PlantingAdvisory::STATUS_PENDING_REVIEW,
            default => $data['status'],
        };

        // The optional MAO Activity (for example a seed distribution day) is kept with the advisory and shown on the Calendar.
        $metadata = Arr::except((array) ($existing?->metadata ?? []), ['mao_activity', 'mao_activity_date']);
        if (filled($data['mao_activity'] ?? null)) {
            $metadata['mao_activity'] = trim((string) $data['mao_activity']);
            $metadata['mao_activity_date'] = Carbon::parse($data['mao_activity_date'])->toDateString();
        }

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
            'valid_from' => $data['valid_from'] ?? ($existing?->valid_from ?? now()),
            'valid_until' => $this->validUntil($data['valid_until'] ?? null) ?? now()->addDays(7),
            'source' => 'MAO-Reviewed Advisory',
            'metadata' => $metadata === [] ? null : $metadata,
        ];
    }

    /** A day picked in the form means "until the end of that day" in Lian; a full date and time is kept as given. */
    private function validUntil(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            ? Carbon::parse($value, BusinessClock::timezone())->endOfDay()->utc()
            : Carbon::parse($value);
    }

    /** Alerts the farmers the advisory is for (once, when it is first published) and says how many were told. */
    private function publishedMessage(PlantingAdvisory $advisory): string
    {
        $count = rescue(fn (): int => app(AdvisoryPublishedNotifier::class)->notify($advisory), 0);

        return 'Advisory published. '.($count === 1 ? '1 farmer was' : number_format($count).' farmers were').' notified.';
    }

    private function priorityForSeverity(string $severity): int
    {
        return ['information' => 20, 'low' => 35, 'moderate' => 55, 'high' => 80, 'critical' => 100][$severity] ?? 50;
    }

    /** Advisories are written, approved and published by the MAO. IT reads them (view-only since 2026-10). */
    private function canManage(Request $request): bool
    {
        return $request->user()->role === User::ROLE_MAO;
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
