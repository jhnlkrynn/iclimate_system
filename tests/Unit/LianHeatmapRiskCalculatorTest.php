<?php

namespace Tests\Unit;

use App\Models\ClimateRecord;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Services\Heatmap\LianHeatmapRiskCalculator;
use Tests\TestCase;

class LianHeatmapRiskCalculatorTest extends TestCase
{
    public function test_missing_verified_inputs_return_insufficient_data(): void
    {
        $result = app(LianHeatmapRiskCalculator::class)->calculate(
            'Bagong Pook',
            null,
            null,
            null,
            null,
            ['farm_system' => 'Unknown']
        );

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame('Insufficient data', $result['display_level']);
        $this->assertNull($result['risk_score']);
        $this->assertSame([], $result['components']);
    }

    public function test_calculates_risk_from_separated_verified_sources(): void
    {
        $climate = new ClimateRecord([
            'record_date' => '2026-09-10',
            'rainfall' => 120,
            'temperature' => 33,
            'humidity' => 88,
            'wind_speed' => 25,
            'source' => 'PAGASA Historical Climate Record',
        ]);

        $advisory = new PlantingAdvisory([
            'title' => 'Heavy rainfall advisory for Lian',
            'severity' => PlantingAdvisory::SEVERITY_HIGH,
            'source' => PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now(),
        ]);

        $storedArea = new HeatmapArea([
            'predicted_yield' => 2.7,
            'description' => 'Validated by trained rice yield model.',
        ]);

        $result = app(LianHeatmapRiskCalculator::class)->calculate(
            'Matabungkay',
            [
                'status' => 'fresh',
                'source' => 'Open-Meteo Forecast API',
                'scope' => 'barangay-coordinate forecast',
                'rainfall_24h_mm' => 90,
                'rainfall_7d_mm' => 220,
                'temperature_c' => 34,
                'humidity_percent' => 90,
                'wind_speed_kmh' => 42,
                'fetched_at' => now()->toIso8601String(),
            ],
            [
                'status' => 'fresh',
                'stale' => false,
                'source' => 'PAGASA WIS/OAPI surface observation',
                'scope' => 'official station observation; not barangay-specific',
                'station_name' => 'Ambulong',
                'observed_at' => now()->toIso8601String(),
                'rainfall_mm' => 42,
                'temperature_c' => 33,
                'humidity_percent' => 87,
                'wind_speed_kmh' => 40,
            ],
            $climate,
            $advisory,
            [
                'recorded_yield_latest' => 2.8,
                'recorded_yield_year' => 2026,
                'farm_system' => 'Rainfed',
                'farm_system_year' => 2026,
                'farm_system_source' => 'Rice production records',
            ],
            $storedArea
        );

        $this->assertSame('calculated', $result['status']);
        $this->assertContains($result['risk_level'], ['Moderate', 'High', 'Severe']);
        $this->assertGreaterThanOrEqual(0.68, $result['risk_score']);
        $this->assertArrayHasKey('forecast_weather', $result['components']);
        $this->assertArrayHasKey('official_pagasa_observation', $result['components']);
        $this->assertArrayHasKey('observed_climate', $result['components']);
        $this->assertArrayHasKey('pagasa_advisory', $result['components']);
        $this->assertArrayHasKey('rice_yield', $result['components']);
        $this->assertTrue($result['data_sections']['official_observed']['available']);
        $this->assertTrue($result['data_sections']['forecast']['available']);
        $this->assertTrue($result['data_sections']['observed']['available']);
        $this->assertTrue($result['data_sections']['pagasa']['available']);
    }

    public function test_municipality_wide_weather_scope_is_labeled_without_local_variation_claims(): void
    {
        $result = app(LianHeatmapRiskCalculator::class)->calculate(
            'Balibago',
            [
                'status' => 'fresh',
                'scope' => 'municipality',
                'rainfall_24h_mm' => 10,
                'rainfall_7d_mm' => 30,
            ],
            null,
            null,
            null,
            ['farm_system' => 'Unknown']
        );

        $this->assertStringContainsString('municipality-wide reading', $result['weather_scope_note']);
        $this->assertSame('municipality', $result['data_sections']['forecast']['scope']);
    }
}
