<?php

namespace App\Console\Commands;

use App\Services\Weather\WeatherFeedbackSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Exports quality-controlled farmer weather observations to CSV for FUTURE,
 * MANUAL evaluation only. Read-only: writes a file, never touches the
 * database, models or ClimateRecord, and never triggers retraining.
 */
class ExportWeatherFeedback extends Command
{
    protected $signature = 'weather:export-feedback {path? : Output CSV path (default storage/app/weather-feedback-export.csv)} {--barangay=} {--from=} {--to=}';

    protected $description = 'Export validated farmer weather feedback to CSV (evaluation only; no retraining).';

    public function handle(WeatherFeedbackSummaryService $summary): int
    {
        $rows = $summary->validatedObservations([
            'barangay' => $this->option('barangay'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
        ]);

        $path = $this->argument('path') ?: storage_path('app/weather-feedback-export.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');
        fputcsv($handle, ['barangay', 'forecast_type', 'forecast_date', 'forecast_condition', 'forecast_rainfall_mm', 'forecast_temperature_c', 'forecast_humidity_percent', 'accuracy', 'observed_condition']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['barangay'], $row['forecast_type'], $row['forecast_date'],
                $row['forecast']['condition'], $row['forecast']['rainfall_mm'],
                $row['forecast']['temperature_c'], $row['forecast']['humidity_percent'],
                $row['accuracy'], $row['observed_condition'],
            ]);
        }
        fclose($handle);

        $this->info("Exported {$rows->count()} validated observations to {$path}");

        return self::SUCCESS;
    }
}
