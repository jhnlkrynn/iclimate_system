<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Console\Command;

class QueueDatasetProcessing extends Command
{
    protected $signature = 'iclimate:process-datasets';

    protected $description = 'Queue Python dataset preparation and model evaluation.';

    public function handle(AnalyticsDispatcher $analytics): int
    {
        $run = $analytics->dispatch('dataset_processing', ['schema' => 'rice_yield_v1']);
        $this->info("Queued Python dataset processing: {$run->uuid}");

        return self::SUCCESS;
    }
}
