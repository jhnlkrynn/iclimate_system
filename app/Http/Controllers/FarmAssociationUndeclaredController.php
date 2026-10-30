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
use Illuminate\View\View;

/**
 * Undeclared Farms (MAO, next to Association Requests): farms in use whose association question has no answer yet,
 * counted per barangay (declared vs not declared) and listed with checkboxes. "Set association" answers for the
 * checked farms of ONE barangay: one of that barangay's associations, or "not a member". The farms are marked as set
 * by the MAO (FarmAssociations::setByMao), the farmer sees "Set by MAO" and can still change it, and no one is notified.
 * A technologist sees and sets only their own barangays (MaoScope); each bulk set is one MAO Activity Log entry.
 */
class FarmAssociationUndeclaredController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): View
    {
        abort_unless(FarmAssociations::available(), 404);
        $barangay = in_array($request->query('barangay'), LianBarangays::all(), true) ? $request->query('barangay') : null;

        $counts = MaoScope::apply(Farm::query()->active()->whereNotNull('farms.barangay'), 'farms.barangay', $request->user())
            ->toBase()
            ->selectRaw('farms.barangay as barangay, COUNT(*) as total, SUM(CASE WHEN farms.association_status IS NULL THEN 1 ELSE 0 END) as undeclared')
            ->groupBy('farms.barangay')->orderBy('farms.barangay')->get()
            ->map(fn ($row): array => ['barangay' => (string) $row->barangay, 'undeclared' => (int) $row->undeclared, 'declared' => (int) $row->total - (int) $row->undeclared]);

        $query = MaoScope::apply(Farm::query()->active()->whereNull('association_status'), 'farms.barangay', $request->user())
            ->with('user:id,name')
            ->when($barangay, fn ($q) => $q->where('farms.barangay', $barangay));

        return view('mao.farm-associations-undeclared', [
            'barangay' => $barangay,
            'barangays' => $counts->pluck('barangay')->all(),
            'counts' => $counts,
            'farms' => $query->orderBy('farms.barangay')->orderBy('farms.id')->paginate(self::PER_PAGE)->withQueryString(),
            // Associations are offered only once the list is down to one barangay.
            'choices' => $barangay !== null ? FarmAssociations::optionsFor($barangay) : collect(),
            'canSet' => $barangay !== null && MaoScope::canManageBarangay($barangay, $request->user()),
        ]);
    }

    /** "Set association" for the checked farms (all in one barangay). */
    public function update(Request $request): RedirectResponse
    {
        abort_unless(FarmAssociations::available(), 404);
        $data = $request->validate([
            'farm_ids' => ['required', 'array', 'min:1', 'max:200'],
            'farm_ids.*' => ['integer', 'distinct'],
            'answer' => ['required', 'string', 'regex:/^(not_member|org:\d+)$/'],
        ], [
            'farm_ids.required' => 'Tick at least one farm.',
            'answer.*' => 'Choose an association, or "Not a member".',
        ]);
        $back = fn (string $error) => back()->withInput()->with('error', $error);

        $farms = Farm::query()->active()->whereNull('association_status')->whereIn('id', $data['farm_ids'])->get();
        if ($farms->count() !== count($data['farm_ids'])) {
            return $back('Some of the ticked farms already have an answer or are no longer in use. Refresh the list and try again.');
        }
        $places = $farms->map(fn (Farm $farm): ?string => LianBarangays::canonicalName($farm->barangay))->unique()->values();
        if ($places->count() !== 1 || $places->first() === null) {
            return $back('The ticked farms are in more than one barangay. Filter the list to one barangay first.');
        }
        $barangay = $places->first();
        abort_unless(MaoScope::canManageBarangay($barangay, $request->user()), 403, 'These farms are in a barangay outside your assigned coverage.');

        $organization = null;
        if ($data['answer'] !== 'not_member') {
            $organization = FarmerOrganization::query()->find((int) substr($data['answer'], 4));
            if ($organization === null || ! FarmAssociations::inBarangay($organization->id, $barangay)) {
                return $back('Choose an association in '.$barangay.'.');
            }
        }

        $status = $organization === null ? Farm::ASSOCIATION_NOT_MEMBER : Farm::ASSOCIATION_MEMBER;
        $count = FarmAssociations::setByMao($farms, $request->user(), $status, $organization?->id);
        $label = $organization?->name ?? 'Not a member';
        SystemAuditLogger::record('Updated Farm Associations', $request, [
            'record_type' => 'Farm',
            'record_label' => $count.' '.($count === 1 ? 'farm' : 'farms').' in '.$barangay.': '.$label,
            'barangay' => $barangay,
            'association' => $label,
            'farm_ids' => $farms->pluck('id')->all(),
        ]);

        return redirect()->route('mao.farm-associations.undeclared', ['barangay' => $barangay])
            ->with('success', $count.' '.($count === 1 ? 'farm' : 'farms').' in '.$barangay.' set to '.$label.'.');
    }
}
