<?php

namespace App\Services;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Shared "Farmers by Organization" summary + chart data, used by the
 * Farmer Profiles page and the IT-expert dashboard. Kept as one cached
 * source so both pages stay in sync and never compute this twice.
 */
class FarmerOrganizationSummaryService
{
    public function summarize(): array
    {
        return Cache::remember('dashboard:farmer-organizations', now()->addSeconds(45), function (): array {
            // Panel revision 1.1: counted from the memberships, so a farmer in two organizations is counted in both.
            $useMembers = \App\Support\SchemaCache::hasTable('farmer_organization_members');
            $organizations = FarmerOrganization::query()
                ->withCount($useMembers ? 'members' : 'farmerProfiles')
                ->orderBy('id')
                ->get();
            $farmerTotals = FarmerProfile::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw($useMembers
                    ? 'SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM farmer_organization_members m WHERE m.farmer_profile_id = farmer_profiles.id) THEN 1 ELSE 0 END) as without_organization'
                    : 'SUM(CASE WHEN farmer_organization_id IS NULL THEN 1 ELSE 0 END) as without_organization')
                ->selectRaw('COALESCE(SUM(farm_area), 0) as total_farm_area')
                ->first();
            $farmersWithoutOrganization = (int) $farmerTotals->without_organization;

            $chartRows = $organizations
                ->map(fn (FarmerOrganization $organization): array => [
                    'label' => $organization->name,
                    'count' => $useMembers ? $organization->members_count : $organization->farmer_profiles_count,
                ])
                ->push([
                    'label' => 'None',
                    'count' => $farmersWithoutOrganization,
                ])
                ->values();

            return [
                'farmerOrganizationSummary' => [
                    // Farmer profiles (farm records MAO works with). Not the same as farmer accounts below.
                    'totalFarmers' => (int) $farmerTotals->total,
                    // Everyone who can sign in as a Farmer, with or without a profile (same count as the IT dashboard).
                    'totalFarmerAccounts' => User::query()->where('role', User::ROLE_FARMER)->count(),
                    'totalOrganizations' => $organizations->count(),
                    'farmersWithoutOrganization' => $farmersWithoutOrganization,
                    'totalFarmArea' => (float) $farmerTotals->total_farm_area,
                ],
                'farmerOrganizationChart' => [
                    'labels' => $chartRows->pluck('label')->all(),
                    'counts' => $chartRows->pluck('count')->map(fn ($count): int => (int) $count)->all(),
                ],
            ];
        });
    }
}
