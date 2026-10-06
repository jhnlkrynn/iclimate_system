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
use Illuminate\Support\Facades\Log;
use Throwable;

class RunPythonAnalytics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public array $backoff = [
        5,
        15,
        30,
    ];

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        public readonly int $analyticsRunId
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Process Analytics Run
    |--------------------------------------------------------------------------
    */

    public function handle(
        AnalyticsPayloadFactory $payloads,
        PythonAnalyticsClient $python,
        AnalyticsResultPersister $persister,
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Find Analytics Run
        |--------------------------------------------------------------------------
        */

        $run =
            AnalyticsRun::query()
                ->findOrFail(
                    $this->analyticsRunId
                );

        /*
        |--------------------------------------------------------------------------
        | Already Completed
        |--------------------------------------------------------------------------
        */

        if (
            $run->status
            ===
            AnalyticsRun::STATUS_COMPLETED
        ) {
            return;
        }

        // The page already told the user this request timed out and offered "try again"; a late worker
        // must not spend time on it.
        if ($run->timedOut()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Processing
        |--------------------------------------------------------------------------
        */

        $run->update([
            'status' =>
                AnalyticsRun::STATUS_PROCESSING,

            'started_at' =>
                now(),

            'error' =>
                null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Execute Existing Production Analytics
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | This is still your existing production Python workflow.
        |
        | Nothing about the production model has been replaced.
        |
        */

        $result =
            $python->run(
                $run->type,
                $payloads->make(
                    $run
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Persist Production Result
        |--------------------------------------------------------------------------
        */

        $storedResult = null;

        DB::transaction(
            function () use (
                $run,
                $result,
                $persister,
                &$storedResult
            ): void {
                /*
                |--------------------------------------------------------------------------
                | Save Existing Analytics Result
                |--------------------------------------------------------------------------
                */

                $storedResult =
                    $persister->persist(
                        $run,
                        $result
                    );

                /*
                |--------------------------------------------------------------------------
                | Complete Analytics Run
                |--------------------------------------------------------------------------
                */

                $run->update([
                    'status' =>
                        AnalyticsRun::STATUS_COMPLETED,

                    'result' =>
                        $storedResult,

                    'completed_at' =>
                        now(),

                    'error' =>
                        null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Cache Production Result
                |--------------------------------------------------------------------------
                */

                if (
                    $run->cache_key
                ) {
                    Cache::put(
                        $run->cache_key,
                        $storedResult,
                        now()->addMinutes(
                            (int)
                            config(
                                'services.farming_ai.cache_minutes',
                                30
                            )
                        )
                    );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Refresh Run After Transaction
        |--------------------------------------------------------------------------
        */

        $run->refresh();

        /*
        |--------------------------------------------------------------------------
        | Dispatch Shadow Comparison
        |--------------------------------------------------------------------------
        |
        | ONLY for successful rice-yield predictions.
        |
        | This happens AFTER:
        |
        | 1. Production Python model succeeds
        | 2. Production result is persisted
        | 3. AnalyticsRun is marked completed
        | 4. Production result is cached
        |
        | Therefore, the candidate model cannot prevent the user from receiving
        | a successful production prediction.
        |
        */

        if (
            $run->type
            ===
            'rice_yield_prediction'
        ) {
            // A rice_yield_prediction result is always shaped
            // {weather_result, ml_result: {predicted_yield, ...}} — see
            // AnalyticsResultPersister (passes it through unchanged) and
            // WeatherPredictionController::index(), which already reads
            // ml_result.predicted_yield for the exact same run type. A
            // top-level lookup here never matches, so the shadow job was
            // never actually being dispatched for any real rice-yield run.
            $productionPrediction =
                is_array(
                    $storedResult
                )
                    ? data_get(
                        $storedResult,
                        'ml_result.predicted_yield'
                    )
                    : null;

            /*
            |--------------------------------------------------------------------------
            | Only Dispatch When Production Yield Exists
            |--------------------------------------------------------------------------
            */

            if (
                is_numeric(
                    $productionPrediction
                )
            ) {
                try {
                    RunRiceYieldShadowComparison::dispatch(
                        $run->id
                    )
                        ->onConnection(
                            (string)
                            config(
                                'services.farming_ai.queue_connection',
                                'database'
                            )
                        )
                        ->onQueue(
                            (string)
                            config(
                                'services.farming_ai.queue',
                                'analytics'
                            )
                        )
                        ->afterCommit();

                    Log::info(
                        'Rice-yield shadow comparison queued.',
                        [
                            'analytics_run_id' =>
                                $run->id,

                            'production_prediction' =>
                                (float)
                                $productionPrediction,
                        ]
                    );
                } catch (Throwable $exception) {
                    /*
                    |--------------------------------------------------------------------------
                    | Shadow Dispatch Must Never Break Production
                    |--------------------------------------------------------------------------
                    */

                    report(
                        $exception
                    );

                    Log::warning(
                        'Production rice-yield prediction succeeded, but shadow comparison could not be queued.',
                        [
                            'analytics_run_id' =>
                                $run->id,

                            'error' =>
                                $exception->getMessage(),
                        ]
                    );
                }
            } else {
                Log::warning(
                    'Rice-yield shadow comparison was not queued because predicted_yield was missing from the production result.',
                    [
                        'analytics_run_id' =>
                            $run->id,
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Production Analytics Failure
    |--------------------------------------------------------------------------
    */

    public function failed(
        ?Throwable $exception
    ): void {
        AnalyticsRun::query()
            ->whereKey(
                $this->analyticsRunId
            )
            ->update([
                'status' =>
                    AnalyticsRun::STATUS_FAILED,

                'error' =>
                    str(
                        $exception?->getMessage()
                        ?:
                        'Python analytics job failed.'
                    )->limit(
                        4000
                    ),

                'completed_at' =>
                    now(),
            ]);
    }
}