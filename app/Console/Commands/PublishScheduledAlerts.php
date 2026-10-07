<?php

namespace App\Console\Commands;

use App\Services\Advisories\AdvisoryAlertService;
use Illuminate\Console\Command;

/** Manuscript – Naicel (2026-10-08), Phase 6: publishes the MAO alerts whose scheduled time has come (every minute). */
class PublishScheduledAlerts extends Command
{
    protected $signature = 'iclimate:publish-scheduled-alerts';

    protected $description = 'Publish scheduled MAO alerts that are due and notify their farmers';

    public function handle(AdvisoryAlertService $alerts): int
    {
        $count = $alerts->publishDue();
        $this->info($count === 0 ? 'No scheduled alert is due.' : "Published {$count} scheduled alert advisories.");

        return self::SUCCESS;
    }
}
