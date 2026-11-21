<?php

namespace Tests\Unit;

use App\Models\RiceProduction;
use App\Services\Prediction\YieldLocalAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YieldLocalAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_factor_when_thresholds_are_met(): void
    {
        // Other barangay in Dry season: 5 records averaging 4.00 t/ha.
        foreach ([2020, 2021, 2022, 2023, 2024] as $year) {
            RiceProduction::query()->create([
                'barangay' => 'Prenza',
                'season' => 'Dry',
                'irrigation_type' => 'Irrigated',
                'yield_per_hectare' => 4.00,
                'year' => $year,
            ]);
        }

        // Target barangay in Dry season: 5 records across 5 seasons averaging 4.40 t/ha.
        // Lian avg = (5*4.00 + 5*4.40) / 10 = 4.20; factor = 4.40 / 4.20 = 1.0476.
        foreach ([2020, 2021, 2022, 2023, 2024] as $year) {
            RiceProduction::query()->create([
                'barangay' => 'Humayingan',
                'season' => 'Dry',
                'irrigation_type' => 'Rainfed',
                'yield_per_hectare' => 4.40,
                'year' => $year,
            ]);
        }

        $result = app(YieldLocalAdjustment::class)->forRequest('Humayingan', 'Rainfed', 'Dry', 4.10);

        $this->assertSame(4.1, $result['model_value']);
        $this->assertEqualsWithDelta(1.0476, $result['factor'], 0.0001);
        $this->assertEqualsWithDelta(round(4.10 * 1.0476, 2), $result['adjusted_yield'], 0.01);
        $this->assertSame(5, $result['record_count']);
        $this->assertSame(5, $result['seasons_used']);
    }

    public function test_falls_back_to_one_when_below_record_or_season_threshold(): void
    {
        // Only 4 records across 4 seasons (< 5 records).
        foreach ([2021, 2022, 2023, 2024] as $year) {
            RiceProduction::query()->create([
                'barangay' => 'Humayingan',
                'season' => 'Dry',
                'irrigation_type' => 'Rainfed',
                'yield_per_hectare' => 5.00,
                'year' => $year,
            ]);
        }

        $tooFewRecords = app(YieldLocalAdjustment::class)->forRequest('Humayingan', 'Rainfed', 'Dry', 4.10);
        $this->assertSame(1.0, $tooFewRecords['factor']);
        $this->assertSame(4.1, $tooFewRecords['adjusted_yield']);
        $this->assertSame(4, $tooFewRecords['record_count']);
        $this->assertSame(4, $tooFewRecords['seasons_used']);

        // 2 distinct seasons (< 3 seasons).
        foreach ([2022, 2023] as $year) {
            RiceProduction::query()->create([
                'barangay' => 'Balibago',
                'season' => 'Wet',
                'irrigation_type' => 'Irrigated',
                'yield_per_hectare' => 5.00,
                'year' => $year,
            ]);
        }

        $tooFewSeasons = app(YieldLocalAdjustment::class)->forRequest('Balibago', 'Irrigated', 'Wet', 3.91);
        $this->assertSame(1.0, $tooFewSeasons['factor']);
        $this->assertSame(3.91, $tooFewSeasons['adjusted_yield']);
        $this->assertSame(2, $tooFewSeasons['record_count']);
        $this->assertSame(2, $tooFewSeasons['seasons_used']);
    }

    public function test_clamps_factor_between_zero_point_eight_and_one_point_two(): void
    {
        // High-yield barangay vs very low-yield barangay in same season.
        foreach ([2020, 2021, 2022, 2023, 2024] as $year) {
            RiceProduction::query()->create([
                'barangay' => 'Prenza',
                'season' => 'Dry',
                'irrigation_type' => 'Irrigated',
                'yield_per_hectare' => 6.00,
                'year' => $year,
            ]);
            RiceProduction::query()->create([
                'barangay' => 'Matabungkay',
                'season' => 'Dry',
                'irrigation_type' => 'Rainfed',
                'yield_per_hectare' => 2.00,
                'year' => $year,
            ]);
        }

        // Lian avg = 4.00; Prenza raw = 1.5 -> clamped to 1.2; Matabungkay raw = 0.5 -> clamped to 0.8.
        $high = app(YieldLocalAdjustment::class)->forRequest('Prenza', 'Irrigated', 'Dry', 4.10);
        $this->assertSame(1.2, $high['factor']);
        $this->assertSame(4.92, $high['adjusted_yield']);

        $low = app(YieldLocalAdjustment::class)->forRequest('Matabungkay', 'Rainfed', 'Dry', 4.10);
        $this->assertSame(0.8, $low['factor']);
        $this->assertSame(3.28, $low['adjusted_yield']);
    }
}
