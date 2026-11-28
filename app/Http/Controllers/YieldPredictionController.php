<?php

namespace App\Http\Controllers;

use App\Jobs\RunYieldPredictionRequest;
use App\Models\BarangayProfile;
use App\Models\Farm;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Analytics\YieldSeasonComparison;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use App\Support\RecordProvenance;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use App\Support\YieldCorrections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 5.
 *  - Yield Prediction (farmer, a tab of Forecast): season, irrigation, barangay and farm area → predicted t/ha, total
 *    harvest, Lian's usual yield for that month and Favorable / Normal / At Risk. History of their requests.
 *  - Yield Forecast (MAO, a tab of Predictions): the same for a barangay or the whole municipality, run history, and
 *    predicted against actual yield.
 * A request is saved at once and the model runs after the response (RunYieldPredictionRequest): no page waits for it.
 */
class YieldPredictionController extends Controller
{
    public const MAX_PENDING = 3;

    public function status(Request $request, int $prediction): JsonResponse
    {
        $record = YieldPredictionRequest::query()->findOrFail($prediction);
        $user = $request->user();
        $allowed = (int) $record->user_id === (int) $user->getKey()
            || ($user->role === User::ROLE_MAO && in_array($record->scope, [YieldPredictionRequest::SCOPE_BARANGAY, YieldPredictionRequest::SCOPE_MUNICIPAL], true));

        abort_unless($allowed, 403);

        return response()->json([
            'id' => $record->getKey(),
            'status' => match ($record->status) {
                YieldPredictionRequest::QUEUED => 'requested',
                YieldPredictionRequest::DONE => 'completed',
                default => $record->status,
            },
            'error' => $record->status === YieldPredictionRequest::FAILED ? $record->error : null,
            'updated_at' => $record->updated_at?->toISOString(),
        ]);
    }

