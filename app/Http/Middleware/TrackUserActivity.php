<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    private const THROTTLE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && Cache::add("activity-throttle:{$user->id}", true, self::THROTTLE_SECONDS)) {
            User::whereKey($user->id)->update(['last_active_at' => now()]);
        }

        return $next($request);
    }
}
