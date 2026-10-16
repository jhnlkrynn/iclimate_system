<?php

namespace App\Services\Analytics;

use App\Http\Controllers\DashboardController;
use App\Support\PageCache;
use App\Support\StaleCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The expensive shared dashboard payloads (MAO dashboard, MAO Operations, IT overview), prepared ahead of the people
 * who open them. One place decides each payload's key, lifetime and stale window, used by the pages, their timed
 * refresh, the Refresh buttons and the warm-up command alike.
 *
 *   page or timed refresh  → the prepared copy. When it is due, the last verified copy (inside its stale window, with
 *                            its own build time) is shown and rebuilt after the response; only a first-ever or too-old
 *                            copy is built while the person waits.
 *   Refresh button         → rebuilt now, once: a second Refresh while one runs waits for it and shares its result.
 *   warm-up (scheduler)    → rebuilt when the data it reads changed (CacheScope) or it is getting old, unless a rebuild
 *                            is already running. A failed build keeps the last verified copy.
 */
final class PreparedDashboards
{
    /**
     * MAO dashboard: how long a copy is current, and how old a verified copy may be while it is rebuilt after it
     * expires. A change to the data it reads is never served from an older copy: the next visit rebuilds it (the
     * warm-up normally has already), so new data shows on the very next visit.
     */
    public const MAO_SECONDS = 90;

    public const MAO_MAX_STALE_SECONDS = 600;

    /** IT overview (its sections show when they were built, so a copy may be shown for up to an hour). */
    public const IT_SECONDS = 60;

    public const IT_MAX_STALE_SECONDS = 3600;

    public const IT_LATEST_KEY = 'dashboard:it:overview:4:latest';

    /** MAO Operations, the whole assembled page (about 100 queries to build); a data change shows on the next visit, as above. */
    public const OPERATIONS_SECONDS = 300;

    public const OPERATIONS_MAX_STALE_SECONDS = 900;

    /** The filters MAO Operations reads from the query string; each combination is its own prepared copy. */
    public const OPERATIONS_FILTERS = ['year', 'fb_barangay', 'fb_from', 'fb_to', 'fb_condition', 'schedule_month', 'schedule_season',
        'schedule_barangay', 'schedule_farm_type', 'schedule_farmer', 'schedule_status'];

    /** The warm-up rebuilds a copy this much before it would expire, so a visitor normally finds a current one. */
    private const WARM_MARGIN_SECONDS = 30;

    public function __construct(
        private readonly MaoDashboardService $mao,
        private readonly ItDashboardService $it,
    ) {}

    // ------------------------------------------------------------------ MAO dashboard

    public function mao(): array
    {
        return StaleCache::scoped('mao-dashboard', self::maoKey(...), self::MAO_SECONDS, fn (): array => $this->mao->payload(),
            maxStale: self::MAO_MAX_STALE_SECONDS);
    }

    /** The MAO Refresh button: the latest weather too. */
    public function refreshMao(): array
    {
        return StaleCache::refreshScoped('mao-dashboard', self::maoKey(...), self::MAO_SECONDS,
            fn (): array => $this->mao->payload(forceWeather: true));
    }

    public static function maoKey(string $token): string
    {
        return 'dashboard:mao:overview:'.$token;
    }

    // ------------------------------------------------------------------ IT overview

    public function it(): array
    {
        return StaleCache::scoped('it-dashboard', self::itKey(...), self::IT_SECONDS, fn (): array => $this->it->payload(),
            self::IT_LATEST_KEY, self::IT_MAX_STALE_SECONDS);
    }

    /** The IT Refresh button: every section rebuilt from the database. */
    public function refreshIt(): array
    {
        return StaleCache::refreshScoped('it-dashboard', self::itKey(...), self::IT_SECONDS,
            fn (): array => $this->it->payload(fresh: true), self::IT_LATEST_KEY);
    }

    public static function itKey(string $token): string
    {
        return 'dashboard:it:overview:4:'.$token;
    }

    // ------------------------------------------------------------------ MAO Operations

    /** The assembled MAO Operations page for this request's filters. */
    public function maoOperations(Request $request): array
    {
        $suffix = self::operationsSuffix($request);

        return StaleCache::scoped('mao-operations', fn (string $token): string => self::operationsKey($token, $suffix), self::OPERATIONS_SECONDS,
            fn (): array => app(DashboardController::class)->maoOperationsData($request),
            maxStale: self::OPERATIONS_MAX_STALE_SECONDS);
    }

    public static function operationsKey(string $token, string $suffix = ''): string
    {
        return 'page:mao-operations:'.$token.$suffix;
    }

    private static function operationsSuffix(Request $request): string
    {
        $vary = PageCache::queryVary($request, self::OPERATIONS_FILTERS);

        return $vary === [] ? '' : ':'.md5(json_encode($vary));
    }

    // ------------------------------------------------------------------ warm-up

    /**
     * Rebuilds whatever is due: the MAO dashboard, the IT overview and MAO Operations as it first opens (no filters).
     * Filtered MAO Operations views are prepared when someone opens them.
     *
     * @return array<string, string> payload => what happened ("12.3 s", "current", "busy" or "failed: ...")
     */
    public function warm(): array
    {
        $plain = Request::create('/mao/operations');

        return [
            'MAO dashboard' => $this->warmOne('mao-dashboard', self::maoKey(...), self::MAO_SECONDS, fn (): array => $this->mao->payload()),
            'IT overview' => $this->warmOne('it-dashboard', self::itKey(...), self::IT_SECONDS, function (): array {
                $payload = $this->it->payload();
                $this->it->activity(30);                         // the Overview's 30-day chart comes with the page
                return $payload;
            }, self::IT_LATEST_KEY),
            'MAO Operations' => $this->warmOne('mao-operations', fn (string $token): string => self::operationsKey($token), self::OPERATIONS_SECONDS,
                fn (): array => app(DashboardController::class)->maoOperationsData($plain)),
        ];
    }

    /** @param Closure(string): string $keyFor */
    private function warmOne(string $consumer, Closure $keyFor, int $seconds, Closure $build, ?string $latestKey = null): string
    {
        if (! StaleCache::isDue($consumer, $keyFor, $seconds - self::WARM_MARGIN_SECONDS, $latestKey)) {
            return 'current';
        }

        $started = microtime(true);
        try {
            $built = StaleCache::prepareScoped($consumer, $keyFor, $seconds, $build, $latestKey);
        } catch (Throwable $exception) {
            // The last verified copy stays in place; the next run tries again.
            Log::warning('Dashboard preparation failed; the last verified copy stays.', ['payload' => $consumer, 'exception' => $exception::class, 'message' => $exception->getMessage()]);

            return 'failed: '.$exception->getMessage();
        }

        return $built ? number_format(microtime(true) - $started, 3).' s' : 'busy';
    }
}