    public function farmer(Request $request): View
    {
        $user = $request->user();
        app()->setLocale(FarmerLanguage::resolve($request));
        $farms = rescue(fn () => ActiveFarm::farms($user), collect(), false);
        $selectedFarm = rescue(fn () => ActiveFarm::current($user), null, false) ?? $farms->first();
        if (old('farm_id') && $farms->isNotEmpty()) {
            $selectedFarm = $farms->firstWhere('id', (int) old('farm_id')) ?? $selectedFarm;
        }

        $defaultSeason = RiceSeason::forMonth((int) now(\App\Support\BusinessClock::timezone())->month);
        $compareSeason = in_array(old('season', $request->query('season')), [RiceSeason::WET, RiceSeason::DRY], true)
            ? old('season', $request->query('season'))
            : $defaultSeason;
        if ($request->query('farm_id') !== null && ! old('farm_id')) {
            $selectedFarm = ActiveFarm::owned($user, $request->query('farm_id')) ?? $selectedFarm;
        }

        return view('analysis.yield', [
            'farms' => $farms,
            'selectedFarm' => $selectedFarm,
            'defaults' => [
                'season' => $compareSeason,
                'farm_id' => $selectedFarm?->getKey(),
            ],
            'comparison' => $this->comparison($user, $selectedFarm, $compareSeason),
            'requests' => YieldPredictionRequest::query()
                ->where('user_id', $user->getKey())
                ->with('farm:id,farm_name,barangay')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    /** The same-season comparison alone, for the farm and season picked on the page (no outside call is made). */
    public function farmerComparison(Request $request): View
    {
        app()->setLocale(FarmerLanguage::resolve($request));
        $user = $request->user();
        $data = $request->validate([
            'season' => ['required', Rule::in([RiceSeason::WET, RiceSeason::DRY])],
            'farm_id' => ['required', 'integer'],
        ]);
        $farm = ActiveFarm::owned($user, $data['farm_id']);
        abort_if($farm === null, 404);

        return view('analysis.partials.yield-comparison', ['comparison' => $this->comparison($user, $farm, $data['season'])]);
    }

    /** @return array<string, mixed>|null */
    private function comparison(User $user, ?Farm $farm, string $season): ?array
    {
        if ($farm === null) {
            return null;
        }

        return rescue(fn () => app(YieldSeasonComparison::class)->build($user, $farm, $season) + ['farm_id' => $farm->getKey(), 'farm_name' => $farm->farm_name ?: null], null);
    }

    public function farmerStore(Request $request): RedirectResponse
    {
        app()->setLocale(FarmerLanguage::resolve($request));
        $user = $request->user();

        $data = $request->validate([
            'season' => ['required', Rule::in([RiceSeason::WET, RiceSeason::DRY])],
            'farm_id' => ['required', 'integer'],
        ]);

        $farm = ActiveFarm::owned($user, $data['farm_id']);
        if ($farm === null) {
            if (Farm::query()->whereKey((int) $data['farm_id'])->exists()) {
                abort(403);
            }

            return redirect()->route('analysis.yield')->withErrors(['farm_id' => __('yield_request.farm_required')])->withInput();
        }

        $area = $farm->modelArea();
        if ($area === null) {
            $sizeData = $request->validate([
                'area_hectares' => ['required', 'numeric', 'gt:0', 'max:1000'],
            ]);
            $area = round((float) $sizeData['area_hectares'], 2);

            if ($request->boolean('save_size_to_farm')) {
                $farm->forceFill([
                    'farm_size_hectares' => round($area, 4),
                    'farm_size_method' => Farm::SIZE_EXACT,
                    'farm_size_unit' => Farm::UNIT_HECTARES,
                ])->save();
                ActiveFarm::forget($user);
            }
        }

        $barangay = LianBarangays::canonicalName($farm->barangay) ?: ($user->registeredBarangay() ?: LianBarangays::all()[0]);
        $farmType = in_array($farm->farm_type, ['Irrigated', 'Rainfed'], true) ? $farm->farm_type : 'Irrigated';

        return $this->queue($request, YieldPredictionRequest::SCOPE_FARM, [
            'farm_id' => $farm->getKey(),
            'season' => $data['season'],
            'farm_type' => $farmType,
            'barangay' => $barangay,
            'area_hectares' => $area,
        ], 'analysis.yield');
    }

    public function useful(Request $request, int $prediction): RedirectResponse|JsonResponse
    {
        app()->setLocale(FarmerLanguage::resolve($request));
        $record = YieldPredictionRequest::query()
            ->where('user_id', $request->user()->getKey())
            ->findOrFail($prediction);

        $data = $request->validate([
            'useful' => ['required', 'boolean'],
        ]);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('yield_prediction_requests', 'useful')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Feedback is not available until database migrations finish.'], 503);
            }

            return redirect()->route('analysis.yield')->withErrors([
                'useful' => 'Feedback is not available until database migrations finish.',
            ]);
        }

        $record->forceFill(['useful' => (bool) $data['useful']])->save();

        if ($request->expectsJson()) {
            return response()->json(['useful' => $record->useful]);
        }

        return redirect()->route('analysis.yield')->with('success', __('yield_request.useful_saved'));
    }

    public function mao(): View
    {
        $requests = YieldPredictionRequest::query()->whereIn('scope', [YieldPredictionRequest::SCOPE_BARANGAY, YieldPredictionRequest::SCOPE_MUNICIPAL])
            ->with('user:id,name')->latest()->limit(30)->get();

        return view('analysis.forecast', [
            'barangays' => LianBarangays::all(),
            'areas' => $this->barangayAreas(),
            'requests' => $requests,
            'actuals' => $this->actualsFor($requests),
            'corrections' => rescue(fn () => YieldCorrections::summary(), ['count' => 0, 'rows' => collect()], false),
            'season' => RiceSeason::forMonth((int) now(\App\Support\BusinessClock::timezone())->month),
        ]);
    }

    public function maoStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in([YieldPredictionRequest::SCOPE_BARANGAY, YieldPredictionRequest::SCOPE_MUNICIPAL])],
            'barangay' => ['nullable', 'required_if:scope,barangay', Rule::in(LianBarangays::all())],
            'season' => ['required', Rule::in([RiceSeason::WET, RiceSeason::DRY])],
            'farm_type' => ['required', Rule::in(['Irrigated', 'Rainfed'])],
            'area_hectares' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
        ]);
        if ($data['scope'] === YieldPredictionRequest::SCOPE_MUNICIPAL) {
            $data['barangay'] = null;
        }
        // Without a typed area: the barangay's (or all barangays') rice area from the MAO's records, else what farmers registered.
        if (empty($data['area_hectares'])) {
            $areas = $this->barangayAreas();
            $data['area_hectares'] = $data['barangay'] ? ($areas[$data['barangay']] ?? null) : (array_sum($areas) ?: null);
        }

        return $this->queue($request, $data['scope'], $data, 'analysis.forecast');
    }

    private function queue(Request $request, string $scope, array $data, string $back): RedirectResponse
    {
        $user = $request->user();
        $pending = YieldPredictionRequest::query()->where('user_id', $user->getKey())->where('status', YieldPredictionRequest::QUEUED)
            ->where('created_at', '>', now()->subMinutes(10))->count();
        if ($pending >= self::MAX_PENDING) {
            return redirect()->route($back)->withErrors(['season' => __('yield_request.too_many')]);
        }

        $targetMonth = YieldPredictionRequest::targetMonthFor($data['season']);
        $forecastHash = $this->currentForecastHash($targetMonth);

        if ($scope === YieldPredictionRequest::SCOPE_FARM && ! empty($data['farm_id'])) {
            $existing = $this->findRecentMatchingRequest(
                $user->getKey(),
                (int) $data['farm_id'],
                $data,
                $targetMonth->toDateString(),
                $forecastHash,
            );
            if ($existing !== null) {
                $now = now();
                $existing->forceFill([
                    'created_at' => $now,
                    'updated_at' => $now,
                    'completed_at' => $now,
                ])->save();

                return redirect()->route($back)->with('success', __('yield_request.queued'));
            }
        }

        $attributes = [
            'user_id' => $user->getKey(),
            'scope' => $scope,
            'barangay' => $data['barangay'] ?? null,
            'season' => $data['season'],
            'farm_type' => $data['farm_type'],
            'area_hectares' => $data['area_hectares'] ?? null,
            'target_month' => $targetMonth->toDateString(),
            'status' => YieldPredictionRequest::QUEUED,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('yield_prediction_requests', 'farm_id')) {
            $attributes['farm_id'] = $data['farm_id'] ?? null;
        }

        $prediction = YieldPredictionRequest::query()->create($attributes);
        SystemAuditLogger::forModel('requested', $prediction, $request, ['scope' => $scope]);
        RunYieldPredictionRequest::start($prediction);

        return redirect()->route($back)->with('success', __('yield_request.queued'));
    }

    private function currentForecastHash(\Carbon\CarbonImmutable $targetMonth): ?string
    {
        return rescue(function () use ($targetMonth): ?string {
            $weather = app(PredictionOrchestrator::class)->monthlyWeatherForecast($targetMonth);

            return YieldPredictionRequest::forecastHash($weather['predictions'] ?? null);
        }, null, false);
    }

    private function findRecentMatchingRequest(
        int $userId,
        int $farmId,
        array $data,
        string $targetDate,
        ?string $forecastHash,
    ): ?YieldPredictionRequest {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('yield_prediction_requests', 'farm_id')) {
            return null;
        }

        $latest = YieldPredictionRequest::query()
            ->where('user_id', $userId)
            ->where('farm_id', $farmId)
            ->where('season', $data['season'])
            ->where('status', YieldPredictionRequest::DONE)
            ->where('created_at', '>=', now()->subHours(24))
            ->latest('created_at')
            ->first();

        if ($latest === null) {
            return null;
        }

        $sameArea = $latest->area_hectares !== null && isset($data['area_hectares'])
            ? round((float) $latest->area_hectares, 2) === round((float) $data['area_hectares'], 2)
            : ($latest->area_hectares === null && ($data['area_hectares'] ?? null) === null);

        $sameInputs = $latest->barangay === ($data['barangay'] ?? null)
            && $latest->farm_type === $data['farm_type']
            && $sameArea
            && $latest->target_month?->toDateString() === $targetDate;

        $sameForecast = ($latest->details['forecast_hash'] ?? null) === $forecastHash;

        return $sameInputs && $sameForecast ? $latest : null;
    }

    /** Rice area per barangay: the MAO's figure on the Barangays page, else the farm area farmers registered. */
    private function barangayAreas(): array
    {
        $mao = rescue(fn () => SchemaCache::hasTable('barangay_profiles') ? BarangayProfile::query()->whereNotNull('farm_area_hectares')->pluck('farm_area_hectares', 'barangay') : collect(), collect(), false);
        $registered = rescue(fn () => SchemaCache::hasTable('farms')
            ? Farm::query()->active()->get()->groupBy('barangay')->map(fn ($farms) => round((float) $farms->sum(fn (Farm $f) => $f->knownHectares() ?? 0), 2))
            : collect(), collect(), false);

        $areas = [];
        foreach (LianBarangays::all() as $name) {
            $value = $mao[$name] ?? (($registered[$name] ?? 0) > 0 ? $registered[$name] : null);
            if ($value !== null) {
                $areas[$name] = (float) $value;
            }
        }

        return $areas;
    }

    /**
     * The yield the MAO recorded (Rice Production) for the season a forecast was for, when it has been recorded: the
     * barangay's records, or the average of all barangays for a municipal forecast.
     *
     * Each value carries where it comes from (RecordProvenance). Only validated, separately measured records are an
     * actual yield; unvalidated records and season totals split across barangays are shown under their own label, and
     * demo records are never used.
     *
     * @param  Collection<int, YieldPredictionRequest>  $requests
     * @return array<int, array{value: float, provenance: string}>
     */
    private function actualsFor(Collection $requests): array
    {
        $done = $requests->where('status', YieldPredictionRequest::DONE);
        if ($done->isEmpty()) {
            return [];
        }
        $classes = rescue(fn (): array => RecordProvenance::riceClasses(), [], false);
        $out = [];
        foreach ($done as $r) {
            $year = $r->target_month->year - ($r->season === RiceSeason::DRY ? 1 : 0);   // a Dry season is named by the year it starts
            $rows = rescue(fn () => RiceProduction::query()
                ->where('season', $r->season)->where('irrigation_type', $r->farm_type)
                ->where(fn ($q) => $q->where('year', $year)->orWhere('year', $r->target_month->year))
                ->when($r->barangay, fn ($q) => $q->where('barangay', $r->barangay))
                ->whereNotNull('yield_per_hectare')->get(['id', 'yield_per_hectare']), collect(), false)
                ->groupBy(fn (RiceProduction $row): string => $classes[$row->id] ?? RecordProvenance::UNVERIFIED);
            // The best evidence on record for this forecast, never an average across kinds of record.
            foreach ([RecordProvenance::VERIFIED, RecordProvenance::UNVERIFIED, RecordProvenance::DERIVED] as $class) {
                if ($rows->has($class)) {
                    $out[$r->getKey()] = ['value' => round((float) $rows[$class]->avg('yield_per_hectare'), 2), 'provenance' => $class];
                    break;
                }
            }
        }

        return $out;
    }
}
