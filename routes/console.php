<?php

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

            \App\Models\ClimateRecord::query()->updateOrCreate(
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
                        ? \App\Models\ClimateRecord::SEASON_WET
                        : \App\Models\ClimateRecord::SEASON_DRY,
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

Schedule::command('iclimate:fetch-weather')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('iclimate:generate-advisories')
    ->hourlyAt(5)
    ->withoutOverlapping();

Schedule::command('iclimate:expire-advisories')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
