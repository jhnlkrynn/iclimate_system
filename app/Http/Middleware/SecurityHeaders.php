<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add baseline browser security headers without blocking the app's current
     * inline styles, Vite assets, Google Fonts, or Bootstrap CDN usage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Strict policy (deployment audit 2026-11-19 hardening): a fresh nonce per response for every <script> tag,
        // also given to the Vite bundles; set before the view renders. SECURITY_CSP_STRICT=false returns the old
        // loose policy as a rollback switch.
        $strict = (bool) config('security.csp_strict', true);
        $nonce = $strict ? Vite::useCspNonce() : null;

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        $response->headers->set('Content-Security-Policy', implode('; ', $nonce !== null ? self::strictPolicy($nonce) : self::loosePolicy()));

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

    /**
     * Scripts run only with this response's nonce ('strict-dynamic' lets those scripts add others, e.g. a fetched
     * widget's initializer). 'unsafe-inline' and https: are ignored by browsers that understand nonces and only keep
     * very old browsers working. No 'unsafe-eval': Alpine.js is the CSP build (@alpinejs/csp, 2026-11-20) and no
     * bundled code evaluates strings. Inline style attributes are still allowed (style-src).
     *
     * @return list<string>
     */
    public static function strictPolicy(string $nonce): array
    {
        return [
            "default-src 'self'",
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-inline' https:",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' https:",
            "font-src 'self' https: data:",
            "img-src 'self' https: data: blob:",
            "connect-src 'self' https: ws: wss:",
            "frame-src 'self' https:",
            "media-src 'self' https: data: blob:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];
    }

    /** The previous policy, kept as a rollback switch (SECURITY_CSP_STRICT=false). @return list<string> */
    public static function loosePolicy(): array
    {
        return [
            "default-src 'self' https: data: blob: ws: wss: 'unsafe-inline' 'unsafe-eval'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];
    }

    private function isHtmlResponse(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
