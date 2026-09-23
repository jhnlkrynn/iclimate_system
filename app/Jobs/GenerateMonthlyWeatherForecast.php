<?php

namespace App\Jobs;

use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background generation of one month's MonthlyWeatherRandomForest
 * prediction, persisted via PredictionOrchestrator. Queued by
 * PredictionOrchestrator::ensureMonthlyWeatherForecastQueued() and by the
 * `iclimate:refresh-predictions` command's 12-month pre-generation pass —
 * never dispatched from a normal farmer-facing request path, and never
 * blocks a page render.
 */
class GenerateMonthlyWeatherForecast implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public array $backoff = [10, 30];

    public function __construct(public readonly string $targetMonth)
    {
    }

    public function handle(PredictionOrchestrator $orchestrator): void
    {
        $month = CarbonImmutable::parse($this->targetMonth)->startOfMonth();

        $orchestrator->generateAndPersist($month);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Monthly weather forecast background generation failed.', [
            'target_month' => $this->targetMonth,
            'error' => $exception?->getMessage() ?? 'Unknown error.',
        ]);
    }
}
