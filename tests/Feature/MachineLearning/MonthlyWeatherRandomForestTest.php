<?php

namespace Tests\Feature\MachineLearning;

use App\Models\ClimateRecord;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonthlyWeatherRandomForestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_ready_and_trains_from_the_real_historical_dataset(): void
    {
        Cache::flush();

        $forest = app(MonthlyWeatherRandomForest::class);
        $prediction = $forest->predict(now()->startOfMonth()->toImmutable());

        $this->assertTrue($prediction['ready']);
        $this->assertSame(131, $prediction['months_available']);
        $this->assertStringContainsString('Ambulong', $prediction['source_note']);
        $this->assertArrayHasKey('rainfall', $prediction['predictions']);
        $this->assertArrayHasKey('confidence', $prediction);

        // Sanity: predictions must be physically plausible, unlike the old
        // contaminated 6-row model (1346mm rainfall, 56.98C, 299 km/h wind).
        $this->assertLessThan(1000, $prediction['predictions']['rainfall']);
        $this->assertLessThan(50, $prediction['predictions']['temperature']);
    }

    public function test_extreme_synthetic_or_invalid_climate_record_rows_never_influence_the_forest(): void
    {
        Cache::flush();
        $forest = app(MonthlyWeatherRandomForest::class);
        $baseline = $forest->predict(now()->startOfMonth()->toImmutable());

        // Seed a ClimateRecord row resembling the historical "sir mags" bad
        // row: physically impossible values that, under the OLD architecture,
        // would have contaminated training.
        ClimateRecord::factory()->create([
            'record_date' => now()->subMonth()->toDateString(),
            'rainfall' => 5000,
            'temperature' => 100,
            'humidity' => 999,
            'wind_speed' => 500,
            'season' => 'Wet',
            'source' => 'sir mags',
        ]);

        Cache::flush();
        $forest = app(MonthlyWeatherRandomForest::class);
        $afterSeeding = $forest->predict(now()->startOfMonth()->toImmutable());

        $this->assertTrue($afterSeeding['ready']);
        $this->assertSame(
            $baseline['predictions'],
            $afterSeeding['predictions'],
            'ClimateRecord content must never change MonthlyWeatherRandomForest output — it no longer reads that table.'
        );
        $this->assertLessThan(1000, $afterSeeding['predictions']['rainfall']);
        $this->assertLessThan(50, $afterSeeding['predictions']['temperature']);
    }

    public function test_training_path_never_queries_climate_record_at_all(): void
    {
        // Direct source-level guarantee: the class that trains the forest no
        // longer references the ClimateRecord model anywhere.
        $source = file_get_contents(app_path('Services/MachineLearning/MonthlyWeatherRandomForest.php'));

        $this->assertStringNotContainsString('ClimateRecord::', $source);
        $this->assertStringNotContainsString('use App\Models\ClimateRecord;', $source);
    }

    public function test_readiness_is_false_when_the_historical_dataset_is_unavailable_regardless_of_climate_record(): void
    {
        Cache::flush();

        // Even with a fully valid, generous ClimateRecord history seeded,
        // readiness must depend on the historical CSV dataset, not on
        // ClimateRecord.
        foreach (range(1, 6) as $i) {
            ClimateRecord::factory()->create([
                'record_date' => now()->subMonths(7 - $i)->startOfMonth()->toDateString(),
                'rainfall' => 150,
                'temperature' => 28,
                'humidity' => 75,
                'wind_speed' => 10,
                'season' => 'Wet',
            ]);
        }

        $this->app->instance(
            AmbulongHistoricalClimateDataset::class,
            new AmbulongHistoricalClimateDataset(sys_get_temp_dir().'/missing-'.uniqid().'.csv')
        );

        $forest = app(MonthlyWeatherRandomForest::class);
        $prediction = $forest->predict(now()->startOfMonth()->toImmutable());

        $this->assertFalse($prediction['ready']);
        $this->assertSame(0, $prediction['months_available']);
        $this->assertSame([], $prediction['predictions']);
    }

    public function test_readiness_is_true_when_dataset_is_available_even_with_empty_climate_record_table(): void
    {
        Cache::flush();

        // No ClimateRecord rows seeded at all (RefreshDatabase leaves it
        // empty) — the forest must still be ready, because it trains from
        // the CSV dataset, independent of ClimateRecord entirely.
        $this->assertSame(0, ClimateRecord::query()->count());

        $forest = app(MonthlyWeatherRandomForest::class);
        $prediction = $forest->predict(now()->startOfMonth()->toImmutable());

        $this->assertTrue($prediction['ready']);
    }
}
