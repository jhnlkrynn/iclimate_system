<?php

namespace App\Console\Commands;

use App\Services\Climate\OpenMeteoArchiveMonthly;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Stores complete past months from the Open-Meteo Historical Weather API (App\Services\Climate\OpenMeteoArchiveMonthly),
 * the fallback for observed-month yield-model inputs that neither the Ambulong station file nor a MAO Climate Record covers.
 */
class FetchClimateArchive extends Command
{
    protected $signature = 'iclimate:fetch-climate-archive
        {--from= : First month (Y-m); default 2015-01 on the first run (for the station calibration), then 18 months back}
        {--to= : Last month (Y-m); default the last month that is over}';

    protected $description = 'Fetch complete past months of Open-Meteo historical weather for the rice-yield model inputs.';

    public function handle(OpenMeteoArchiveMonthly $archive): int
    {
        $now = CarbonImmutable::now((string) config('services.open_meteo.timezone', 'Asia/Manila'));
        try {
            // Until the station years are stored (the calibration needs them), the first run fetches from 2015.
            $stored = array_keys(OpenMeteoArchiveMonthly::months());
            $default = $stored !== [] && $stored[0] <= OpenMeteoArchiveMonthly::CALIBRATION_FROM
                ? $now->startOfMonth()->subMonthsNoOverflow(18)
                : CarbonImmutable::createFromFormat('!Y-m', OpenMeteoArchiveMonthly::CALIBRATION_FROM);
            $from = $this->option('from') ? CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('from')) : $default;
            $to = $this->option('to') ? CarbonImmutable::createFromFormat('!Y-m', (string) $this->option('to')) : $now->startOfMonth()->subMonthNoOverflow();
        } catch (Throwable) {
            $this->error('Use Y-m for --from and --to, e.g. 2026-05.');

            return self::INVALID;
        }

        try {
            $months = $archive->fetch($from, $to);
        } catch (Throwable $e) {
            report($e);
            $this->warn('Open-Meteo archive not updated: '.$e->getMessage());

            return self::FAILURE;
        }

        $total = $archive->store($months);
        $this->info('Complete months fetched: '.count($months).($months ? ' ('.array_key_first($months).' .. '.array_key_last($months).')' : ''));
        $this->line('Months stored: '.$total.' in '.OpenMeteoArchiveMonthly::path());

        return self::SUCCESS;
    }
}
