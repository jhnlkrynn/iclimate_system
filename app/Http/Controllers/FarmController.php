<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmBoundary;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\FarmBoundaryMeasurementService;
use App\Services\Farms\FarmService;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\FarmSize;
use App\Support\LianBarangays;
use App\Support\LianGeography;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * A farmer's farms: add, edit, remove (archive), set as primary, pick the farm to view (the My Farm selector), and draw
 * each farm's map. A farm is only ever found among the signed-in farmer's own farms in use: an id from the browser
 * that is not theirs is "not found", so no other farmer's farm can be seen or changed.
 */
class FarmController extends Controller
{
    private const MAX_MAP_POINTS = 100;

    public function __construct(private readonly FarmService $farms) {}

    public function create(Request $request): View
    {
        $this->ensureAvailable();

        return view('farms.form', [
            'farm' => new Farm(['farm_size_method' => Farm::SIZE_UNKNOWN, 'farm_size_unit' => Farm::UNIT_HECTARES]),
            'defaultName' => $this->farms->defaultName($request->user()),
            'action' => route('farmer.farms.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAvailable();
        $user = $request->user();
        $farm = $this->farms->create($user, $this->validated($request));
        // The farm just added is the one the farmer sees next.
        ActiveFarm::select($user, (int) $farm->getKey());
        $this->audit('Added Farm', $request, $farm);

        return $farm->farm_size_method === Farm::SIZE_MAPPED
            ? redirect()->route('farmer.farms.map', $farm)->with('success', __('farms.added', ['name' => $farm->farm_name]))
            : redirect()->route('farmer.my-farm')->with('success', __('farms.added', ['name' => $farm->farm_name]));
    }

    public function edit(Request $request, int $farm): View
    {
        $farm = $this->ownFarm($request, $farm);

        return view('farms.form', [
            'farm' => $farm,
            'defaultName' => $farm->farm_name,
            'action' => route('farmer.farms.update', $farm),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, int $farm): RedirectResponse
    {
        $farm = $this->ownFarm($request, $farm);
        $this->farms->update($farm, $this->validated($request));
        $this->audit('Updated Farm', $request, $farm);

        return $farm->farm_size_method === Farm::SIZE_MAPPED && $farm->boundary === null
            ? redirect()->route('farmer.farms.map', $farm)->with('success', __('farms.saved', ['name' => $farm->farm_name]))
            : redirect()->route('farmer.my-farm')->with('success', __('farms.saved', ['name' => $farm->farm_name]));
    }

    /** Removes the farm from the list (archived: its plans, map and records stay). */
    public function destroy(Request $request, int $farm): RedirectResponse
    {
        $farm = $this->ownFarm($request, $farm);
        $this->farms->archive($farm);
        $this->audit('Removed Farm', $request, $farm);

        return redirect()->route('farmer.my-farm')->with('success', __('farms.removed', ['name' => $farm->farm_name]));
    }

    public function primary(Request $request, int $farm): RedirectResponse
    {
        $farm = $this->ownFarm($request, $farm);
        $this->farms->makePrimary($farm);
        $this->audit('Set Primary Farm', $request, $farm);

        return redirect()->route('farmer.my-farm')->with('success', __('farms.primary_set', ['name' => $farm->farm_name]));
    }

    /**
     * The My Farm selector: makes one of the farmer's own farms the one their pages show. Any other id changes nothing
     * and is refused.
     */
    public function select(Request $request): RedirectResponse|JsonResponse
    {
        $this->ensureAvailable();
        $data = $request->validate(['farm_id' => ['required', 'integer']]);
        $farm = ActiveFarm::select($request->user(), (int) $data['farm_id']);

        if ($farm === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('farms.errors.not_found')], 422);
            }

            return back()->withErrors(['farm_id' => __('farms.errors.not_found')]);
        }

        if ($request->expectsJson()) {
            return response()->json(['farm_id' => $farm->getKey(), 'message' => __('farms.switched', ['name' => $farm->farm_name])]);
        }

        return redirect()->to($this->backTo($request))->with('success', __('farms.switched', ['name' => $farm->farm_name]));
    }

    public function map(Request $request, int $farm): View
    {
        $farm = $this->ownFarm($request, $farm);

        return view('farms.map', ['farm' => $farm, 'boundary' => $farm->boundary, 'size' => FarmSize::describe($farm)]);
    }

    /**
     * Saves this farm's outline. It must lie inside the official Lian boundary; its barangay is worked out here from the
     * official barangay boundaries (nothing the browser says about where the farm is gets trusted) and its area is
     * measured, so "Measure on map" never asks the farmer to work out hectares.
     */
    public function saveMap(Request $request, int $farm, FarmBoundaryMeasurementService $measurement): RedirectResponse
    {
        $farm = $this->ownFarm($request, $farm);
        if (is_string($request->input('boundary_coordinates'))) {
            $decoded = json_decode($request->input('boundary_coordinates'), true);
            $request->merge(['boundary_coordinates' => is_array($decoded) ? $decoded : null]);
        }

        $validated = $request->validate([
            'boundary_coordinates' => ['required', 'array', 'min:3', 'max:'.self::MAX_MAP_POINTS],
            'boundary_coordinates.*' => ['required', 'array:lat,lng'],
            'boundary_coordinates.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'boundary_coordinates.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'boundary_coordinates.required' => __('farmer.popup.map.required'),
            'boundary_coordinates.array' => __('farmer.popup.map.required'),
            'boundary_coordinates.min' => __('farmer.popup.map.required'),
        ]);

        $coordinates = array_map(fn (array $point): array => [
            'lat' => round((float) $point['lat'], 7),
            'lng' => round((float) $point['lng'], 7),
        ], $validated['boundary_coordinates']);

        $place = LianGeography::locate($coordinates);
        if (! $place['inside'] || $place['barangay'] === null) {
            throw ValidationException::withMessages([
                'boundary_coordinates' => [match (true) {
                    $place['partly'] => __('farmer.popup.map.partly'),
                    $place['inside'] => __('farmer.popup.map.unknown'),
                    default => __('farmer.popup.map.outside'),
                }],
            ]);
        }
        if (! $measurement->isValidPolygon($coordinates)) {
            throw ValidationException::withMessages(['boundary_coordinates' => [__('farmer.popup.map.crossed')]]);
        }
        $measured = $measurement->measure($coordinates);

        $user = $request->user();
        $profile = $user->farmerProfile()->firstOrCreate([], [
            'full_name' => $user->name,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'barangay' => $place['barangay'],
            'farm_type' => \App\Models\FarmerProfile::FARM_TYPE_RAINFED,
        ]);
        $boundary = FarmBoundary::query()->updateOrCreate(['farm_id' => $farm->getKey()], [
            'farmer_profile_id' => $profile->getKey(),
            'boundary_coordinates' => $coordinates,
            'barangay' => $place['barangay'],
            'calculated_area_hectares' => $measured['area_hectares'],
            'calculated_perimeter_meters' => $measured['perimeter_meters'],
        ]);

        // The outline is where the farm really is: its barangay follows the map.
        $farm->barangay = $place['barangay'];
        $this->farms->sizeFromMap($farm, (float) $measured['area_hectares']);
        ActiveFarm::forget($user);
        FarmerDashboardService::forgetCached($user);

        SystemAuditLogger::record('Updated Farm Boundary', $request, [
            'record_type' => 'FarmBoundary',
            'record_id' => $boundary->id,
            'farm_id' => $farm->getKey(),
            'farmer_profile_id' => $profile->getKey(),
            'farm_barangay' => $place['barangay'],
            'area_hectares' => $measured['area_hectares'],
            'perimeter_meters' => $measured['perimeter_meters'],
        ]);

        return redirect()->route('farmer.farms.map', $farm)->with('success', __('farms.map.saved', ['size' => FarmSize::hectares((float) $measured['area_hectares'])]));
    }

    public function deleteMap(Request $request, int $farm): RedirectResponse
    {
        $farm = $this->ownFarm($request, $farm);
        $boundary = $farm->boundary;
        if ($boundary !== null) {
            $boundary->delete();
            $farm->forceFill(['location_method' => Farm::LOCATION_BARANGAY])->save();
            FarmerDashboardService::forgetCached($request->user());
            SystemAuditLogger::record('Deleted Farm Boundary', $request, ['record_type' => 'FarmBoundary', 'record_id' => $boundary->id, 'farm_id' => $farm->getKey()]);
        }

        return redirect()->route('farmer.farms.map', $farm)->with('success', __('farms.map.deleted', ['name' => $farm->farm_name]));
    }

    /** One of the signed-in farmer's own farms in use; anything else is "not found" (never another farmer's). */
    private function ownFarm(Request $request, int $id): Farm
    {
        $this->ensureAvailable();

        return Farm::query()->ownedBy($request->user())->active()->with('boundary')->findOrFail($id);
    }

    private function ensureAvailable(): void
    {
        abort_unless(ActiveFarm::available(), 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $ranges = array_keys(Farm::ESTIMATE_RANGES);
        $data = $request->validate([
            'farm_name' => ['nullable', 'string', 'max:80'],
            'barangay' => ['required', 'string', Rule::in(LianBarangays::all())],
            'location_description' => ['nullable', 'string', 'max:160'],
            'farm_type' => ['nullable', 'string', Rule::in(['Irrigated', 'Rainfed'])],
            'farm_size_method' => ['required', Rule::in(Farm::SIZE_METHODS)],
            'farm_size_value' => ['nullable', 'required_if:farm_size_method,'.Farm::SIZE_EXACT, 'numeric', 'gt:0', 'max:100000000'],
            'farm_size_unit' => ['nullable', 'required_if:farm_size_method,'.Farm::SIZE_EXACT, Rule::in([Farm::UNIT_HECTARES, Farm::UNIT_SQUARE_METERS])],
            'farm_size_estimate_range' => ['nullable', 'required_if:farm_size_method,'.Farm::SIZE_ESTIMATED, Rule::in($ranges)],
        ], [
            'barangay.*' => __('farms.errors.barangay'),
            'farm_size_value.*' => __('farms.errors.size_value'),
            'farm_size_estimate_range.*' => __('farms.errors.size_range'),
        ]);

        // At most 10,000 hectares however it was entered (square meters are turned into hectares).
        $validator = validator($data, []);
        $validator->after(function (Validator $v) use ($data): void {
            if (($data['farm_size_method'] ?? null) === Farm::SIZE_EXACT) {
                $hectares = ($data['farm_size_unit'] ?? null) === Farm::UNIT_SQUARE_METERS ? (float) $data['farm_size_value'] / 10000 : (float) $data['farm_size_value'];
                if ($hectares < 0.0001 || $hectares > 10000) {
                    $v->errors()->add('farm_size_value', __('farms.errors.size_value'));
                }
            }
        });
        $validator->validate();

        return $data;
    }

    private function audit(string $action, Request $request, Farm $farm): void
    {
        SystemAuditLogger::record($action, $request, [
            'record_type' => 'Farm',
            'record_id' => $farm->getKey(),
            'farm_barangay' => $farm->barangay,
            'farm_size_method' => $farm->farm_size_method,
        ]);
    }

    /** Back to the page the selector was used on (same site only), else the farmer's Home. */
    private function backTo(Request $request): string
    {
        $previous = url()->previous();

        return is_string($previous) && str_starts_with($previous, url('/')) && ! str_contains($previous, '/farmer/farms/active')
            ? $previous
            : route('farmer.dashboard');
    }
}
