<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Models\User;
use App\Services\RiceVarieties\PlantedVarietiesOverview;
use App\Services\RiceVarieties\RiceVarietyImporter;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\RiceVarieties\VarietyDetails;
use App\Support\SchemaCache;
use App\Support\VarietyHarvestEstimator;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Rice Varieties. One page for three roles:
 *  - Farmer: "My Planted Variety" (expected harvest of the current plan) and the catalog.
 *  - MAO: the catalog and "Planted in my barangays" (read-only).
 *  - IT Personnel: the catalog as a data table with Add / Edit and an Active switch (no deleting: plans point here).
 * The Variety Details pop-up reads show() as JSON, so the catalog page stays light.
 */
class RiceVarietyController extends Controller
{
    private const FILTERS = ['q', 'type', 'farm_type', 'season', 'maturity', 'sort'];

    public function index(Request $request, PlantedVarietiesOverview $overview): View
    {
        Gate::authorize('viewAny', RiceVariety::class);
        $user = $request->user();
        $filters = $this->filters($request);
        $ready = SchemaCache::hasTable('rice_varieties');
        // "No variety matches" is only for a search or filter; with none, an empty list means nothing is loaded yet.
        $filtering = array_diff_key($filters, ['sort' => true]) !== [];

        if ($user->role === User::ROLE_IT_EXPERT) {
            // Newest first: the list is grouped by year ("2026 · 8 varieties", counted over every page).
            $byYear = $ready && ($filters['sort'] ?? 'newest') === 'newest';

            return view('rice-varieties.it-index', [
                'filters' => $filters,
                'varieties' => $ready ? RiceVariety::query()->catalog($filters)->paginate(25)->withQueryString() : null,
                'yearCounts' => $byYear ? RiceVariety::query()->catalog($filters)->reorder()->toBase()
                    ->selectRaw('year_approved, COUNT(*) AS varieties')->groupBy('year_approved')->pluck('varieties', 'year_approved')->map(fn ($n) => (int) $n)->all() : null,
                'filtering' => $filtering,
                'notLoaded' => ! $ready || ! RiceVariety::query()->exists(),
            ]);
        }

        $isFarmer = $user->role === User::ROLE_FARMER;
        $plan = $isFarmer && $ready ? $this->currentPlan($user) : null;
        $direction = $request->query('planted_sort') === 'desc' ? 'desc' : 'asc';

        return view('rice-varieties.index', [
            'filters' => $filters,
            'varieties' => $ready ? RiceVariety::query()->active()->catalog($filters)->paginate(24)->withQueryString() : null,
            'isFarmer' => $isFarmer,
            'myVariety' => $plan ? $this->myVariety($plan) : null,
            'planted' => ! $isFarmer && $ready ? $overview->for($user, $direction) : null,
            'plantedSort' => $direction,
            'plantUrl' => $isFarmer ? $this->plantUrl($plan) : null,
            'filtering' => $filtering,
            'notLoaded' => ! $ready || ! RiceVariety::query()->active()->exists(),
        ]);
    }

    /** The Variety Details pop-up's data. */
    public function show(Request $request, RiceVariety $riceVariety): JsonResponse
    {
        Gate::authorize('view', $riceVariety);
        $user = $request->user();
        if ($user->role !== User::ROLE_FARMER) {
            return response()->json(VarietyDetails::payload($riceVariety));
        }

        // The farmer's own plan for this variety adds the "On your calendar" bar; "I planted this variety" opens the plan.
        $current = $this->currentPlan($user);
        $plan = $current?->rice_variety_id === $riceVariety->id ? $current : null;

        return response()->json(VarietyDetails::payload($riceVariety, $plan, $riceVariety->is_active ? $this->plantUrl($current, $riceVariety->id) : null));
    }

