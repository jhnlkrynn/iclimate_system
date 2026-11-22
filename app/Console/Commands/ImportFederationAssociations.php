<?php

namespace App\Console\Commands;

use App\Services\Farms\FederationAssociationImporter;
use App\Support\SchemaCache;
use Illuminate\Console\Command;
use Throwable;

class ImportFederationAssociations extends Command
{
    protected $signature = 'rice-associations:import-federation {path? : Path to the federation .xlsx file (default: database/data/lian-farmer-associations.xlsx)}';

    protected $description = 'Import Lian Federation of Farmers Associations areas and member counts (excluding personal contact fields)';

    public function handle(FederationAssociationImporter $importer): int
    {
        if (! SchemaCache::hasTable('farmer_organizations')) {
            $this->error('The farmer_organizations table does not exist yet. Run php artisan migrate first.');

            return self::FAILURE;
        }

        try {
            $summary = $importer->import($this->argument('path'));
        } catch (Throwable $e) {
            \App\Support\SilentFailure::log($e, 'ImportFederationAssociations::handle');
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Processed {$summary['rows']} associations.");
        $this->table(
            ['Created', 'Updated', 'Unchanged', 'Irrigated (ha)', 'Rainfed (ha)', 'Total (ha)'],
            [[
                $summary['created'],
                $summary['updated'],
                $summary['unchanged'],
                number_format($summary['irrigated_ha'], 2),
                number_format($summary['rainfed_ha'], 2),
                number_format($summary['total_ha'], 2),
            ]],
        );

        return self::SUCCESS;
    }
}
