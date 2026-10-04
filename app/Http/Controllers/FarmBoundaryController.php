<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\FarmBoundaryMeasurementService;
use App\Services\SystemAuditLogger;
use App\Support\LianGeography;
use App\Support\SchemaCache;
use App\Models\FarmBoundary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FarmBoundaryController extends Controller
{
    private const MAX_POINTS = 100;

    public function edit(Request $request): View
    {
        $profile = $this->profileFor($request);

        return view('farm-boundaries.edit', [
            'profile' => $profile,
            'boundary' => $profile->farmBoundary,
        ]);
    }

    public function update(Request $request, FarmBoundaryMeasurementService $measurement): RedirectResponse
    {
        $this->save($request, $measurement);

        return redirect()->route('farmer.boundary.edit')->with('success', 'Farm boundary saved successfully.');
    }

    /** The farmer dashboard's "Map My Farm" pop-up: the same boundary, validation and measurement as the page. */
    public function updateFromDashboard(Request $request, FarmBoundaryMeasurementService $measurement): JsonResponse
    {
        $boundary = $this->save($request, $measurement);

        return response()->json([
            'message' => __('farmer.popup.map.saved'),
            'boundary_coordinates' => $boundary->boundary_coordinates,
            'barangay' => LianGeography::locate($boundary->boundary_coordinates)['barangay'],
        ]);
    }

    private function save(Request $request, FarmBoundaryMeasurementService $measurement): FarmBoundary
    {
        $profile = $this->profileFor($request);
        if (is_string($request->input('boundary_coordinates'))) {
            $decoded = json_decode($request->input('boundary_coordinates'), true);
            $request->merge(['boundary_coordinates' => is_array($decoded) ? $decoded : null]);
        }

        $validated = $request->validate([
            'farm_area' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'boundary_coordinates' => ['required', 'array', 'min:3', 'max:'.self::MAX_POINTS],
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

        // The whole outline must lie inside the official Lian boundary, and its barangay is worked out here from the
        // official barangay boundaries: nothing the browser says about where the farm is gets trusted.
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
            throw ValidationException::withMessages([
                'boundary_coordinates' => [__('farmer.popup.map.crossed')],
            ]);
        }
        $measurements = $measurement->measure($coordinates);
        if ($request->has('farm_area')) {
            $profile->update(['farm_area' => $validated['farm_area'] ?? null]);
        }

        // The farm's barangay goes on the farm's own record; the barangay on the farmer's profile is not changed.
        $boundary = $profile->farmBoundary()->updateOrCreate([], [
            'boundary_coordinates' => $coordinates,
            ...(SchemaCache::hasColumn('farm_boundaries', 'barangay') ? ['barangay' => $place['barangay']] : []),
            'calculated_area_hectares' => $measurements['area_hectares'],
            'calculated_perimeter_meters' => $measurements['perimeter_meters'],
        ]);

        SystemAuditLogger::record('Updated Farm Boundary', $request, [
            'record_type' => 'FarmBoundary',
            'record_id' => $boundary->id,
            'farmer_profile_id' => $profile->id,
            'farm_barangay' => $place['barangay'],
            'area_hectares' => $measurements['area_hectares'],
            'perimeter_meters' => $measurements['perimeter_meters'],
            'declared_area_hectares' => $profile->farm_area,
        ]);

        $this->forgetDashboard($request);

        return $boundary;
    }

    /** The farmer dashboard keeps its page for a minute: its "Draw your farm on the map" item must not outlive this change. */
    private function forgetDashboard(Request $request): void
    {
        Cache::forget(FarmerDashboardService::cacheKey($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request);
        $boundary = $profile->farmBoundary;

        if ($boundary) {
            $boundary->delete();
            SystemAuditLogger::record('Deleted Farm Boundary', $request, [
                'record_type' => 'FarmBoundary',
                'record_id' => $boundary->id,
                'farmer_profile_id' => $profile->id,
            ]);
            $this->forgetDashboard($request);
        }

        return redirect()->route('farmer.boundary.edit')->with('success', 'Farm boundary cleared.');
    }

    private function profileFor(Request $request): FarmerProfile
    {
        $user = $request->user();

        return $user->farmerProfile()->firstOrCreate([], [
            'full_name' => $user->name,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'barangay' => $user->barangay ?: 'Poblacion',
            'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
        ]);
    }
}
