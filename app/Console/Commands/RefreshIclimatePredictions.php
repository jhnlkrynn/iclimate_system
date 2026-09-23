<?php

namespace App\Console\Commands;

use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Operator-only (Artisan CLI / scheduler) tool for keeping the persisted
 * monthly weather predictions warm. Never exposed to farmers or MAO users —
 * there is no HTTP route for this; only someone with shell/scheduler access
 * to the server can run it.
 *
 *   php artisan iclimate:refresh-predictions
 *     Ensures the 12-month operational horizon (this month .. +11) has a
 *     fresh persisted forecast, queuing background generation for any
 *     month that's missing/stale. Non-blocking — safe to run often
 *     (e.g. from the scheduler); already-fresh months are skipped.
 *
 *   php artisan iclimate:refresh-predictions --year=2026 --month=11
 *     Force-regenerates exactly that one month, synchronously, printing
 *     the result immediately.
 *
 *   php artisan iclimate:refresh-predictions --all
 *     Force-regenerates every month in the 12-month horizon synchronously.
 */
class RefreshIclimatePredictions extends Command
{
    protected $signature = 'iclimate:refresh-predictions
        {--year= : Force-refresh a specific year (requires --month)}
        {--month= : Force-refresh a specific month (requires --year)}
        {--all : Force-refresh every month in the 12-month operational horizon}';

    protected $description = 'Pre-generate/refresh persisted monthly weather predictions for the operational horizon.';

    private const OPERATIONAL_HORIZON_MONTHS = 12;

    public function handle(PredictionOrchestrator $orchestrator): int
    {
        if ($this->option('year') || $this->option('month')) {
            return $this->refreshSingleMonth($orchestrator);
        }

        if ($this->option('all')) {
            return $this->refreshHorizon($orchestrator, forceRefresh: true);
        }

        return $this->refreshHorizon($orchestrator, forceRefresh: false);
    }

    private function refreshSingleMonth(PredictionOrchestrator $orchestrator): int
    {
        $year = $this->option('year');
        $month = $this->option('month');

        if (! $year || ! $month) {
            $this->error('--year and --month must be provided together.');

            return self::FAILURE;
        }

        $target = CarbonImmutable::createFromDate((int) $year, (int) $month, 1)->startOfMonth();

        $this->info("Refreshing monthly weather prediction for {$target->format('F Y')}...");

        $result = $orchestrator->monthlyWeatherForecast($target, forceRefresh: true);

        if ($result['ready'] ?? false) {
            $this->info("Done. Rainfall {$result['predictions']['rainfall']} mm, temperature {$result['predictions']['temperature']}°C.");
        } else {
            $this->warn('Model reported not ready: '.($result['message'] ?? 'unknown reason'));
        }

        return self::SUCCESS;
    }

    private function refreshHorizon(PredictionOrchestrator $orchestrator, bool $forceRefresh): int
    {
        $start = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $queued = 0;
        $skipped = 0;

        for ($i = 0; $i < self::OPERATIONAL_HORIZON_MONTHS; $i++) {
            $month = $start->addMonthsNoOverflow($i);

            if ($forceRefresh) {
                $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);
                $this->line("Refreshed {$month->format('F Y')}.");
                $queued++;

                continue;
            }

            if ($orchestrator->ensureMonthlyWeatherForecastQueued($month)) {
                $this->line("Queued generation for {$month->format('F Y')}.");
                $queued++;
            } else {
                $skipped++;
            }
        }

        $this->info(
            $forceRefresh
                ? "Force-refreshed {$queued} month(s) across the {$this->horizonLabel($start)} horizon."
                : "Queued {$queued} month(s), {$skipped} already fresh, across the {$this->horizonLabel($start)} horizon."
        );

        return self::SUCCESS;
    }

    private function horizonLabel(CarbonImmutable $start): string
    {
        $end = $start->addMonthsNoOverflow(self::OPERATIONAL_HORIZON_MONTHS - 1);

        return $start->format('M Y').' - '.$end->format('M Y');
    }
}
