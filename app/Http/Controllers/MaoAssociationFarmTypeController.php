<?php

namespace App\Http\Controllers;

use App\Models\FarmerOrganization;
use App\Services\Risk\HeatMapLayerService;
use App\Services\SystemAuditLogger;
use App\Support\CacheScope;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaoAssociationFarmTypeController extends Controller
{
    public function index(Request $request): View
    {
        $barangay = in_array($request->query('barangay'), LianBarangays::all(), true) ? $request->query('barangay') : null;
        $status = in_array($request->query('status'), ['pending', 'confirmed'], true) ? $request->query('status') : null;
        $hasSource = Schema::hasColumn('farmer_organizations', 'farm_type_source');

        $query = FarmerOrganization::query()
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay))
            ->when($hasSource && $status === 'pending', fn ($q) => $q->whereNotNull('farm_type_source'))
            ->when($hasSource && $status === 'confirmed', fn ($q) => $q->whereNull('farm_type_source')->whereNotNull('farm_type'))
            ->orderBy('barangay')
            ->orderBy('name');

        MaoScope::apply($query, 'barangay', $request->user());

        $associations = $query->get();
        $pendingCount = $hasSource
            ? FarmerOrganization::query()
                ->tap(fn ($q) => MaoScope::apply($q, 'barangay', $request->user()))
                ->whereNotNull('farm_type_source')
                ->count()
            : 0;

        return view('mao.association-farm-types', [
            'associations' => $associations,
            'barangay' => $barangay,
            'barangays' => LianBarangays::all(),
            'status' => $status,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function update(Request $request, FarmerOrganization $association): RedirectResponse
    {
        abort_unless(MaoScope::canManageBarangay($association->barangay, $request->user()), 403);

        $data = $request->validate([
            'farm_type' => ['required', 'string', Rule::in(['Irrigated', 'Rainfed'])],
        ]);

        $previous = $association->farm_type;
        $association->farm_type = $data['farm_type'];

        if (Schema::hasColumns('farmer_organizations', ['farm_type_source', 'farm_type_confirmed_at', 'farm_type_confirmed_by'])) {
            $association->farm_type_source = null;
            $association->farm_type_confirmed_at = now();
            $association->farm_type_confirmed_by = $request->user()->getKey();
        }

        $association->save();

        CacheScope::bump('farmers');
        CacheScope::bump('heat-map-layers');
        app(HeatMapLayerService::class)->forgetCache();

        SystemAuditLogger::record('Confirmed Association Farm Type', $request, [
            'record_type' => 'Farmer association',
            'record_id' => $association->getKey(),
            'record_label' => $association->name,
            'barangay' => $association->barangay,
            'previous_farm_type' => $previous,
            'farm_type' => $association->farm_type,
        ]);

        return back()->with('success', "{$association->name} confirmed as {$association->farm_type}.");
    }
}
