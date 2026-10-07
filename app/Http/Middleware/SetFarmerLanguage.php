<?php

namespace App\Http\Middleware;

use App\Support\FarmerLanguage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the farmer's chosen language (English or Filipino) for the Farmer dashboard and the requests it makes
 * (refresh, pop-up saves). Other pages are not affected.
 */
class SetFarmerLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Farmers only: MAO staff who picked Filipino on their dashboard (App\Support\MaoLanguage) still read the pages
        // shared with farmers (Heat Map, Weather Analysis) in English, as those pages are written for staff.
        $user = $request->user();
        if ($user === null || $user->role === \App\Models\User::ROLE_FARMER) {
            app()->setLocale(FarmerLanguage::resolve($request));
        }

        return $next($request);
    }
}
