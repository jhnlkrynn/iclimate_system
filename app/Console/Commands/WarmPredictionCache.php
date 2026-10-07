<?php

namespace App\Console\Commands;

use App\Models\FarmerProfile;
use App\Services\Prediction\PredictionEngine;
use App\Services\Prediction\MunicipalYieldForecastService;
use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Makes sure everything the chatbot reads for yield and planting questions is
 * already prepared, so no chat request ever has to train a model.
 *
 * It runs after `iclimate:refresh-predictions` (02:00), which only queues the
 * monthly forecasts. This command then:
 *   1. waits a little for those queued jobs to finish,
 *   2. builds any month still missing or stale itself (here, in the CLI,
 *      where taking a minute is fine),
 *   3. runs the shared yield engine once per farm type for the coming months
 *      so the prediction service and its data are warm,
 *   4. records a "ready" marker with the outcome.
 *
 * It uses the same forecasts and the same engine as everything else; it
 * changes no formula and no result.
 */
class WarmPredictionCache extends Command
{
    public const READY_KEY = 'predictions:cache_state';

    protected $signature = 'iclimate:warm-prediction-cache
        {--wait=300 : Seconds to wait for queued forecast jobs before building a missing month here}
        {--months=12 : How many months ahead to prepare}';

    protected $description = 'Prepare the monthly forecasts and yield results the chatbot reads, so chat requests never train a model.';

    /** Yield results are warmed for this many months starting now. */
    private const YIELD_WARM_MONTHS = 3;

    public function handle(PredictionOrchestrator $orchestrator, PredictionEngine $engine, MunicipalYieldForecastService $municipalYield): int
    {
        $started = microtime(true);
        $start = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $months = max(1, (int) $this->option('months'));
        $deadline = microtime(true) + max(0, (int) $this->option('wait'));

        $ready = 0;
        $builtHere = 0;
        $missing = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonthsNoOverflow($i);

            if ($orchestrator->hasPreparedMonthlyWeatherForecast($month)) {
                $ready++;

                continue;
            }

            // A queued job is already building it: give it time rather than train the same forest twice.
            while ($orchestrator->isGenerationQueued($month) && microtime(true) < $deadline) {
                sleep(5);

                if ($orchestrator->hasPreparedMonthlyWeatherForecast($month)) {
                    break;
                }
            }

            if ($orchestrator->hasPreparedMonthlyWeatherForecast($month)) {
                $ready++;

                continue;
            }

            try {
                $result = $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);
                $builtHere++;

                if ($result['ready'] ?? false) {
                    $ready++;
                    $this->line("Prepared {$month->format('F Y')}.");
                } else {
                    $missing[] = $month->format('Y-m');
                }
            } catch (Throwable) {
                $missing[] = $month->format('Y-m');
                $this->warn("Could not prepare {$month->format('F Y')}.");
            }
        }

        $yieldWarm = $this->warmYield($engine, $start);
        $municipalStatus = $municipalYield->status($start);
        $municipal = $municipalStatus['status'] === 'ready' && ! ($municipalStatus['stale'] ?? false)
            ? ['state' => 'ok', 'reused' => true]
            : ($municipalStatus['status'] === 'unavailable' ? ['state' => 'error'] : $municipalYield->prepare($start));
        $this->line(($municipal['state'] ?? 'error') === 'ok'
            ? (($municipal['reused'] ?? false) ? 'Reused the current All of Lian municipality yield forecast.' : 'Prepared the All of Lian municipality yield forecast.')
            : 'Municipality yield forecast was not produced; the page will say unavailable.');

        Cache::forever(self::READY_KEY, [
            'ready' => $missing === [],
            'ready_at' => now()->toIso8601String(),
            'months_ready' => $ready,
            'months_total' => $months,
            'months_missing' => $missing,
            'yield_results' => $yieldWarm,
        ]);

        $this->info("Prediction cache: {$ready}/{$months} months ready ({$builtHere} built here), {$yieldWarm} yield result(s) warmed in ".round(microtime(true) - $started).' s.');

        if ($missing !== []) {
            $this->warn('Not ready: '.implode(', ', $missing));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * One pass of the shared yield engine per farm type and month, from the
     * prepared forecasts. Failures are ignored: warming must never fail the run.
     * Only a pass that produced a yield is counted; one the engine declined
     * (e.g. no recorded farm area to run on) is reported, not counted.
     */
    private function warmYield(PredictionEngine $engine, CarbonImmutable $start): int
    {
        $warmed = 0;
        $reasons = [];

        for ($i = 0; $i < self::YIELD_WARM_MONTHS; $i++) {
            foreach ([FarmerProfile::FARM_TYPE_RAINFED, FarmerProfile::FARM_TYPE_IRRIGATED] as $farmType) {
                try {
                    $result = $engine->predict($start->addMonthsNoOverflow($i), $farmType);
                } catch (Throwable) {
                    // The month's forecast may be genuinely unavailable; that is reported above.
                    continue;
                }

                if (is_numeric($result['yield']['predicted_yield'] ?? null)) {
                    $warmed++;
                } elseif (filled($result['api_error'] ?? null)) {
                    $reasons[] = (string) $result['api_error'];
                }
            }
        }

        if ($reasons !== []) {
            $this->warn('Yield results not produced: '.implode(' | ', array_unique($reasons)));
        }

        return $warmed;
    }
}
