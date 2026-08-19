<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Services\FarmBoundaryMeasurementService;
use App\Services\SystemAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        foreach ($validated['boundary_coordinates'] as $point) {
            if ((float) $point['lat'] < 13.95 || (float) $point['lat'] > 14.12
                || (float) $point['lng'] < 120.55 || (float) $point['lng'] > 120.75) {
                throw ValidationException::withMessages([
                    'boundary_coordinates' => ['Every boundary point must be within the Lian, Batangas service area.'],
                ]);
            }
        }

        $coordinates = array_map(fn (array $point): array => [
            'lat' => round((float) $point['lat'], 7),
            'lng' => round((float) $point['lng'], 7),
        ], $validated['boundary_coordinates']);
        if (! $measurement->isValidPolygon($coordinates)) {
            throw ValidationException::withMessages([
                'boundary_coordinates' => ['The boundary must be a valid, non-self-intersecting field polygon.'],
            ]);
        }
        $measurements = $measurement->measure($coordinates);
        if ($request->has('farm_area')) {
            $profile->update(['farm_area' => $validated['farm_area'] ?? null]);
        }

        $boundary = $profile->farmBoundary()->updateOrCreate([], [
            'boundary_coordinates' => $coordinates,
            'calculated_area_hectares' => $measurements['area_hectares'],
            'calculated_perimeter_meters' => $measurements['perimeter_meters'],
        ]);

        SystemAuditLogger::record('Updated Farm Boundary', $request, [
            'record_type' => 'FarmBoundary',
            'record_id' => $boundary->id,
            'farmer_profile_id' => $profile->id,
            'area_hectares' => $measurements['area_hectares'],
            'perimeter_meters' => $measurements['perimeter_meters'],
            'declared_area_hectares' => $profile->farm_area,
        ]);

        return redirect()->route('farmer.boundary.edit')->with('success', 'Farm boundary saved successfully.');
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
