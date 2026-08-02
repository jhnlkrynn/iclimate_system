<?php

namespace Tests\Unit;

use App\Services\Risk\AgriculturalRiskScorer;
use PHPUnit\Framework\TestCase;

class AgriculturalRiskScorerTest extends TestCase
{
    private AgriculturalRiskScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new AgriculturalRiskScorer;
    }

    public function test_high_stress_inputs_score_as_severe(): void
    {
        $result = $this->scorer->score([
            'rainfall' => 115,
            'temperature' => 33,
            'predicted_yield' => 2.5,
            'farm_type' => 'Rainfed',
            'barangay' => 'Matabungkay',
            'stress_factor_count' => 2,
        ]);

        $this->assertSame(0.87, $result['score']);
        $this->assertSame('Severe', $result['level']);
        $this->assertSame('Severe Risk', $result['label']);
        $this->assertSame('red', $result['color']);
    }

    public function test_favorable_inputs_score_as_low(): void
    {
        $result = $this->scorer->score([
            'rainfall' => 230,
            'temperature' => 27,
            'predicted_yield' => 4.5,
            'farm_type' => 'Irrigated',
            'barangay' => '',
            'stress_factor_count' => 0,
        ]);

        $this->assertSame(0.29, $result['score']);
        $this->assertSame('Low', $result['level']);
        $this->assertSame('green', $result['color']);
    }

    public function test_mixed_inputs_score_as_moderate(): void
    {
        $result = $this->scorer->score([
            'rainfall' => 310,
            'temperature' => 30,
            'predicted_yield' => 3.5,
            'farm_type' => 'Rainfed',
            'barangay' => 'Unknown Barangay',
            'stress_factor_count' => 1,
        ]);

        $this->assertSame(0.6, $result['score']);
        $this->assertSame('Moderate', $result['level']);
        $this->assertSame('yellow', $result['color']);
    }

    public function test_missing_temperature_defaults_to_lowest_risk_band(): void
    {
        $withZeroTemp = $this->scorer->score([
            'rainfall' => 230,
            'temperature' => 0,
            'predicted_yield' => 4.5,
            'farm_type' => 'Irrigated',
            'barangay' => '',
            'stress_factor_count' => 0,
        ]);

        $withNormalTemp = $this->scorer->score([
            'rainfall' => 230,
            'temperature' => 27,
            'predicted_yield' => 4.5,
            'farm_type' => 'Irrigated',
            'barangay' => '',
            'stress_factor_count' => 0,
        ]);

        $this->assertSame($withNormalTemp['score'], $withZeroTemp['score']);
    }

    public function test_exposure_label_buckets_correctly(): void
    {
        $this->assertSame('high', $this->scorer->exposureLabel('Matabungkay'));
        $this->assertSame('moderate', $this->scorer->exposureLabel('Balibago'));
        $this->assertSame('low', $this->scorer->exposureLabel('Binubusan'));
        $this->assertSame('low', $this->scorer->exposureLabel('Nonexistent Barangay'));
    }
}
