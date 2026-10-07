<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\MaoActivity;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * MAO Planner (2026-10): the MAO's activities as a list sorted by date, with a form to add one, and the farmers'
 * planting and harvest dates as a second, read-only list. Replaces the calendar grid for MAO accounts; farmers keep
 * their calendar. Saved data only.
 */
class MaoPlannerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'barangay' => ['nullable', Rule::in(LianBarangays::all())],
            'type' => ['nullable', Rule::in(MaoActivity::TYPES)],
            'farmer_barangay' => ['nullable', Rule::in(LianBarangays::all())],
        ]);

        $activitiesAvailable = SchemaCache::hasTable('mao_activities');

        if ($activitiesAvailable) {
            $activities = MaoActivity::query()->with('user:id,name')
                // An activity for all barangays is part of every barangay's list.
                ->when($filters['barangay'] ?? null, fn ($q, string $b) => $q->where(fn ($w) => $w->where('barangay', $b)->orWhereNull('barangay')))
                ->when($filters['type'] ?? null, fn ($q, string $type) => $q->where('activity_type', $type))
                ->orderBy('starts_on')->orderBy('id')
                ->paginate(20, ['*'], 'page')->withQueryString();
        } else {
            Log::warning('MAO planner is unavailable because its database table is missing.', [
                'table' => 'mao_activities',
                'user_id' => $request->user()?->id,
                'route' => $request->route()?->getName(),
            ]);

            $activities = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 20,
                currentPage: LengthAwarePaginator::resolveCurrentPage('page'),
                options: ['path' => $request->url(), 'query' => $request->query()],
            );
        }

        $data = [
            'activities' => $activities,
            'activitiesAvailable' => $activitiesAvailable,
            'farmerActivities' => $this->farmerActivities($filters['farmer_barangay'] ?? null),
            'filters' => $filters,
            'barangays' => LianBarangays::all(),
            'types' => MaoActivity::TYPES,
            'locale' => $request->user()->getAttribute('preferred_language') === 'fil' ? 'fil' : 'en',
        ];

        return response()->view('mao.planner', $data, $activitiesAvailable ? 200 : 503);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! SchemaCache::hasTable('mao_activities')) {
            return back()->with('error', 'MAO planning is temporarily unavailable while the database update is completed.');
        }

        MaoActivity::query()->create($this->validated($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('mao.planner.index')->with('success', 'Activity added.');
    }

    public function update(Request $request, MaoActivity $activity): RedirectResponse
    {
        abort_unless($activity->user_id === $request->user()->id, 403);
        $activity->update($this->validated($request));

        return redirect()->route('mao.planner.index')->with('success', 'Activity updated.');
    }

    public function destroy(Request $request, MaoActivity $activity): RedirectResponse
    {
        abort_unless($activity->user_id === $request->user()->id, 403);
        $activity->delete();

        return redirect()->route('mao.planner.index')->with('success', 'Activity deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'activity_type' => ['required', Rule::in(MaoActivity::TYPES)],
            'barangay' => ['nullable', Rule::in(LianBarangays::all())],     // empty: All Barangays
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ['barangay' => $data['barangay'] ?? null, 'ends_on' => $data['ends_on'] ?? null, 'notes' => $data['notes'] ?? null] + $data;
    }

    /**
     * What farmers planted and harvested (or expect to harvest), newest first: one row per date, read-only.
     *
     * @return Collection<int, array{date: \Illuminate\Support\Carbon, kind: string, farmer: string, barangay: ?string, farm: ?string, hectares: ?float}>
     */
    private function farmerActivities(?string $barangay): Collection
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules')) {
            return collect();
        }

        return FarmerPlantingSchedule::query()->with(['farmer:id,name', 'farm:id,farm_name'])
            ->latest('planting_date')->limit(200)->get()
            ->filter(fn (FarmerPlantingSchedule $plan): bool => $barangay === null || LianBarangays::canonicalName($plan->barangay) === $barangay)
            ->flatMap(function (FarmerPlantingSchedule $plan): array {
                $row = fn ($date, string $kind): array => [
                    'date' => $date, 'kind' => $kind, 'farmer' => (string) ($plan->farmer?->name ?? 'Farmer'),
                    'barangay' => LianBarangays::canonicalName($plan->barangay) ?? $plan->barangay,
                    'farm' => $plan->farm?->farm_name, 'hectares' => (float) $plan->farm_area_hectares ?: null,
                ];
                $harvest = $plan->harvested_on ?? $plan->harvestDateForPlanning();

                return array_filter([
                    $plan->planting_date ? $row($plan->planting_date, 'Planted') : null,
                    $harvest ? $row($harvest, $plan->harvested_on ? 'Harvested' : 'Expected harvest') : null,
                ]);
            })
            ->sortByDesc('date')->values();
    }
}
