<?php

namespace App\Http\Controllers;

use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\Advisories\AdvisoryGenerationService;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\SystemAuditLogger;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\OpenMeteoService;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantingAdvisoryController extends Controller
{
    private const TYPES = ['climate', 'planting', 'harvesting', 'irrigation'];

    private const SEVERITIES = ['information', 'low', 'moderate', 'high', 'critical'];

    private const STATUSES = ['pending_review', 'published', 'expired', 'rejected', 'archived'];

    public function index(Request $request, TyphoonSafetyService $typhoonSafety, PagasaAdvisoryService $pagasa): View
    {
        $pagasaSummary = $pagasa->fetchAndStore(false);
        $query = $this->filteredQuery($request)
            ->when($request->user()->role === User::ROLE_FARMER, function ($query) use ($request) {
                $query->active()->forBarangay($request->user()->barangay);
            });
        $activeTyphoonSafetyEvent = $request->user()->role === User::ROLE_FARMER
            ? $typhoonSafety->activeEvent()
            : null;

        return view('advisories.index', [
            'advisories' => $query->latest('valid_from')->paginate(10)->withQueryString(),
            'lastWeather' => ExternalWeatherData::query()->latest('fetched_at')->first(),
            'latestPagasaAdvisory' => PlantingAdvisory::query()->where('source', 'PAGASA')->latest('created_at')->first(),
            'activeTyphoonSafetyEvent' => $activeTyphoonSafetyEvent,
            'typhoonSafetyResponse' => Schema::hasTable('typhoon_safety_responses') && $activeTyphoonSafetyEvent
                ? TyphoonSafetyResponse::query()
                    ->where('user_id', $request->user()->id)
                    ->where('event_key', $activeTyphoonSafetyEvent['key'])
                    ->first()
                : null,
            'activeCount' => PlantingAdvisory::query()->active()->count(),
            'pagasaAdvisoryCount' => PlantingAdvisory::query()->active()->where('source', 'PAGASA')->count(),
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
            'canManage' => $this->canManage($request),
            'pagasaSummary' => $pagasaSummary,
        ]);
    }

    public function adminIndex(Request $request): View
    {
        $this->authorizeManage($request);

        return view('advisories.management', [
            'advisories' => $this->filteredQuery($request)->latest('created_at')->paginate(10)->withQueryString(),
            'lastWeather' => ExternalWeatherData::query()->latest('fetched_at')->first(),
            'latestPagasaAdvisory' => PlantingAdvisory::query()->where('source', 'PAGASA')->latest('created_at')->first(),
            'activeCount' => PlantingAdvisory::query()->active()->count(),
            'pagasaAdvisoryCount' => PlantingAdvisory::query()->active()->where('source', 'PAGASA')->count(),
            'pendingCount' => PlantingAdvisory::query()->pendingReview()->count(),
            'highRiskCount' => PlantingAdvisory::query()->whereIn('severity', ['high', 'critical'])->whereIn('status', ['published', 'pending_review'])->count(),
            'expiredTodayCount' => PlantingAdvisory::query()->where('status', 'expired')->whereDate('updated_at', today())->count(),
            'types' => self::TYPES,
            'severities' => self::SEVERITIES,
            'statuses' => self::STATUSES,
            'barangays' => LianBarangays::all(),
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

    public function show(Request $request, PlantingAdvisory $plantingAdvisory): View
    {
        if ($request->user()->role === User::ROLE_FARMER && (! $plantingAdvisory->isActive() || ! $this->isVisibleToFarmer($plantingAdvisory, $request->user()))) {
            abort(403);
        }

        return view('advisories.show', [
            'advisory' => $plantingAdvisory->load(['weatherData', 'rule', 'approvedBy', 'postedBy']),
            'canManage' => $this->canManage($request),
        ]);
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

    public function refreshWeather(Request $request, OpenMeteoService $weather): RedirectResponse
    {
        $this->authorizeManage($request);
        $result = $weather->fetchForecast(true);
        SystemAuditLogger::record('Refreshed Advisory Weather', $request, [
            'ok' => $result['ok'] ?? null,
            'records_saved' => $result['records_saved'] ?? null,
            'message' => $result['message'] ?? null,
        ]);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function regenerate(Request $request, AdvisoryGenerationService $service, PagasaAdvisoryService $pagasa): RedirectResponse
    {
        $this->authorizeManage($request);
        $pagasaSummary = $pagasa->fetchAndStore(true);
        $summary = $service->generate(fetchWeather: true, forceWeather: true);
        $message = "{$pagasaSummary['advisories_created']} PAGASA advisories and {$summary['advisories_created']} forecast-based advisories were created. {$pagasaSummary['advisories_skipped_as_duplicates']} PAGASA duplicates and {$summary['advisories_skipped_as_duplicates']} forecast duplicates were skipped. {$summary['advisories_expired']} expired advisory records were updated.";

        $errors = array_merge($pagasaSummary['errors'] ?? [], $summary['errors'] ?? []);

        if ($errors) {
            Log::warning('Advisory regeneration completed with errors.', [
                'pagasa' => $pagasaSummary,
                'forecast' => $summary,
            ]);
        }
        SystemAuditLogger::record('Generated Live Advisories', $request, [
            'pagasa_created' => $pagasaSummary['advisories_created'] ?? 0,
            'forecast_created' => $summary['advisories_created'] ?? 0,
            'forecast_expired' => $summary['advisories_expired'] ?? 0,
            'errors' => $errors,
        ]);

        return back()->with($errors ? 'error' : 'success', $message);
    }

    public function refreshPagasa(Request $request, PagasaAdvisoryService $pagasa, AdvisoryGenerationService $service): RedirectResponse
    {
        $pagasaSummary = $pagasa->fetchAndStore(true);
        $forecastSummary = $service->generate(fetchWeather: true, forceWeather: true);
        $errors = array_merge($pagasaSummary['errors'] ?? [], $forecastSummary['errors'] ?? []);
        $message = "{$pagasaSummary['advisories_created']} PAGASA advisories and {$forecastSummary['advisories_created']} forecast-based advisories created. Sources checked: {$pagasaSummary['sources_checked']}.";
        SystemAuditLogger::record('Refreshed PAGASA Advisories', $request, [
            'sources_checked' => $pagasaSummary['sources_checked'] ?? 0,
            'pagasa_created' => $pagasaSummary['advisories_created'] ?? 0,
            'forecast_created' => $forecastSummary['advisories_created'] ?? 0,
            'errors' => $errors,
        ]);

        return back()->with($errors ? 'error' : 'success', $message);
    }

    private function filteredQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

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
            ->when($request->filled('advisory_type'), fn ($query) => $query->where('advisory_type', $request->query('advisory_type')))
            ->when($request->filled('type'), fn ($query) => $query->where('advisory_type', strtolower((string) $request->query('type'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->query('severity')))
            ->when($request->filled('target_barangay'), function ($query) use ($request) {
                $barangay = (string) $request->query('target_barangay');

                $query->where(function ($builder) use ($barangay) {
                    $builder->where('target_barangay', $barangay)
                        ->orWhereNull('target_barangay')
                        ->orWhere('target_barangay', '')
                        ->orWhere('target_scope', 'municipality')
                        ->orWhereJsonContains('metadata->pagasa_barangay_matches', $barangay);
                });
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
        return empty($advisory->target_barangay)
            || $advisory->target_scope === 'municipality'
            || $advisory->target_barangay === $farmer->barangay;
    }
}
