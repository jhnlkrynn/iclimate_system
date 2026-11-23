<?php

namespace App\Http\Middleware;

use App\Support\SchemaCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Existing users created before RA 10173 consent tracking must accept the Data Privacy Notice once on next login.
 */
class EnsurePrivacyAgreed
{
    private const OPEN = [
        'privacy.consent',
        'privacy.consent.store',
        'privacy.notice',
        'logout',
        'password.first-change',
        'password.first-change.update',
        'password.first-change.skip',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user
            || ! SchemaCache::hasColumn('users', 'privacy_agreed_at')
            || $user->privacy_agreed_at !== null
            || $request->routeIs(...self::OPEN)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('privacy.consent_required')], Response::HTTP_CONFLICT);
        }

        return redirect()->route('privacy.consent');
    }
}
