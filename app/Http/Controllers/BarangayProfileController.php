<?php

namespace App\Http\Controllers;

use App\Models\BarangayProfile;
use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: Barangays (MAO, a tab of Records). Lian's 19 barangays with their
 * location, rice area, the MAO's risk classification and whether they are flood- or drought-prone. Next to the MAO's
 * own figures: the farmers and farm area registered in iClimate.
 */
class BarangayProfileController extends Controller
{
    public function index(): View
    {
        $profiles = BarangayProfile::query()->get()->keyBy('barangay');
        $farmers = FarmerProfile::query()->selectRaw('barangay, count(*) as total')->groupBy('barangay')->pluck('total', 'barangay');
        $registeredArea = SchemaCache::hasTable('farms')
            ? Farm::query()->active()->get()->groupBy('barangay')->map(fn ($farms) => round((float) $farms->sum(fn (Farm $farm) => $farm->knownHectares() ?? 0), 2))
            : collect();

        $rows = collect(LianBarangays::all())->map(fn (string $name) => (object) [
            'barangay' => $name,
            'profile' => $profiles->get($name) ?? new BarangayProfile(['barangay' => $name]),
            'farmers' => (int) ($farmers[$name] ?? 0),
            'registered_area' => $registeredArea[$name] ?? null,
        ]);

        return view('mao.barangays', [
            'rows' => $rows,
            'classifications' => BarangayProfile::CLASSIFICATIONS,
            'summary' => [
                'flood' => $profiles->where('flood_prone', true)->count(),
                'drought' => $profiles->where('drought_prone', true)->count(),
                'high' => $profiles->where('risk_classification', 'High')->count(),
                'area' => $profiles->sum(fn ($p) => (float) $p->farm_area_hectares),
            ],
        ]);
    }

    public function update(Request $request, string $barangay): RedirectResponse
    {
        abort_unless(in_array($barangay, LianBarangays::all(), true), 404);
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:13.8,14.2'],
            'longitude' => ['nullable', 'numeric', 'between:120.5,120.8'],
            'farm_area_hectares' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'risk_classification' => ['nullable', Rule::in(BarangayProfile::CLASSIFICATIONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['flood_prone'] = $request->boolean('flood_prone');
        $data['drought_prone'] = $request->boolean('drought_prone');
        $data['updated_by'] = $request->user()->getKey();

        $profile = BarangayProfile::query()->updateOrCreate(['barangay' => $barangay], $data);
        SystemAuditLogger::forModel('updated', $profile, $request, ['barangay' => $barangay]);

        return redirect()->to(route('mao.barangays.index').'#b-'.str($barangay)->slug())->with('success', $barangay.' saved.');
    }
}
