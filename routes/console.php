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
                    'season' => in_array($month, [5, 6, 7, 8, 9, 10], true)
                        ? ClimateRecord::SEASON_WET
                        : ClimateRecord::SEASON_DRY,
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

$automationLog = storage_path('logs/automation.log');

Schedule::command('iclimate:automation-run --force-weather')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

// Shared current weather for all 19 barangays (Heat Map, Advisories, Climora): one batched Open-Meteo request
// every 10 minutes, so pages read a reading that is at most ~10 minutes old. Offset from the quarter hour
// (see the PAGASA note below). After a deployment run `php artisan iclimate:refresh-barangay-weather --force`.
Schedule::command('iclimate:refresh-barangay-weather')
    ->cron('1,11,21,31,41,51 * * * *')
    ->withoutOverlapping(5)
    ->appendOutputTo($automationLog);

// Keeps the MAO / IT dashboard payloads cached (they cost ~40 Supabase round
// trips to build). Replaced in place every minute, so page loads stay instant.
Schedule::command('iclimate:warm-dashboards')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo($automationLog);

// Prepare the pages that are the same for everyone (MAO / IT dashboards, System Health) so opening one is
// instant instead of waiting for dozens of database round trips after every data change. System Health is
// only prepared by this command.
Schedule::command('iclimate:warm-dashboard-cache')
    ->everyTwoMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo($automationLog);

// Keep the stored weather fresh so pages and the chatbot read it instantly instead of waiting on a provider.
Schedule::command('iclimate:refresh-weather-cache')
    ->everyThreeMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo($automationLog);

// PAGASA online advisories are only fetched by this command; without a
// schedule the stored ones expire (6h) and the Advisories page goes empty.
// Runs at :07/:22/:37/:52 rather than on the quarter hour: every run that failed with
// "Could not resolve host" started at :00/:15/:30/:45, when the weather, risk-map and
// warm-up jobs all start their own PHP processes at the same moment.
Schedule::command('iclimate:fetch-pagasa-advisories --force')
    ->cron('7,22,37,52 * * * *')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

Schedule::command('iclimate:refresh-lian-boundaries')
    ->daily()
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

// Re-rates the 19 barangays with the existing risk engine from the shared weather above (no extra provider
// request when that weather is current). The ratings carry their own calculation time.
Schedule::command('iclimate:refresh-barangay-risk')
    ->cron('5,35 * * * *')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

Schedule::command('iclimate:process-datasets')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

// Keeps the 12-month operational horizon of persisted monthly weather
// predictions warm (queues background generation only for months that are
// missing/stale) so the weather-predictions page and barangay risk map
// almost never have to fall back to computing a forecast inline.
Schedule::command('iclimate:refresh-predictions')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

// Right after the refresh has queued the monthly forecasts: wait for them, build anything still
// missing here in the CLI, warm the yield engine and mark the cache ready. Chat requests only
// ever READ what this prepared (see PredictionReadMode), so none of them trains a model.
Schedule::command('iclimate:warm-prediction-cache')
    ->dailyAt('02:15')
    ->withoutOverlapping(60)
    ->appendOutputTo($automationLog);

// Daily PostgreSQL backup during low activity (after the 01:30 dataset and
// 02:00 prediction jobs), followed by cleanup of backups past the retention window.
Schedule::command('iclimate:backup-database')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);

// Ten minutes after the backup: prove the newest archive is readable
// (pg_restore --list only; nothing is restored).
Schedule::command('iclimate:verify-backup')
    ->dailyAt('03:25')
    ->withoutOverlapping()
    ->appendOutputTo($automationLog);
