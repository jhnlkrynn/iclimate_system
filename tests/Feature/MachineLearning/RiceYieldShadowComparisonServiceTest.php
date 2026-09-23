<?php

namespace Tests\Feature\MachineLearning;

use App\Jobs\RunRiceYieldShadowComparison;
use App\Models\AnalyticsRun;
use App\Models\RiceYieldModelComparison;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\MachineLearning\RiceYieldShadowComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for the shadow candidate comparison: it must read
 * weather through the same centralized PredictionOrchestrator the rest of
 * the system uses, must never fail/block the already-completed production
 * prediction, and must never write a second comparison row for the same
 * analytics run.
 */
class RiceYieldShadowComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_failure_is_recorded_without_throwing_when_weather_is_not_ready(): void
    {
        Cache::flush();

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn([
                'ready' => false,
                'message' => 'Not enough monthly climate history is available yet.',
                'source_name' => 'iClimate Ambulong Monthly Random Forest v5',
                'predictions' => [],
                'training_period' => null,
            ]);
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        $comparison = app(RiceYieldShadowComparisonService::class)->record(
            year: 2026,
            month: 11,
            productionPrediction: 4.2,
            userId: null,
            metadata: ['analytics_run_id' => 123],
        );

        $this->assertInstanceOf(RiceYieldModelComparison::class, $comparison);
        $this->assertFalse($comparison->candidate_success);
        $this->assertNull($comparison->candidate_prediction);
        $this->assertNotNull($comparison->candidate_error);
        $this->assertSame(4.2, (float) $comparison->production_prediction);
    }

    public function test_shadow_job_never_creates_a_second_comparison_for_the_same_analytics_run(): void
    {
        Cache::flush();

        $run = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => null,
            'cache_key' => 'test:dedupe:'.uniqid(),
            'input' => ['target_date' => '2026-09-15', 'area' => 2.5, 'farm_type' => 'Rainfed'],
            'result' => ['predicted_yield' => 4.1, 'source' => 'Test Production Model'],
            'completed_at' => now(),
        ]);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn([
                'ready' => true,
                'message' => 'ok',
                'source_name' => 'iClimate Ambulong Monthly Random Forest v5',
                'training_period' => ['from' => '2015-01', 'to' => '2024-12'],
                'months_available' => 120,
                'predictions' => [
                    'rainfall' => 150.0, 'tmax' => 31.0, 'tmin' => 24.0,
                    'temperature' => 27.5, 'humidity' => 80.0,
                    'wind_speed' => 2.0, 'wind_direction' => 180.0, 'season' => 'Wet',
                ],
                'confidence' => ['value' => 70, 'label' => 'Moderate'],
            ]);
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        // Run the job twice for the SAME analytics run — as could happen on
        // a queue retry — and confirm only one comparison row exists.
        (new RunRiceYieldShadowComparison($run->id))->handle(app(\App\Services\MachineLearning\RiceYieldShadowComparisonService::class));
        (new RunRiceYieldShadowComparison($run->id))->handle(app(\App\Services\MachineLearning\RiceYieldShadowComparisonService::class));

        $this->assertSame(1, RiceYieldModelComparison::query()->count());
    }
}