    /** The planting form's "Expected harvest: Feb 16, 2027 (± 4 days)" line. */
    public function estimate(Request $request, VarietyHarvestEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'variety' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'season' => ['nullable', Rule::in(FarmerPlantingSchedule::SEASONS)],
            'establishment' => ['nullable', Rule::in(VarietyHarvestEstimator::ESTABLISHMENTS)],
            'basis' => ['nullable', Rule::in(VarietyHarvestEstimator::DATE_BASES)],
            'seedling_age' => ['nullable', 'integer', 'min:5', 'max:60'],
        ]);
        $variety = ! empty($data['variety']) && SchemaCache::hasTable('rice_varieties') ? RiceVariety::query()->find($data['variety']) : null;
        $result = $estimator->estimate($variety, $data['date'], $data['season'] ?? null, $data['establishment'] ?? null, $data['basis'] ?? 'sowing', $data['seedling_age'] ?? null);
        $expected = $result['expected_harvest_date'];
        $before = (int) $result['window_start']->diffInDays($expected);
        $after = (int) $expected->diffInDays($result['window_end']);

        return response()->json([
            'expected_harvest_date' => $expected->toDateString(),
            'window_start' => $result['window_start']->toDateString(),
            'window_end' => $result['window_end']->toDateString(),
            'maturity_days_used' => $result['maturity_days_used'],
            'basis' => $result['basis'],
            'basis_code' => $result['basis_code'],
            'label' => $before === $after
                ? __('rice_varieties.estimate.label', ['date' => $this->day($expected), 'days' => $before])
                : __('rice_varieties.estimate.label_range', ['date' => $this->day($expected), 'range' => self::range($result['window_start'], $result['window_end'])]),
        ]);
    }

    // ------------------------------------------------------------------ IT Personnel

    public function create(): View
    {
        Gate::authorize('create', RiceVariety::class);

        return view('rice-varieties.form', ['variety' => new RiceVariety(['is_active' => true]), 'action' => route('rice-varieties.store'), 'method' => 'POST']);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', RiceVariety::class);
        $data = $this->validatedVariety($request, null);

        $key = RiceVarietyImporter::nsicKey($data['nsic_reg_no']) ?? RiceVarietyImporter::nameKey($data['variety_name'], (int) $data['year_approved']);
        if (RiceVariety::query()->where('import_key', $key)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['variety_name' => __('rice_varieties.it.errors.unique')]);
        }

        $variety = new RiceVariety;
        $variety->fill($this->attributes($data, $variety) + ['import_key' => $key, 'is_active' => true])->save();
        SystemAuditLogger::record('Added Rice Variety', $request, $this->logContext($variety), model: $variety);

        return redirect()->route('rice-varieties.index')->with('success', __('rice_varieties.it.saved'));
    }

    public function edit(RiceVariety $riceVariety): View
    {
        Gate::authorize('update', $riceVariety);

        return view('rice-varieties.form', ['variety' => $riceVariety, 'action' => route('rice-varieties.update', $riceVariety), 'method' => 'PUT']);
    }

    public function update(Request $request, RiceVariety $riceVariety): RedirectResponse
    {
        Gate::authorize('update', $riceVariety);
        $data = $this->validatedVariety($request, $riceVariety);

        // An imported row corrected by hand becomes "manual", so the next import does not undo the correction.
        $riceVariety->fill($this->attributes($data, $riceVariety))->save();
        SystemAuditLogger::record('Edited Rice Variety', $request, $this->logContext($riceVariety), model: $riceVariety);

        return redirect()->route('rice-varieties.index', $request->only(self::FILTERS))->with('success', __('rice_varieties.it.updated'));
    }

    /** Active / inactive: an inactive variety leaves the farmer and MAO pickers but stays on the plans that use it. */
    public function toggle(Request $request, RiceVariety $riceVariety): RedirectResponse
    {
        Gate::authorize('toggle', $riceVariety);
        $riceVariety->forceFill(['is_active' => ! $riceVariety->is_active])->save();
        SystemAuditLogger::record($riceVariety->is_active ? 'Activated Rice Variety' : 'Deactivated Rice Variety', $request, $this->logContext($riceVariety), model: $riceVariety);

        return back()->with('success', __($riceVariety->is_active ? 'rice_varieties.it.activated' : 'rice_varieties.it.deactivated'));
    }

    /**
     * "Run import" (IT, on the empty list): the same import as `php artisan rice-varieties:import`, from the NSIC
     * spreadsheet on the server (config rice_varieties.import_path). The result is in the System Logs.
     */
    public function import(Request $request, RiceVarietyImporter $importer): RedirectResponse
    {
        Gate::authorize('import', RiceVariety::class);
        $path = (string) config('rice_varieties.import_path');

        try {
            $summary = $importer->import($path);
        } catch (\Throwable $e) {
            report($e);
            SystemAuditLogger::record('Rice Variety Import Failed', $request, ['file' => basename($path), 'error' => mb_substr($e->getMessage(), 0, 250)]);

            return back()->with('error', __('rice_varieties.it.import_failed'));
        }

        SystemAuditLogger::record('Imported Rice Varieties', $request, ['file' => basename($path)]
            + array_intersect_key($summary, array_flip(['rows', 'inserted', 'updated', 'unchanged', 'skipped'])));

        return back()->with('success', __('rice_varieties.it.import_done', [
            'inserted' => number_format($summary['inserted']), 'updated' => number_format($summary['updated']), 'unchanged' => number_format($summary['unchanged']),
        ]));
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $filters = array_map(fn ($v) => is_string($v) ? trim($v) : '', $request->only(self::FILTERS));
        $allowed = [
            'type' => ['Inbred', 'Hybrid', 'Special Purpose'],
            'farm_type' => RiceVariety::FARM_TYPES,
            'season' => ['ds', 'ws'],
            'maturity' => array_keys(RiceVariety::MATURITY_BANDS),
            'sort' => ['newest', 'maturity', 'yield'],
        ];
        foreach ($allowed as $key => $values) {
            if (isset($filters[$key]) && ! in_array($filters[$key], $values, true)) {
                unset($filters[$key]);
            }
        }
        $filters['q'] = mb_substr($filters['q'] ?? '', 0, 80);

        return array_filter($filters, fn ($v) => $v !== '');
    }

    private function currentPlan(User $user): ?FarmerPlantingSchedule
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'rice_variety_id')) {
            return null;
        }

        return FarmerPlantingScheduleController::currentSchedule($user, ActiveFarm::current($user))?->load('riceVariety');
    }

    /** Where "I planted this variety" / "Change variety" go: the current plan's form, else a new plan. */
    private function plantUrl(?FarmerPlantingSchedule $plan, ?int $varietyId = null): string
    {
        $query = $varietyId ? ['variety' => $varietyId] : [];

        return $plan ? route('farmer.planting-schedules.edit', ['plantingSchedule' => $plan->id] + $query) : route('farmer.planting-schedules.create', $query);
    }

    /**
     * The "My Planted Variety" card: dates, countdown, growth stages along the season and the estimate's basis.
     *
     * @return array<string, mixed>|null
     */
    private function myVariety(FarmerPlantingSchedule $plan): ?array
    {
        $harvest = $plan->harvestDateForPlanning();
        if ($plan->planting_date === null || $harvest === null) {
            return ['plan' => $plan, 'variety' => $plan->riceVariety, 'harvest' => null];
        }

        $today = BusinessClock::today()->startOfDay();
        $planted = $plan->planting_date->copy()->startOfDay();
        $harvest = $harvest->copy()->startOfDay();
        $estimate = $plan->rice_variety_id ? $plan->varietyEstimate() : null;
        $total = max(1, (int) $planted->diffInDays($harvest));
        $elapsed = max(0, min($total, (int) $planted->diffInDays($today, false)));
        // Ripening: about the last 30 days; reproductive: the 30 before; vegetative: the rest.
        $ripening = max(0, $total - 30);
        $reproductive = max(0, $total - 60);
        $pct = fn (int $days): float => round($days / $total * 100, 1);

        return [
            'plan' => $plan,
            'variety' => $plan->riceVariety,
            'planted' => $planted,
            'harvest' => $harvest,
            'window' => $estimate && ! $plan->harvest_date_manual ? self::range($estimate['window_start'], $estimate['window_end']) : null,
            'days_left' => (int) $today->diffInDays($harvest, false),
            'progress' => $pct($elapsed),
            'stages' => [
                ['key' => 'vegetative', 'from' => 0, 'to' => $pct($reproductive)],
                ['key' => 'reproductive', 'from' => $pct($reproductive), 'to' => $pct($ripening)],
                ['key' => 'ripening', 'from' => $pct($ripening), 'to' => 100.0],
            ],
            'basis' => $plan->harvest_date_manual ? __('rice_varieties.my_variety.manual') : ($estimate['basis'] ?? null),
            'change_url' => route('farmer.planting-schedules.edit', $plan),
        ];
    }

    private function day(CarbonInterface $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('M j, Y');
    }

    /** "Feb 12–20, 2027", "Jan 30 – Feb 5, 2027", "Dec 28, 2026 – Jan 3, 2027". */
    public static function range(CarbonInterface $start, CarbonInterface $end): string
    {
        $locale = app()->getLocale();
        $f = fn (CarbonInterface $d, string $format): string => $d->copy()->locale($locale)->translatedFormat($format);
        if ($start->year !== $end->year) {
            return $f($start, 'M j, Y').' – '.$f($end, 'M j, Y');
        }
        if ($start->month !== $end->month) {
            return $f($start, 'M j').' – '.$f($end, 'M j, Y');
        }

        return $f($start, 'M j').'–'.$f($end, 'j, Y');
    }

    /** @return array<string, mixed> */
    private function validatedVariety(Request $request, ?RiceVariety $variety): array
    {
        $codes = [...RiceVariety::REACTION_CODES, 'none', 'keep'];
        $rules = [
            'nsic_reg_no' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail) use ($variety): void {
                $key = RiceVarietyImporter::nsicKey((string) $value);
                if ($key === null) {
                    return;                                         // "Philippine Seed Board": shared by the old rows
                }
                $taken = RiceVariety::query()->whereRaw('LOWER(nsic_reg_no) = ?', [$key])
                    ->when($variety, fn ($q) => $q->whereKeyNot($variety->getKey()))->exists();
                if ($taken) {
                    $fail(__('rice_varieties.it.errors.unique'));
                }
            }],
            'variety_name' => ['required', 'string', 'max:191'],
            'line_name' => ['nullable', 'string', 'max:191'],
            'variety_type' => ['nullable', Rule::in(RiceVariety::TYPES)],
            'owner_breeder' => ['nullable', 'string', 'max:191'],
            'year_approved' => ['required', 'integer', 'between:1950,'.(now()->year + 1)],
            // Farm type: Irrigated and / or Rainfed, or none ("Not specified"); saved exactly as checked.
            'farm_types' => ['nullable', 'array'],
            'farm_types.*' => ['string', 'distinct', Rule::in(RiceVariety::FARM_TYPES)],
            'culm_strength' => ['nullable', 'string', 'max:80'],
            'amylose_content' => ['nullable', 'numeric', 'gt:0', 'max:40'],
            'adaptation' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'pests' => ['nullable', 'array'],
            'pests.*' => ['nullable', Rule::in($codes)],
            'diseases' => ['nullable', 'array'],
            'diseases.*' => ['nullable', Rule::in($codes)],
        ];
        foreach (RiceVariety::COMBOS as $combo) {
            $rules['yield_'.$combo] = ['nullable', 'integer', 'gt:0', 'max:20000'];
            foreach (['', '_min', '_max'] as $part) {
                $rules['maturity_'.$combo.$part] = ['nullable', 'integer', 'gt:0', 'max:250'];
                $rules['height_'.$combo.$part] = ['nullable', 'numeric', 'gt:0', 'max:300'];
                $rules['tillers_'.$combo.$part] = ['nullable', 'numeric', 'gt:0', 'max:1000'];
            }
        }
        foreach (['', '_min', '_max'] as $part) {
            $rules['maturity_seed_production'.$part] = ['nullable', 'integer', 'gt:0', 'max:250'];
        }

        $validator = validator($request->all(), $rules, [], $this->attributeNames());
        $validator->after(function (Validator $v) use ($request): void {
            foreach ([...array_map(fn ($c) => 'maturity_'.$c, [...RiceVariety::COMBOS, 'seed_production']), ...array_map(fn ($c) => 'height_'.$c, RiceVariety::COMBOS), ...array_map(fn ($c) => 'tillers_'.$c, RiceVariety::COMBOS)] as $field) {
                $values = array_map(fn (string $s) => is_numeric($request->input($field.$s)) ? (float) $request->input($field.$s) : null, ['_min' => '_min', '' => '', '_max' => '_max']);
                $known = array_values(array_filter($values, fn ($x) => $x !== null));
                $sorted = $known;
                sort($sorted);
                if ($known !== $sorted) {
                    $v->errors()->add($field, __('rice_varieties.it.errors.order'));
                }
            }
        });

        return $validator->validate();
    }

    /** @return array<string, mixed> */
    private function attributes(array $data, RiceVariety $variety): array
    {
        $attributes = array_intersect_key($data, array_flip([
            'nsic_reg_no', 'variety_name', 'line_name', 'variety_type', 'owner_breeder', 'year_approved',
            'culm_strength', 'amylose_content', 'adaptation', 'remarks',
        ])) + array_fill_keys(['line_name', 'variety_type', 'owner_breeder', 'culm_strength', 'amylose_content', 'adaptation', 'remarks'], null);
        $attributes['source'] = RiceVariety::SOURCE_MANUAL;
        foreach (RiceVariety::FARM_TYPE_COLUMNS as $type => $column) {
            $attributes[$column] = in_array($type, $data['farm_types'] ?? [], true);
        }

        $yields = $variety->yield_values ?? [];
        foreach (RiceVariety::COMBOS as $combo) {
            $kg = isset($data['yield_'.$combo]) ? (int) $data['yield_'.$combo] : null;
            if ($kg !== ($variety->{'yield_'.$combo} !== null ? (int) $variety->{'yield_'.$combo} : null)) {
                // A changed yield replaces the imported regional breakdown.
                $yields[$combo] = $kg !== null ? ['raw' => (string) $kg, 'values' => [['label' => null, 'kg' => $kg]]] : null;
            }
            $attributes['yield_'.$combo] = $kg;
            foreach (['', '_min', '_max'] as $part) {
                foreach (['maturity', 'height', 'tillers'] as $measure) {
                    $attributes[$measure.'_'.$combo.$part] = $data[$measure.'_'.$combo.$part] ?? null;
                }
            }
        }
        foreach (['', '_min', '_max'] as $part) {
            $attributes['maturity_seed_production'.$part] = $data['maturity_seed_production'.$part] ?? null;
        }
        $attributes['yield_values'] = array_filter($yields) ?: null;

        $attributes['reactions'] = [];
        foreach (['pests' => RiceVariety::PESTS, 'diseases' => RiceVariety::DISEASES] as $group => $keys) {
            $out = [];
            foreach ($keys as $key) {
                $choice = $data[$group][$key] ?? 'none';
                if ($choice === 'keep' && isset($variety->reactions[$group][$key])) {
                    $out[$key] = $variety->reactions[$group][$key];
                } elseif (in_array($choice, RiceVariety::REACTION_CODES, true)) {
                    $out[$key] = ['raw' => $choice, 'entries' => [['label' => null, 'code' => $choice, 'note' => null]]];
                }
            }
            $attributes['reactions'][$group] = $out;
        }

        return $attributes;
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];
        foreach (__('rice_varieties.it.fields') as $key => $label) {
            $names[$key] = $label;
        }
        foreach (RiceVariety::COMBOS as $combo) {
            $label = __('rice_varieties.it.combos.'.$combo);
            $names['yield_'.$combo] = 'yield '.$label;
            foreach (['' => 'avg', '_min' => 'min', '_max' => 'max'] as $part => $word) {
                $names['maturity_'.$combo.$part] = 'maturity '.$label.' '.$word;
                $names['height_'.$combo.$part] = 'height '.$label.' '.$word;
                $names['tillers_'.$combo.$part] = 'tillers '.$label.' '.$word;
            }
        }

        return $names;
    }

    /** @return array<string, mixed> */
    private function logContext(RiceVariety $variety): array
    {
        return ['record_type' => 'Rice variety', 'record_id' => $variety->getKey(), 'record_label' => $variety->displayName(), 'active' => (bool) $variety->is_active];
    }
}
