<?php

namespace App\Http\Controllers;

use App\Jobs\RunYieldPredictionRequest;
use App\Models\BarangayProfile;
use App\Models\Farm;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use App\Support\YieldCorrections;
use Illuminate\Http\RedirectResponse;
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

    public function farmer(Request $request): View
    {
        $user = $request->user();
        app()->setLocale(FarmerLanguage::resolve($request));
        $farm = rescue(fn () => ActiveFarm::current($user), null, false);

        return view('analysis.yield', [
            'defaults' => [
                'season' => RiceSeason::forMonth((int) now(\App\Support\BusinessClock::timezone())->month),
                'farm_type' => $farm?->farm_type ?: ($user->farmerProfile?->farm_type ?: 'Irrigated'),
                'barangay' => $farm?->barangay ?: $user->registeredBarangay(),
                'area' => $farm?->modelArea() ?? ($user->farmerProfile?->farm_area ? (float) $user->farmerProfile->farm_area : null),
            ],
            'barangays' => LianBarangays::all(),
            'requests' => YieldPredictionRequest::query()->where('user_id', $user->getKey())->latest()->limit(20)->get(),
        ]);
    }

    public function farmerStore(Request $request): RedirectResponse
    {
        app()->setLocale(FarmerLanguage::resolve($request));
        $data = $request->validate([
            'season' => ['required', Rule::in([RiceSeason::WET, RiceSeason::DRY])],
            'farm_type' => ['required', Rule::in(['Irrigated', 'Rainfed'])],
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            'area_hectares' => ['required', 'numeric', 'gt:0', 'max:1000'],
        ]);

        return $this->queue($request, YieldPredictionRequest::SCOPE_FARM, $data, 'analysis.yield');
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

        $prediction = YieldPredictionRequest::query()->create([
            'user_id' => $user->getKey(),
            'scope' => $scope,
            'barangay' => $data['barangay'] ?? null,
            'season' => $data['season'],
            'farm_type' => $data['farm_type'],
            'area_hectares' => $data['area_hectares'] ?? null,
            'target_month' => YieldPredictionRequest::targetMonthFor($data['season'])->toDateString(),
            'status' => YieldPredictionRequest::QUEUED,
        ]);
        SystemAuditLogger::forModel('requested', $prediction, $request, ['scope' => $scope]);
        RunYieldPredictionRequest::start($prediction);

        return redirect()->route($back)->with('success', __('yield_request.queued'));
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
     * @param  Collection<int, YieldPredictionRequest>  $requests
     * @return array<int, float>
     */
    private function actualsFor(Collection $requests): array
    {
        $out = [];
        foreach ($requests->where('status', YieldPredictionRequest::DONE) as $r) {
            $year = $r->target_month->year - ($r->season === RiceSeason::DRY ? 1 : 0);   // a Dry season is named by the year it starts
            $actual = rescue(fn () => RiceProduction::query()
                ->where('season', $r->season)->where('irrigation_type', $r->farm_type)
                ->where(fn ($q) => $q->where('year', $year)->orWhere('year', $r->target_month->year))
                ->when($r->barangay, fn ($q) => $q->where('barangay', $r->barangay))
                ->whereNotNull('yield_per_hectare')->avg('yield_per_hectare'), null, false);
            if ($actual !== null) {
                $out[$r->getKey()] = round((float) $actual, 2);
            }
        }

        return $out;
    }
}
