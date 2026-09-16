<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Console\Command;

class ExportRiceYieldTrainingData extends Command
{
    protected $signature = 'iclimate:export-yield-data {--path=storage/app/private/ml/rice_yield_training_data.csv : Retained for command compatibility}';

    protected $description = 'Queue Python preparation and evaluation of the rice-yield training dataset.';

    public function handle(AnalyticsDispatcher $analytics): int
    {
        $run = $analytics->dispatch('dataset_processing', [
            'schema' => 'rice_yield_v1',
            'requested_output_path' => (string) $this->option('path'),
        ]);

        $this->info("Queued Python dataset processing: {$run->uuid}");

        return self::SUCCESS;
    }
}
