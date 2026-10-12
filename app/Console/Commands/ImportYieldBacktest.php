<?php

namespace App\Console\Commands;

use App\Models\MunicipalYieldPrediction;
use App\Support\CacheScope;
use App\Support\RiceSeason;
use Illuminate\Console\Command;

/**
 * Panel revision 4.2: loads the model's predicted vs. actual municipal yield (python_scripts/backtest_candidate_yield.py
 * → storage/datasets/generated/rice_yield_backtest.csv) into municipal_yield_predictions. Safe to run again: a month is
 * matched by year, month and model and updated in place.
 */
class ImportYieldBacktest extends Command
{
    protected $signature = 'iclimate:import-yield-backtest {--file= : CSV to read (default: the generated backtest file)}';

    protected $description = 'Load predicted vs. actual municipal rice yield (t/ha) for the MAO dashboard (panel revision 4.2).';

    public const DEFAULT_FILE = 'storage/datasets/generated/rice_yield_backtest.csv';

    public function handle(): int
    {
        $path = base_path((string) ($this->option('file') ?: self::DEFAULT_FILE));
        if (! is_file($path)) {
            $this->error('File not found: '.$path);
            $this->line('Create it with: python python_scripts/backtest_candidate_yield.py');

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtoupper(trim((string) $h)), (array) fgetcsv($handle));
        $count = 0;
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $line);
            if (! is_numeric($row['YEAR'] ?? null) || ! is_numeric($row['MONTH'] ?? null) || ! is_numeric($row['PREDICTED_YIELD'] ?? null)) {
                continue;
            }
            $month = (int) $row['MONTH'];
            MunicipalYieldPrediction::query()->updateOrCreate(
                ['year' => (int) $row['YEAR'], 'month' => $month, 'barangay' => null, 'model' => (string) ($row['MODEL'] ?? 'Rice yield model')],
                [
                    'season' => in_array($row['SEASON'] ?? '', [RiceSeason::WET, RiceSeason::DRY], true) ? $row['SEASON'] : RiceSeason::forMonth($month),
                    'predicted_yield' => round((float) $row['PREDICTED_YIELD'], 4),
                    'actual_yield' => is_numeric($row['ACTUAL_YIELD'] ?? null) ? round((float) $row['ACTUAL_YIELD'], 4) : null,
                    'split' => strtoupper((string) ($row['SPLIT'] ?? '')) ?: null,
                    'source' => 'Backtest on the iClimate modeling dataset (municipal monthly average)',
                ],
            );
            $count++;
        }
        fclose($handle);

        rescue(fn () => CacheScope::bumpAll(), null, false);
        $this->info("Loaded {$count} months of predicted vs. actual yield.");

        return self::SUCCESS;
    }
}
