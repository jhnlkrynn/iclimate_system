<?php

namespace App\Console\Commands;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class ExportRiceYieldTrainingData extends Command
{
    protected $signature = 'iclimate:export-yield-data {--path=storage/app/private/ml/rice_yield_training_data.csv}';

    protected $description = 'Export rice_productions joined with matching climate_records (by year + season) to a CSV for model training/evaluation scripts.';

    public function handle(): int
    {
        $climate = ClimateRecord::query()
            ->whereNotNull('rainfall')
            ->whereNotNull('temperature')
            ->whereNotNull('humidity')
            ->whereNotNull('wind_speed')
            ->get()
            ->groupBy(fn (ClimateRecord $record): string => $record->record_date->format('Y').'|'.$record->season)
            ->map(fn (Collection $records): array => [
                'rainfall' => round((float) $records->avg('rainfall'), 2),
                'temperature' => round((float) $records->avg('temperature'), 2),
                'humidity' => round((float) $records->avg('humidity'), 2),
                'wind_speed' => round((float) $records->avg('wind_speed'), 2),
            ]);

        $rows = RiceProduction::query()
            ->whereNotNull('yield_per_hectare')
            ->whereNotNull('area_hectares')
            ->orderBy('year')
            ->orderBy('season')
            ->get()
            ->map(function (RiceProduction $production) use ($climate): ?array {
                $season = str($production->season)->contains('dry', true) ? ClimateRecord::SEASON_DRY : ClimateRecord::SEASON_WET;
                $climateRow = $climate->get($production->year.'|'.$season);

                if (! $climateRow) {
                    return null;
                }

                return [
                    'barangay' => $production->barangay,
                    'year' => $production->year,
                    'season' => $season,
                    'irrigation_type' => str($production->irrigation_type)->contains('irrigated', true) ? 'Irrigated' : 'Rainfed',
                    'rainfall' => $climateRow['rainfall'],
                    'temperature' => $climateRow['temperature'],
                    'humidity' => $climateRow['humidity'],
                    'wind_speed' => $climateRow['wind_speed'],
                    'area_hectares' => (float) $production->area_hectares,
                    'yield_per_hectare' => (float) $production->yield_per_hectare,
                ];
            })
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            $this->error('No rice_production rows have a matching climate_records year+season with rainfall/temperature/humidity/wind_speed all present. Nothing exported.');

            return self::FAILURE;
        }

        $path = base_path($this->option('path'));
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows->first()));

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->info("Exported {$rows->count()} rows to {$path}");

        if ($rows->count() < 30) {
            $this->warn('Sample size is small. Treat any accuracy comparison from this export as a pipeline smoke test, not a statistically reliable result, until more historical MAO/PAGASA records are imported.');
        }

        return self::SUCCESS;
    }
}
