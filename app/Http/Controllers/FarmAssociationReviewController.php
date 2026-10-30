<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Services\Farms\FarmAssociations;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Association Requests (MAO): farms whose farmer answered "my association isn't listed". The MAO links the farm to an
 * association of its barangay, adds the association and links it, or turns the name down (the farm becomes "not a
 * member" and the farmer sees the MAO's note on the farm card). Same pattern as Farmer Approvals: a technologist sees
 * and decides the farms of their own barangays (MaoScope), and every decision lands in the MAO Activity Log.
 */
class FarmAssociationReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(FarmAssociations::available(), 404);
        $barangay = in_array($request->query('barangay'), LianBarangays::all(), true) ? $request->query('barangay') : null;

        $query = Farm::query()->active()
            ->where('association_status', Farm::ASSOCIATION_PENDING)
            ->with('user:id,name,email,contact_number')
            ->when($barangay, fn ($q) => $q->where('farms.barangay', $barangay));
        MaoScope::apply($query, 'farms.barangay', $request->user());
        $pendingCount = (clone $query)->count();

        return view('mao.farm-associations', [
            'barangay' => $barangay,
            'barangays' => LianBarangays::all(),
            'farms' => $query->oldest('updated_at')->oldest('id')->paginate(CrudController::PER_PAGE)->withQueryString(),
            'pendingCount' => $pendingCount,
            'associations' => FarmAssociations::all(),
        ]);
    }

    /** Links the farm to an association of its barangay. */
    public function link(Request $request, Farm $farm): RedirectResponse
    {
        $this->ensurePending($request, $farm);
        $data = $request->validate(['farmer_organization_id' => ['required', 'integer']]);
        if (! FarmAssociations::inBarangay((int) $data['farmer_organization_id'], $farm->barangay)) {
            throw ValidationException::withMessages(['farmer_organization_id' => 'Choose an association in '.$farm->barangay.'.']);
        }
        $organization = FarmerOrganization::query()->findOrFail((int) $data['farmer_organization_id']);

        $typed = $farm->association_other_name;
        $this->linkTo($farm, $organization);
        SystemAuditLogger::record('Approved Farm Association', $request, $this->context($farm, $organization->name, ['requested_name' => $typed]));
        FarmAssociations::notifyFarmer($farm, 'linked', ['association' => $organization->name, 'name' => $typed]);

        return back()->with('success', $farm->farm_name.' is now linked to '.$organization->name.'.');
    }

    /** Adds the association the farmer named (in the farm's barangay) and links the farm to it. */
    public function store(Request $request, Farm $farm): RedirectResponse
    {
        $this->ensurePending($request, $farm);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('farmer_organizations', 'name')],
            'barangay' => ['required', Rule::in([LianBarangays::canonicalName($farm->barangay)])],
            'farm_type' => ['required', Rule::in(['Irrigated', 'Rainfed'])],
        ], [
            'name.unique' => 'An association with this name already exists. Link the farm to it instead.',
            'barangay.in' => 'The association must be in the farm\'s barangay ('.$farm->barangay.').',
        ]);

        $organization = FarmerOrganization::query()->create([
            'name' => trim($data['name']),
            'barangay' => $data['barangay'],
            'farm_type' => $data['farm_type'],
        ]);
        SystemAuditLogger::record('Created Farmer Association', $request, [
            'record_type' => 'Farmer association', 'record_id' => $organization->id, 'record_label' => $organization->name, 'barangay' => $organization->barangay,
        ]);
        $typed = $farm->association_other_name;
        $this->linkTo($farm, $organization);
        SystemAuditLogger::record('Approved Farm Association', $request, $this->context($farm, $organization->name, ['requested_name' => $typed]));
        FarmAssociations::notifyFarmer($farm, 'created', ['association' => $organization->name, 'name' => $typed]);

        return back()->with('success', $organization->name.' was added and '.$farm->farm_name.' is linked to it.');
    }

    /** Turns the name down: the farm is "not a member", with the MAO's note for the farmer. */
    public function reject(Request $request, Farm $farm): RedirectResponse
    {
        $this->ensurePending($request, $farm);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $typed = $farm->association_other_name;
        $farm->forceFill([
            'association_status' => Farm::ASSOCIATION_NOT_MEMBER,
            'farmer_organization_id' => null,
            'association_other_name' => null,
            'association_note' => trim($data['note']),
        ]);
        $farm->associationAnswered = true;
        $farm->save();
        SystemAuditLogger::record('Rejected Farm Association', $request, $this->context($farm, $typed, ['reason' => $data['note']]));
        FarmAssociations::notifyFarmer($farm, 'rejected', ['name' => $typed, 'note' => trim($data['note'])]);

        return back()->with('success', 'The association request for '.$farm->farm_name.' was rejected.');
    }

    private function linkTo(Farm $farm, FarmerOrganization $organization): void
    {
        $farm->forceFill([
            'association_status' => Farm::ASSOCIATION_MEMBER,
            'farmer_organization_id' => $organization->id,
            'association_other_name' => null,
            'association_note' => null,
        ]);
        $farm->associationAnswered = true;
        $farm->save();   // Farm::booted() adds the membership
    }

    /** A farm in use that waits for review, in the MAO user's barangays. */
    private function ensurePending(Request $request, Farm $farm): void
    {
        abort_unless(FarmAssociations::available() && $farm->archived_at === null && $farm->association_status === Farm::ASSOCIATION_PENDING, 404);
        abort_unless(MaoScope::canManageBarangay($farm->barangay, $request->user()), 403, 'This farm is in a barangay outside your assigned coverage.');
    }

    /** @return array<string, mixed> */
    private function context(Farm $farm, ?string $association, array $more = []): array
    {
        return array_filter([
            'record_type' => 'Farm',
            'record_id' => $farm->getKey(),
            'record_label' => trim(($farm->user?->name ?? '').' – '.$farm->farm_name.($association ? ' ('.$association.')' : ''), ' –'),
            'barangay' => $farm->barangay,
            'farmer_id' => $farm->user_id,
        ] + $more, fn ($value) => $value !== null && $value !== '');
    }
}
