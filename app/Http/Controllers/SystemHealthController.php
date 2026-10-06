<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    /** How long a completed check is reused. The Refresh button (?refresh=1) always runs a new one. */
    public const REUSE_SECONDS = 120;

    /** What one run of the checks produces; the page and the scheduled warm-up both store exactly this. */
    public static function runChecks(SystemHealthService $health): array
    {
        return [
            'checks' => $health->checks(),
            'checkedAt' => now()->timezone('Asia/Manila'),
        ];
    }

    /** Runs the checks ahead of time from the scheduler so the page opens on a prepared result. */
    public function warm(SystemHealthService $health): void
    {
        PageCache::refreshShared('system-health', self::REUSE_SECONDS, fn (): array => self::runChecks($health));
    }

    public function index(Request $request, SystemHealthService $health): View
    {
        $build = fn (): array => self::runChecks($health);

        // The 17 checks need about as many database round trips, so opening the page
        // reuses a check made in the last two minutes. "checkedAt" is when the checks
        // really ran, so the page never claims to be fresher than it is.
        // A manual refresh runs the checks now and becomes the result the next visitor reuses.
        $result = $request->boolean('refresh')
            ? PageCache::refreshShared('system-health', self::REUSE_SECONDS, $build)
            : PageCache::shared('system-health', self::REUSE_SECONDS, $build);

        return view('system-health.index', [
            'checks' => $result['checks'],
            'summary' => $health->summarize($result['checks']),
            'checkedAt' => $result['checkedAt'],
            // Usage Patterns, Resources and the Data Pipeline moved here from the IT dashboard (2026-10): the overview
            // that is already prepared, read only. Null until one exists; the checks above still show.
            'it' => rescue(fn (): ?array => app(DashboardController::class)->preparedItOverview(), null, false),
        ]);
    }
}
