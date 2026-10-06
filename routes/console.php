<?php

use App\Models\ClimateRecord;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Artisan::command('climate:import-ambulong {path : Full path to Ambulong Monthly Data CSV}', function (string $path): int {
    if (! is_file($path)) {
        $this->error("CSV file not found: {$path}");

        return self::FAILURE;
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        $this->error("Unable to open CSV file: {$path}");

        return self::FAILURE;
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        $this->error('CSV file is empty.');

        return self::FAILURE;
    }

    $header = array_map(fn ($value) => strtoupper(trim((string) $value)), $header);
    $required = ['YEAR', 'MONTH', 'RAINFALL', 'TMAX', 'TMIN', 'RH', 'WIND_SPEED'];
    $missing = array_values(array_diff($required, $header));

    if ($missing !== []) {
        fclose($handle);
        $this->error('Missing required CSV columns: '.implode(', ', $missing));

        return self::FAILURE;
    }

    $indexes = array_flip($header);
    $imported = 0;
    $skipped = 0;

    while (($row = fgetcsv($handle)) !== false) {
        try {
            $year = (int) trim((string) ($row[$indexes['YEAR']] ?? ''));
            $month = (int) trim((string) ($row[$indexes['MONTH']] ?? ''));

            if ($year < 1900 || $month < 1 || $month > 12) {
                $skipped++;

                continue;
            }

            $rainfall = (float) ($row[$indexes['RAINFALL']] ?? 0);
            $tmax = (float) ($row[$indexes['TMAX']] ?? 0);
            $tmin = (float) ($row[$indexes['TMIN']] ?? 0);
            $humidity = (float) ($row[$indexes['RH']] ?? 0);
            $windSpeed = (float) ($row[$indexes['WIND_SPEED']] ?? 0);
            $recordDate = sprintf('%04d-%02d-01', $year, $month);

            ClimateRecord::query()->updateOrCreate(
                [
                    'record_date' => $recordDate,
                    'source' => 'PAGASA Lian Monthly Data',
                ],
                [
                    'rainfall' => $rainfall,
                    'temperature' => round(($tmax + $tmin) / 2, 2),
                    'humidity' => $humidity,
                    'wind_speed' => $windSpeed,
                    'season' => ClimateRecord::seasonForMonth($month),
                ],
            );

            $imported++;
        } catch (Throwable $exception) {
            $skipped++;
            $this->warn('Skipped row: '.$exception->getMessage());
        }
    }

    fclose($handle);

    $this->info("Imported or updated {$imported} Ambulong monthly climate records.");
    if ($skipped > 0) {
        $this->warn("Skipped {$skipped} invalid rows.");
    }

    return self::SUCCESS;
})->purpose('Import Ambulong monthly climate CSV data into climate_records');

// One output file per scheduled command (storage/logs/automation-<command>.log). They used to share a single
// automation.log: on Windows a file that one command is appending to cannot be opened by another, so whenever
// two commands overlapped (the every-minute dashboard warm-up runs in the background) the second one failed
// with exit code 1 before it even started.
$automationLog = fn (string $command): string => storage_path('logs/automation-'.\Illuminate\Support\Str::after(\Illuminate\Support\Str::before($command, ' '), ':').'.log');

Schedule::command('iclimate:automation-run --force-weather')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:automation-run --force-weather'));

// Shared current weather for all 19 barangays (Heat Map, Advisories, Climora): one batched Open-Meteo request
// every 10 minutes, then the 19 risk ratings are made from that same weather (skipped when already rated), so the
// risk follows the weather without a queue worker. Offset from the quarter hour (see the PAGASA note below).
// After a deployment run `php artisan iclimate:refresh-barangay-weather --force` to warm weather and risk.
Schedule::command('iclimate:refresh-barangay-weather')
    ->cron('1,11,21,31,41,51 * * * *')
    ->withoutOverlapping(5)
    ->appendOutputTo($automationLog('iclimate:refresh-barangay-weather'));

// Keeps the MAO / IT dashboard payloads cached (they cost ~40 Supabase round
// trips to build). Replaced in place every minute, so page loads stay instant.
Schedule::command('iclimate:warm-dashboards')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo($automationLog('iclimate:warm-dashboards'));

// Prepare the pages that are the same for everyone (MAO / IT dashboards, System Health) so opening one is
// instant instead of waiting for dozens of database round trips after every data change. System Health is
// only prepared by this command.
Schedule::command('iclimate:warm-dashboard-cache')
    ->everyTwoMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo($automationLog('iclimate:warm-dashboard-cache'));

// Keep the stored weather fresh so pages and the chatbot read it instantly instead of waiting on a provider.
Schedule::command('iclimate:refresh-weather-cache')
    ->everyThreeMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo($automationLog('iclimate:refresh-weather-cache'));

// PAGASA online advisories are only fetched by this command; without a
// schedule the stored ones expire (6h) and the Advisories page goes empty.
// Runs at :07/:22/:37/:52 rather than on the quarter hour: every run that failed with
// "Could not resolve host" started at :00/:15/:30/:45, when the weather, risk-map and
// warm-up jobs all start their own PHP processes at the same moment.
Schedule::command('iclimate:fetch-pagasa-advisories --force')
    ->cron('7,22,37,52 * * * *')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:fetch-pagasa-advisories --force'));

Schedule::command('iclimate:refresh-lian-boundaries')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:refresh-lian-boundaries'));

// Reconciliation: re-rates the 19 barangays with the existing risk engine in case a rating after a weather
// refresh was missed (no extra provider request when that weather is current). Ratings carry their own time.
Schedule::command('iclimate:refresh-barangay-risk')
    ->cron('5,35 * * * *')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:refresh-barangay-risk'));

// Closes prediction/report requests nothing finished in time and drops repeated refresh jobs, so the queue
// stays short even after the worker was stopped for a while.
Schedule::command('iclimate:clean-stale-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo($automationLog('iclimate:clean-stale-queue'));

Schedule::command('iclimate:process-datasets')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:process-datasets'));

// Keeps the 12-month operational horizon of persisted monthly weather
// predictions warm (queues background generation only for months that are
// missing/stale) so the weather-predictions page and barangay risk map
// almost never have to fall back to computing a forecast inline.
Schedule::command('iclimate:refresh-predictions')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:refresh-predictions'));

// Right after the refresh has queued the monthly forecasts: wait for them, build anything still
// missing here in the CLI, warm the yield engine and mark the cache ready. Chat requests only
// ever READ what this prepared (see PredictionReadMode), so none of them trains a model.
Schedule::command('iclimate:warm-prediction-cache')
    ->dailyAt('02:15')
    ->withoutOverlapping(60)
    ->appendOutputTo($automationLog('iclimate:warm-prediction-cache'));

// Times of day in this file are Philippine time (config app.schedule_timezone), not UTC.
// Daily database backup (mysqldump for MySQL/MariaDB, pg_dump for PostgreSQL) during low activity (after the 01:30 dataset and
// 02:00 prediction jobs), followed by cleanup of backups past the retention window.
Schedule::command('iclimate:backup-database')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:backup-database'));

// Ten minutes after the backup: prove the newest archive is readable
// (read or listed only; nothing is restored).
Schedule::command('iclimate:verify-backup')
    ->dailyAt('03:25')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog('iclimate:verify-backup'));
