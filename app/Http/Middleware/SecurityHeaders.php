<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline browser security headers without blocking the app's current
     * inline styles, Vite assets, Google Fonts, or Bootstrap CDN usage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // A deliberately loose content policy: it still allows the app's inline styles/scripts, Google Fonts, the
        // Bootstrap CDN and OpenStreetMap tiles (any https source), but it stops the page being framed by another
        // site, <base> hijacking, forms posting to another site, and plugins. Tighten script-src once the inline
        // scripts have moved into asset files.
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self' https: data: blob: ws: wss: 'unsafe-inline' 'unsafe-eval'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));

        // Do not advertise the PHP version (set expose_php = Off in php.ini as well; this covers servers where it is on).
        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() && $this->isHtmlResponse($response)) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
