<?php

namespace Tests\Feature\Prediction;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\MonthlyWeatherPrediction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression coverage for the centralized prediction layer: a persisted
 * forecast must be reused instead of retraining the Random Forest on every
 * call, missing forecasts must dispatch a background job rather than
 * blocking, duplicate jobs for the same month must never be queued twice,
 * and the persisted table's unique constraint must prevent duplicate rows.
 */
class PredictionOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private function fakePrediction(float $rainfall = 180.5): array
    {
        return [
            'ready' => true,
            'message' => 'Prediction generated using the improved Ambulong historical monthly Random Forest model.',
            'source_type' => 'Trained Model',
            'source_name' => 'iClimate Ambulong Monthly Random Forest v5',
            'source_note' => 'Uses only legitimate Ambulong historical monthly climate records.',
            'training_period' => ['from' => '2015-01', 'to' => '2024-12'],
            'months_available' => 120,
            'predictions' => [
                'rainfall' => $rainfall,
                'tmax' => 31.2,
                'tmin' => 23.9,
                'temperature' => 27.55,
                'humidity' => 82.0,
                'wind_speed' => 2.1,
                'wind_direction' => 190.0,
                'season' => 'Wet',
            ],
            'confidence' => ['value' => 70, 'label' => 'Moderate'],
        ];
    }

    public function test_second_call_reads_the_persisted_row_and_never_retrains_the_model(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2026, 11, 1);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn($this->fakePrediction());
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        $orchestrator = app(PredictionOrchestrator::class);

        $first = $orchestrator->monthlyWeatherForecast($month);
        $second = $orchestrator->monthlyWeatherForecast($month);

        $this->assertTrue($first['ready']);
        $this->assertSame(180.5, $first['predictions']['rainfall']);
        $this->assertSame($first['predictions']['rainfall'], $second['predictions']['rainfall']);
        $this->assertSame(1, MonthlyWeatherPrediction::query()->count());
    }

    public function test_force_refresh_recomputes_even_when_a_fresh_row_already_exists(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2026, 11, 1);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->twice()->andReturn(
                $this->fakePrediction(180.5),
                $this->fakePrediction(210.0),
            );
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        $orchestrator = app(PredictionOrchestrator::class);

        $orchestrator->monthlyWeatherForecast($month);
        $refreshed = $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);

        $this->assertSame(210.0, $refreshed['predictions']['rainfall']);
        // Still exactly one row — the unique constraint upsert, not a
        // second competing row for the same month/model.
        $this->assertSame(1, MonthlyWeatherPrediction::query()->count());
    }

    public function test_missing_prediction_dispatches_a_background_job_and_never_computes_inline(): void
    {
        Queue::fake();
        Cache::flush();
        $month = CarbonImmutable::create(2027, 3, 1);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldNotReceive('predict');
        });

        $orchestrator = app(PredictionOrchestrator::class);
        $dispatched = $orchestrator->ensureMonthlyWeatherForecastQueued($month);

        $this->assertTrue($dispatched);
        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, fn ($job) => $job->targetMonth === $month->format('Y-m-d'));
        $this->assertSame(0, MonthlyWeatherPrediction::query()->count());
    }

    public function test_duplicate_background_jobs_are_not_queued_for_the_same_month(): void
    {
        Queue::fake();
        Cache::flush();
        $month = CarbonImmutable::create(2027, 4, 1);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldNotReceive('predict');
        });

        $orchestrator = app(PredictionOrchestrator::class);

        $this->assertTrue($orchestrator->ensureMonthlyWeatherForecastQueued($month));
        $this->assertFalse($orchestrator->ensureMonthlyWeatherForecastQueued($month));

        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, 1);
    }

    public function test_the_background_job_persists_the_same_way_the_inline_path_does(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2027, 5, 1);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn($this->fakePrediction(95.25));
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        (new GenerateMonthlyWeatherForecast($month->format('Y-m-d')))->handle(app(PredictionOrchestrator::class));

        $row = MonthlyWeatherPrediction::query()->first();
        $this->assertNotNull($row);
        $this->assertSame(2027, $row->prediction_year);
        $this->assertSame(5, $row->prediction_month);
        $this->assertSame(MonthlyWeatherRandomForest::MODEL_VERSION, $row->weather_model);
        $this->assertEqualsWithDelta(95.25, (float) $row->rainfall, 0.0001);
    }

    public function test_unready_model_output_is_never_stored_as_zero(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2027, 6, 1);

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

        $result = app(PredictionOrchestrator::class)->monthlyWeatherForecast($month);

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['predictions']);

        $row = MonthlyWeatherPrediction::query()->first();
        $this->assertSame(MonthlyWeatherPrediction::STATUS_UNREADY, $row->status);
        $this->assertNull($row->rainfall);
    }

    public function test_same_month_and_model_never_creates_a_duplicate_row(): void
    {
        MonthlyWeatherPrediction::query()->create([
            'prediction_year' => 2026,
            'prediction_month' => 11,
            'rainfall' => 100.0,
            'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
            'generated_at' => now(),
            'status' => MonthlyWeatherPrediction::STATUS_READY,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MonthlyWeatherPrediction::query()->create([
            'prediction_year' => 2026,
            'prediction_month' => 11,
            'rainfall' => 999.0,
            'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
            'generated_at' => now(),
            'status' => MonthlyWeatherPrediction::STATUS_READY,
        ]);
    }

    /**
     * A row generated against an older Ambulong CSV must be treated as
     * stale (regenerated) even while its normal TTL (expires_at) hasn't
     * elapsed yet — this is what actually implements "predictions become
     * stale if the verified historical source data changes" without
     * needing an unrelated application-data change to trigger it.
     */
    public function test_row_is_regenerated_when_the_underlying_dataset_version_changed(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2026, 11, 1);

        // A mutable "current dataset version" the test flips between the
        // two calls, rather than hardcoding a fragile call-count sequence.
        $datasetVersion = 'dataset-v1';

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) use (&$datasetVersion) {
            $mock->shouldReceive('predict')->twice()->andReturn(
                $this->fakePrediction(180.5),
                $this->fakePrediction(300.0),
            );
            // Explicit function+use(&...), not an arrow function — arrow
            // functions capture outer variables by value at creation time,
            // which would freeze this at 'dataset-v1' forever.
            $mock->shouldReceive('datasetVersion')->andReturnUsing(function () use (&$datasetVersion) {
                return $datasetVersion;
            });
        });

        $orchestrator = app(PredictionOrchestrator::class);

        $first = $orchestrator->monthlyWeatherForecast($month);
        $this->assertSame(180.5, $first['predictions']['rainfall']);

        // The dataset "changed" between the two calls (v1 -> v2) — the
        // orchestrator must not serve the v1-generated row as current.
        $datasetVersion = 'dataset-v2';
        $second = $orchestrator->monthlyWeatherForecast($month);

        $this->assertSame(300.0, $second['predictions']['rainfall']);
        $this->assertSame(1, MonthlyWeatherPrediction::query()->count());
    }

    /**
     * A row with no stored dataset_version at all (e.g. persisted before
     * this feature existed) must still be served normally — this check is
     * additive, not a forced mass-invalidation of pre-existing rows.
     */
    public function test_row_without_a_stored_dataset_version_is_still_treated_as_fresh(): void
    {
        Cache::flush();
        MonthlyWeatherPrediction::query()->create([
            'prediction_year' => 2026,
            'prediction_month' => 12,
            'rainfall' => 150.0,
            'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION,
            'generated_at' => now(),
            'expires_at' => now()->addHours(6),
            'status' => MonthlyWeatherPrediction::STATUS_READY,
            'metadata' => ['source_name' => 'legacy row'],
        ]);

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldNotReceive('predict');
            $mock->shouldReceive('datasetVersion')->andReturn('dataset-v1');
        });

        $result = app(PredictionOrchestrator::class)->monthlyWeatherForecast(CarbonImmutable::create(2026, 12, 1));

        $this->assertSame(150.0, $result['predictions']['rainfall']);
    }
}
