<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page removed from the farmer's menu sends a farmer to its replacement (other roles pass through), so old links and
 * bookmarks keep working and nothing is deleted: 'farmer.redirect:farmer.dashboard' or 'farmer.redirect:route,fragment'.
 */
class RedirectFarmers
{
    public function handle(Request $request, Closure $next, string $route, ?string $fragment = null): Response
    {
        if ($request->user()?->role !== User::ROLE_FARMER) {
            return $next($request);
        }

        // Always a redirect (never an error page): 303 so a form post opens the replacement with GET.
        return redirect()->to(route($route).($fragment ? '#'.$fragment : ''), 303);
    }
}
