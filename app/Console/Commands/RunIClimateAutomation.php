<?php

namespace App\Console\Commands;

use App\Models\AnalyticsRun;
use App\Models\SystemLog;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Support\QueueWorkerHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunIClimateAutomation extends Command
{
    protected $signature = 'iclimate:automation-run
        {--force-weather : Retained for scheduler compatibility}
        {--force-pagasa : Retained for scheduler compatibility}
        {--skip-pagasa : Skip advisory analytics for this run}';

    protected $description = 'Queue the Python analytics, risk, advisory, and dataset pipeline.';

    public function handle(AnalyticsDispatcher $analytics): int
    {
        // No worker: the runs would only wait, time out after a few minutes and be recorded as failed. Skip this round;
        // the next one (30 min) queues again once the worker is back. (41 such failed runs on 2026-11-10.)
        $connection = (string) config('services.farming_ai.queue_connection', 'database');
        if (! QueueWorkerHeartbeat::alive($connection)) {
            $this->recordAutomation([
                'ok' => false,
                'skipped' => true,
                'reason' => 'queue_worker_unavailable',
                'queue_connection' => $connection,
                'queue' => (string) config('services.farming_ai.queue', 'analytics'),
            ]);
            $this->warn('Skipped: no queue worker is running (start it with scripts/start-iclimate-local.sh). Nothing was queued.');

            return self::SUCCESS;
        }

        $wanted = ['dataset_processing' => ['schema' => 'rice_yield_v1']];
        if (! $this->option('skip-pagasa')) {
            $wanted['advisory_ai'] = [
                'source' => 'scheduled_automation',
                'force_weather' => (bool) $this->option('force-weather'),
                'force_pagasa' => (bool) $this->option('force-pagasa'),
            ];
        }

        $runs = [];
        $skipped = [];
        foreach ($wanted as $type => $input) {
            // The previous round of the same work is still waiting or running: one is enough (no duplicate runs).
            $inFlight = AnalyticsRun::query()->where('type', $type)->whereNull('requested_by')
                ->whereIn('status', [AnalyticsRun::STATUS_PENDING, AnalyticsRun::STATUS_PROCESSING])
                ->where('created_at', '>=', now()->subHour())
                ->exists();
            if ($inFlight) {
                $this->line("Skipped {$type}: the previous scheduled run is still in progress.");
                $skipped[] = ['type' => $type, 'reason' => 'previous_run_in_flight'];

                continue;
            }
            $runs[] = $analytics->dispatch($type, $input);
        }

        $this->recordAutomation([
            'ok' => true,
            'skipped' => $skipped,
            'queued' => collect($runs)->map(fn (AnalyticsRun $run): array => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'type' => $run->type,
            ])->values()->all(),
            'queue_connection' => $connection,
            'queue' => (string) config('services.farming_ai.queue', 'analytics'),
        ]);

        $this->info('Queued Python analytics runs: '.(collect($runs)->pluck('uuid')->implode(', ') ?: 'none'));

        return self::SUCCESS;
    }

    /** Record scheduler dispatch state without allowing audit bookkeeping to break the producer. */
    private function recordAutomation(array $details): void
    {
        if (! Schema::hasTable('system_logs')) {
            return;
        }

        try {
            SystemLog::query()->create([
                'user_id' => null,
                'action' => 'iclimate.automation',
                'details' => json_encode(['context' => $details], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable) {
            // Dispatch must remain successful if optional audit bookkeeping is unavailable.
        }
    }
}
