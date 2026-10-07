<?php

namespace App\Console\Commands;

use App\Http\Controllers\SystemHealthController;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rebuilds the pages that are the same for everyone (MAO dashboard, IT Expert
 * dashboard, System Health) before anyone asks, so opening one reads a
 * prepared copy. Without this, the first visitor after any data change waits
 * for the whole page to be built (dozens of database round trips).
 *
 * Runs from the scheduler every two minutes. It builds exactly what a page
 * request would build and stores it under the same key.
 */
class WarmDashboardCache extends Command
{
    protected $signature = 'iclimate:warm-dashboard-cache';

    protected $description = 'Prepare the shared MAO / IT dashboards and System Health so opening them is instant.';

    public function handle(): int
    {
        $failed = 0;

        foreach ([
            // The MAO / IT / MAO Operations payloads are prepared by iclimate:warm-dashboards (every minute, when due).
            'system health' => fn () => app()->call([app(SystemHealthController::class), 'warm']),
        ] as $label => $warm) {
            $started = microtime(true);

            try {
                $warm();
                $this->line(ucfirst($label).' prepared in '.round(microtime(true) - $started, 1).' s.');
            } catch (Throwable $exception) {
                $failed++;
                $this->warn(ucfirst($label).' could not be prepared.');
                report($exception);
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
