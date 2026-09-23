<?php

namespace Tests\Feature\Prediction;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\MonthlyWeatherPrediction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshIclimatePredictionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakePrediction(float $rainfall): array
    {
        return [
            'ready' => true,
            'message' => 'ok',
            'source_name' => 'iClimate Ambulong Monthly Random Forest v5',
            'training_period' => ['from' => '2015-01', 'to' => '2024-12'],
            'months_available' => 120,
            'predictions' => [
                'rainfall' => $rainfall,
                'tmax' => 31.0,
                'tmin' => 24.0,
                'temperature' => 27.5,
                'humidity' => 80.0,
                'wind_speed' => 2.0,
                'wind_direction' => 180.0,
                'season' => 'Wet',
            ],
            'confidence' => ['value' => 70, 'label' => 'Moderate'],
        ];
    }

    /**
     * The default (no options) invocation must queue background generation
     * for the full 12-month operational horizon without ever training the
     * model inline in the CLI process itself.
     */
    public function test_default_invocation_queues_the_12_month_horizon_without_computing_inline(): void
    {
        Queue::fake();
        Cache::flush();

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldNotReceive('predict');
        });

        $this->artisan('iclimate:refresh-predictions')->assertSuccessful();

        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, 12);
        $this->assertSame(0, MonthlyWeatherPrediction::query()->count());
    }

    /**
     * Running the default invocation twice must not queue duplicate jobs
     * for months already queued/fresh.
     */
    public function test_running_the_default_invocation_twice_does_not_duplicate_jobs(): void
    {
        Queue::fake();
        Cache::flush();

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldNotReceive('predict');
        });

        $this->artisan('iclimate:refresh-predictions')->assertSuccessful();
        $this->artisan('iclimate:refresh-predictions')->assertSuccessful();

        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, 12);
    }

    public function test_year_and_month_option_force_refreshes_synchronously(): void
    {
        Cache::flush();

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->once()->andReturn($this->fakePrediction(150.0));
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        $this->artisan('iclimate:refresh-predictions', ['--year' => 2026, '--month' => 11])
            ->assertSuccessful();

        $row = MonthlyWeatherPrediction::query()
            ->where('prediction_year', 2026)
            ->where('prediction_month', 11)
            ->first();

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(150.0, (float) $row->rainfall, 0.0001);
    }

    public function test_all_option_force_refreshes_every_month_in_the_horizon_synchronously(): void
    {
        Cache::flush();

        $this->mock(MonthlyWeatherRandomForest::class, function ($mock) {
            $mock->shouldReceive('predict')->times(12)->andReturn($this->fakePrediction(100.0));
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset-v1');
        });

        $this->artisan('iclimate:refresh-predictions', ['--all' => true])->assertSuccessful();

        $this->assertSame(12, MonthlyWeatherPrediction::query()->count());
    }
}
