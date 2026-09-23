<?php

namespace App\Jobs;

use App\Models\AnalyticsRun;
use App\Models\RiceYieldModelComparison;
use App\Services\MachineLearning\RiceYieldShadowComparisonService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunRiceYieldShadowComparison implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    */

    public int $tries = 2;

    public int $timeout = 180;

    public bool $failOnTimeout = false;

    public array $backoff = [
        10,
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
    | Handle Shadow Comparison
    |--------------------------------------------------------------------------
    */

    public function handle(
        RiceYieldShadowComparisonService $shadowComparison
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Load Completed Production Analytics Run
        |--------------------------------------------------------------------------
        */

        $run =
            AnalyticsRun::query()
                ->find(
                    $this->analyticsRunId
                );

        if (
            ! $run
        ) {
            Log::warning(
                'Rice-yield shadow comparison skipped because analytics run was not found.',
                [
                    'analytics_run_id' =>
                        $this->analyticsRunId,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Only Rice-Yield Predictions
        |--------------------------------------------------------------------------
        */

        if (
            $run->type
            !==
            'rice_yield_prediction'
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Production Run Must Be Completed
        |--------------------------------------------------------------------------
        */

        if (
            $run->status
            !==
            AnalyticsRun::STATUS_COMPLETED
        ) {
            Log::info(
                'Rice-yield shadow comparison skipped because production run is not completed.',
                [
                    'analytics_run_id' =>
                        $run->id,

                    'status' =>
                        $run->status,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Read Production Result
        |--------------------------------------------------------------------------
        */

        $result =
            $run->result;

        if (
            ! is_array(
                $result
            )
        ) {
            Log::warning(
                'Rice-yield shadow comparison skipped because production result is not an array.',
                [
                    'analytics_run_id' =>
                        $run->id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Production Predicted Yield
        |--------------------------------------------------------------------------
        */

        // A rice_yield_prediction result is shaped
        // {weather_result, ml_result: {predicted_yield, ...}} — see
        // AnalyticsResultPersister (passes it through unchanged) and
        // RunPythonAnalytics's own shadow-dispatch check. A flat top-level
        // 'predicted_yield' is kept as a fallback for any other caller
        // shape, but the nested path is what a real production run
        // actually has.
        $productionPrediction =
            data_get(
                $result,
                'ml_result.predicted_yield',
                $result['predicted_yield'] ?? null
            );

        if (
            ! is_numeric(
                $productionPrediction
            )
        ) {
            Log::warning(
                'Rice-yield shadow comparison skipped because production predicted_yield is missing or invalid.',
                [
                    'analytics_run_id' =>
                        $run->id,

                    'result_keys' =>
                        array_keys(
                            $result
                        ),
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Read Original Input
        |--------------------------------------------------------------------------
        */

        $input =
            $run->input;

        if (
            ! is_array(
                $input
            )
        ) {
            Log::warning(
                'Rice-yield shadow comparison skipped because analytics input is unavailable.',
                [
                    'analytics_run_id' =>
                        $run->id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Target Date
        |--------------------------------------------------------------------------
        */

        $targetDateValue =
            $input[
                'target_date'
            ]
            ?? null;

        if (
            ! is_string(
                $targetDateValue
            )
            ||
            trim(
                $targetDateValue
            )
            === ''
        ) {
            Log::warning(
                'Rice-yield shadow comparison skipped because target_date is unavailable.',
                [
                    'analytics_run_id' =>
                        $run->id,
                ]
            );

            return;
        }

        try {
            $targetDate =
                CarbonImmutable::parse(
                    $targetDateValue
                );
        } catch (Throwable $exception) {
            Log::warning(
                'Rice-yield shadow comparison skipped because target_date could not be parsed.',
                [
                    'analytics_run_id' =>
                        $run->id,

                    'target_date' =>
                        $targetDateValue,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Shadow Comparison
        |--------------------------------------------------------------------------
        |
        | Queue retries or duplicate dispatches must not create another
        | comparison row for the same AnalyticsRun.
        |
        */

        $existingComparisons =
            RiceYieldModelComparison::query()
                ->where(
                    'source',
                    'Shadow Comparison'
                )
                ->where(
                    'prediction_year',
                    $targetDate->year
                )
                ->where(
                    'prediction_month',
                    $targetDate->month
                )
                ->where(
                    'user_id',
                    $run->requested_by
                )
                ->get();

        $alreadyRecorded =
            $existingComparisons
                ->contains(
                    function (
                        RiceYieldModelComparison $comparison
                    ) use (
                        $run
                    ): bool {
                        $metadata =
                            $comparison->metadata;

                        if (
                            ! is_array(
                                $metadata
                            )
                        ) {
                            return false;
                        }

                        return
                            (int)
                            (
                                $metadata[
                                    'analytics_run_id'
                                ]
                                ?? 0
                            )
                            ===
                            (int)
                            $run->id;
                    }
                );

        if (
            $alreadyRecorded
        ) {
            Log::info(
                'Rice-yield shadow comparison already exists for this analytics run.',
                [
                    'analytics_run_id' =>
                        $run->id,
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Production Metadata
        |--------------------------------------------------------------------------
        */

        $farmArea =
            isset(
                $input[
                    'area'
                ]
            )
            &&
            is_numeric(
                $input[
                    'area'
                ]
            )
                ? (float)
                    $input[
                        'area'
                    ]
                : null;

        $productionSource =
            data_get($result, 'ml_result.source')
            ?? data_get($result, 'ml_result.source_name')
            ?? $result['source']
            ?? $result['source_name']
            ?? 'Current Production Model';

        $productionUnit =
            data_get($result, 'ml_result.unit')
            ?? $result['unit']
            ?? 'tons/hectare';

        /*
        |--------------------------------------------------------------------------
        | Run Shadow Candidate Comparison
        |--------------------------------------------------------------------------
        |
        | This does NOT replace the production prediction.
        |
        | Candidate pipeline:
        |
        | Ambulong Monthly Weather Random Forest
        |                 ↓
        | Candidate Rice Yield Random Forest
        |
        */

        try {
            $comparison =
                $shadowComparison->record(
                    year:
                        $targetDate->year,

                    month:
                        $targetDate->month,

                    productionPrediction:
                        (float)
                        $productionPrediction,

                    userId:
                        $run->requested_by
                            ? (int)
                                $run->requested_by
                            : null,

                    metadata: [
                        /*
                        |--------------------------------------------------------------------------
                        | Analytics Run
                        |--------------------------------------------------------------------------
                        */

                        'analytics_run_id' =>
                            $run->id,

                        'analytics_run_uuid' =>
                            $run->uuid
                            ?? null,

                        /*
                        |--------------------------------------------------------------------------
                        | Request
                        |--------------------------------------------------------------------------
                        */

                        'target_date' =>
                            $targetDate->toDateString(),

                        'prediction_year' =>
                            $targetDate->year,

                        'prediction_month' =>
                            $targetDate->month,

                        'farm_type' =>
                            $input[
                                'farm_type'
                            ]
                            ?? null,

                        'farm_area_hectares' =>
                            $farmArea,

                        /*
                        |--------------------------------------------------------------------------
                        | Production Model
                        |--------------------------------------------------------------------------
                        */

                        'production_result_source' =>
                            $productionSource,

                        'production_prediction' =>
                            (float)
                            $productionPrediction,

                        'production_prediction_unit' =>
                            $productionUnit,

                        'production_run_completed_at' =>
                            $run->completed_at
                                ? $run
                                    ->completed_at
                                    ->toISOString()
                                : null,

                        /*
                        |--------------------------------------------------------------------------
                        | Shadow Information
                        |--------------------------------------------------------------------------
                        */

                        'shadow_mode' =>
                            true,

                        'candidate_display_status' =>
                            'Testing Only',
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Log Successful Comparison
            |--------------------------------------------------------------------------
            */

            Log::info(
                'Rice-yield shadow comparison completed.',
                [
                    'analytics_run_id' =>
                        $run->id,

                    'comparison_id' =>
                        $comparison->id,

                    'prediction_year' =>
                        $targetDate->year,

                    'prediction_month' =>
                        $targetDate->month,

                    'production_prediction' =>
                        (float)
                        $productionPrediction,

                    'candidate_prediction' =>
                        $comparison
                            ->candidate_prediction,

                    'difference' =>
                        $comparison
                            ->difference,

                    'absolute_difference' =>
                        $comparison
                            ->absolute_difference,

                    'candidate_success' =>
                        $comparison
                            ->candidate_success,

                    'candidate_error' =>
                        $comparison
                            ->candidate_error,
                ]
            );
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Shadow failure must NEVER modify or fail the already completed
            | production AnalyticsRun.
            |
            */

            report(
                $exception
            );

            Log::error(
                'Rice-yield shadow comparison failed after production prediction completed.',
                [
                    'analytics_run_id' =>
                        $run->id,

                    'production_prediction' =>
                        (float)
                        $productionPrediction,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Do Not Re-Throw
            |--------------------------------------------------------------------------
            |
            | The production prediction has already completed successfully.
            | The shadow candidate is testing-only.
            |
            */

            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Queue Job Failure
    |--------------------------------------------------------------------------
    |
    | This callback only logs the failure.
    |
    | It must never modify the production AnalyticsRun because production
    | already completed before the shadow job was dispatched.
    |
    */

    public function failed(
        ?Throwable $exception
    ): void {
        Log::error(
            'Rice-yield shadow comparison background job failed.',
            [
                'analytics_run_id' =>
                    $this->analyticsRunId,

                'error' =>
                    $exception?->getMessage()
                    ??
                    'Unknown shadow comparison error.',
            ]
        );
    }
}