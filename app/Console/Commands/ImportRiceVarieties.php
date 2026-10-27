<?php

namespace App\Console\Commands;

use App\Services\RiceVarieties\RiceVarietyImporter;
use App\Support\SchemaCache;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the NSIC-registered rice varieties ("Rice" sheet) into rice_varieties. Safe to run again: unchanged rows stay
 * as they are, and varieties IT Personnel added or corrected by hand are never overwritten.
 */
class ImportRiceVarieties extends Command
{
    protected $signature = 'rice-varieties:import {path? : The NSIC .xlsx file (default: config rice_varieties.import_path)}';

    protected $description = 'Import or refresh the NSIC-registered rice varieties from the NSIC spreadsheet';

    public function handle(RiceVarietyImporter $importer): int
    {
        if (! SchemaCache::hasTable('rice_varieties')) {
            $this->error('The rice_varieties table does not exist yet. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $path = (string) ($this->argument('path') ?: config('rice_varieties.import_path'));
        try {
            $summary = $importer->import($path);
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Read {$summary['rows']} varieties from ".basename($path).'.');
        $this->table(['Inserted', 'Updated', 'Unchanged', 'Skipped (manual)', 'No maturity data', 'No transplanted maturity'], [[
            $summary['inserted'], $summary['updated'], $summary['unchanged'], $summary['skipped'], $summary['no_maturity'], $summary['no_tpr_maturity'],
        ]]);
        $this->table(['Irrigated', 'Rainfed', 'Both', 'Not specified'], [[
            $summary['irrigated'], $summary['rainfed'], $summary['both'], $summary['not_specified'],
        ]]);
        $this->line('Varieties with no maturity data use the '.(int) config('farmer_dashboard.crop.default_duration_days', 115).'-day default for the expected harvest date.');

        // The PDF of the same list, for "View source list" (shown only, never imported).
        $this->line(\App\Support\RiceVarieties\SourceDocument::publish()
            ? 'Source PDF published at storage/app/public/'.\App\Support\RiceVarieties\SourceDocument::PUBLIC_PATH.'.'
            : 'No source PDF found at '.\App\Support\RiceVarieties\SourceDocument::source().'; the page shows no "View source list" link.');

        return self::SUCCESS;
    }
}
