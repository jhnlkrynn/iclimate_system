<?php

namespace App\Support;

/**
 * Tells a service whether a person is waiting on the current request, and how
 * patient it may therefore be with slow outside services (weather providers).
 *
 *  - A page request (HTML): short provider limits, no retries, and it may use
 *    a recent stored result instead of calling out. A scheduled job keeps the
 *    stored result fresh, so a page never has to wait for a provider.
 *  - A JSON request (live widgets polling in the background): reads the same
 *    stored/cache-only snapshot as HTML pages. Fresh provider data is prepared
 *    by the scheduler or an explicit refresh operation.
 *  - No web request (commands, queue workers): unchanged, patient behaviour.
 */
class InteractiveRequest
{
    /** True while a web page or API request is being answered (someone is waiting), false in commands and queue workers. */
    public static function isPageRequest(): bool
    {
        $request = app()->bound('request') ? request() : null;

        return $request !== null && $request->route() !== null;
    }

    /**
     * @return array{budget: ?float, stale_ok: bool}
     */
    public static function weatherMode(): array
    {
        $request = app()->bound('request') ? request() : null;

        if ($request === null || $request->route() === null) {
            return ['budget' => null, 'stale_ok' => false];
        }

        return [
            'budget' => max(1.0, (float) config('ai.weather_timeout', 3)),
            'stale_ok' => ! $request->expectsJson(),
        ];
    }
}
