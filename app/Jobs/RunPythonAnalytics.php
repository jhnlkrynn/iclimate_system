<?php

namespace App\Jobs;

use App\Models\AnalyticsRun;
use App\Services\Analytics\AnalyticsPayloadFactory;
use App\Services\Analytics\AnalyticsResultPersister;
use App\Services\Analytics\PythonAnalyticsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunPythonAnalytics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public array $backoff = [5, 15, 30];

    public function __construct(public readonly int $analyticsRunId) {}

    public function handle(
        AnalyticsPayloadFactory $payloads,
        PythonAnalyticsClient $python,
        AnalyticsResultPersister $persister,
    ): void {
        $run = AnalyticsRun::query()->findOrFail($this->analyticsRunId);
        if ($run->status === AnalyticsRun::STATUS_COMPLETED) {
            return;
        }

        $run->update([
            'status' => AnalyticsRun::STATUS_PROCESSING,
            'started_at' => now(),
            'error' => null,
        ]);

        $result = $python->run($run->type, $payloads->make($run));

        DB::transaction(function () use ($run, $result, $persister): void {
            $storedResult = $persister->persist($run, $result);
            $run->update([
                'status' => AnalyticsRun::STATUS_COMPLETED,
                'result' => $storedResult,
                'completed_at' => now(),
                'error' => null,
            ]);

            if ($run->cache_key) {
                Cache::put($run->cache_key, $storedResult, now()->addMinutes(
                    (int) config('services.farming_ai.cache_minutes', 30)
                ));
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        AnalyticsRun::query()->whereKey($this->analyticsRunId)->update([
            'status' => AnalyticsRun::STATUS_FAILED,
            'error' => str($exception?->getMessage() ?: 'Python analytics job failed.')->limit(4000),
            'completed_at' => now(),
        ]);
    }
}
