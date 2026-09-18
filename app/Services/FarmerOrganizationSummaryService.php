<?php

namespace App\Services;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
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
            $organizations = FarmerOrganization::query()
                ->withCount('farmerProfiles')
                ->orderBy('id')
                ->get();
            $farmerTotals = FarmerProfile::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN farmer_organization_id IS NULL THEN 1 ELSE 0 END) as without_organization')
                ->selectRaw('COALESCE(SUM(farm_area), 0) as total_farm_area')
                ->first();
            $farmersWithoutOrganization = (int) $farmerTotals->without_organization;

            $chartRows = $organizations
                ->map(fn (FarmerOrganization $organization): array => [
                    'label' => $organization->name,
                    'count' => $organization->farmer_profiles_count,
                ])
                ->push([
                    'label' => 'None',
                    'count' => $farmersWithoutOrganization,
                ])
                ->values();

            return [
                'farmerOrganizationSummary' => [
                    'totalFarmers' => (int) $farmerTotals->total,
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
