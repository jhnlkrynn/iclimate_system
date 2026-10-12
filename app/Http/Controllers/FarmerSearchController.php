<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Support\MaoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panel revision 1.4: live farmer search (resources/js/panel-revisions.js, [data-live-search]). Every word typed must
 * match, partly and in any letter case, one of: name, farmer ID (RSBSA no. or record no.), barangay, organization.
 * Results follow the MAO user's coverage (App\Support\MaoScope). At most 12 results, best matches (name starts with
 * the text) first.
 */
class FarmerSearchController extends Controller
{
    public const LIMIT = 12;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        if ($q === '') {
            return response()->json(['query' => '', 'results' => []]);
        }
        $words = array_slice(preg_split('/\s+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);

        $query = FarmerProfile::query()->with(['organizations:farmer_organizations.id,name', 'farmerOrganization:id,name', 'user:id,status']);
        MaoScope::apply($query, 'farmer_profiles.barangay', $request->user());
        foreach ($words as $word) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';
            $query->where(function (Builder $w) use ($like, $word): void {
                $w->whereRaw('LOWER(farmer_profiles.full_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(farmer_profiles.rsbsa_no, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(farmer_profiles.barangay, \'\')) LIKE ?', [$like])
                    ->orWhereHas('organizations', fn (Builder $o) => $o->whereRaw('LOWER(farmer_organizations.name) LIKE ?', [$like]))
                    ->orWhereHas('farmerOrganization', fn (Builder $o) => $o->whereRaw('LOWER(name) LIKE ?', [$like]));
                if (ctype_digit($word)) {
                    $w->orWhere('farmer_profiles.id', (int) $word);
                }
            });
        }

        $first = mb_strtolower($words[0] ?? '');
        $results = $query->orderBy('full_name')->limit(60)->get()
            ->sortBy(fn (FarmerProfile $p): int => str_starts_with(mb_strtolower((string) $p->full_name), $first) ? 0 : 1)
            ->take(self::LIMIT)
            ->map(fn (FarmerProfile $p): array => [
                'id' => $p->id,
                'title' => (string) $p->full_name,
                'meta' => implode(' · ', array_filter([
                    'Brgy. '.$p->barangay,
                    $p->organization_names,
                    $p->rsbsa_no ? 'RSBSA '.$p->rsbsa_no : 'ID '.$p->id,
                ])),
                'badge' => $p->user?->status && $p->user->status !== \App\Models\User::STATUS_ACTIVE ? $p->user->status : null,
                'url' => route('farmer-profiles.show', $p->id),
                'modal' => true,
            ])->values();

        return response()->json(['query' => $q, 'results' => $results]);
    }
}
