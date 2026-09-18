<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsDispatcher;
use Illuminate\Console\Command;

class RunIClimateAutomation extends Command
{
    protected $signature = 'iclimate:automation-run
        {--force-weather : Retained for scheduler compatibility}
        {--force-pagasa : Retained for scheduler compatibility}
        {--skip-pagasa : Skip advisory analytics for this run}';

    protected $description = 'Queue the Python analytics, risk, advisory, and dataset pipeline.';

    public function handle(AnalyticsDispatcher $analytics): int
    {
        $runs = [
            $analytics->dispatch('heatmap_risk', ['source' => 'scheduled_automation']),
            $analytics->dispatch('dataset_processing', ['schema' => 'rice_yield_v1']),
        ];

        if (! $this->option('skip-pagasa')) {
            $runs[] = $analytics->dispatch('advisory_ai', [
                'source' => 'scheduled_automation',
                'force_weather' => (bool) $this->option('force-weather'),
                'force_pagasa' => (bool) $this->option('force-pagasa'),
            ]);
        }

        $this->info('Queued Python analytics runs: '.collect($runs)->pluck('uuid')->implode(', '));

        return self::SUCCESS;
    }
}
