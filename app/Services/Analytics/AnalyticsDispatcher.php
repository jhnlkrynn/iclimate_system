<?php

namespace App\Services\Analytics;

use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AnalyticsDispatcher
{
    public function dispatch(string $type, array $input, ?User $user = null): AnalyticsRun
    {
        $cacheKey = 'analytics:'.hash('sha256', $type.'|'.json_encode($input));
        // Reports must always reflect the database state at the moment the user
        // clicks Generate. Their completed snapshots are still cached for reads,
        // but a cache hit must never suppress creation of a new saved report.
        $cached = $type === 'report_analytics' ? null : Cache::get($cacheKey);

        $run = AnalyticsRun::query()->create([
            'type' => $type,
            'status' => is_array($cached) ? AnalyticsRun::STATUS_COMPLETED : AnalyticsRun::STATUS_PENDING,
            'requested_by' => $user?->id,
            'cache_key' => $cacheKey,
            'input' => $input,
            'result' => is_array($cached) ? $cached : null,
            'completed_at' => is_array($cached) ? now() : null,
        ]);

        if (! is_array($cached)) {
            RunPythonAnalytics::dispatch($run->id)
                ->onConnection((string) config('services.farming_ai.queue_connection', 'database'))
                ->onQueue((string) config('services.farming_ai.queue', 'analytics'))
                ->afterCommit();
        }

        return $run;
    }
}
