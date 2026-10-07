<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * IT is the system controller, not agricultural staff (2026-10): the agricultural pages are off the IT menu, and an IT
 * account that opens one (an old link, a bookmark, a typed address) is sent to the IT dashboard. Other roles pass
 * through, and no route or controller is removed. Same idea as RedirectFarmers.
 */
class RedirectItFromFieldPages
{
    /** Route names (with wildcards) that are no longer IT pages. */
    private const FIELD_PAGES = [
        'heat-map.*', 'planting-advisories.*', 'management.advisories.*', 'advisory-feedback.*', 'farmer-profiles.*',
        'community-feed.*', 'announcements.*', 'climate-records.*', 'rice-productions.*', 'weather-predictions.*',
        'rice-yield-predictions.*', 'weather-feedback.*', 'reports.*', 'calendar.*', 'field-reports.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== User::ROLE_IT_EXPERT || ! $request->routeIs(...self::FIELD_PAGES)) {
            return $next($request);
        }

        // The IT dashboard's Data Integrity card opens the farmer list filtered to the records that need fixing.
        if ($request->routeIs('farmer-profiles.index') && $request->filled('integrity')) {
            return $next($request);
        }

        // Always a redirect (never an error page): 303 so a form post opens the dashboard with GET.
        return redirect()->route('admin.dashboard', [], 303);
    }
}
