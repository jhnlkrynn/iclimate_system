<?php

namespace Tests\Feature;

use App\Services\DecisionSupportService;
use App\Services\Risk\AgriculturalRiskScorer;
use Tests\TestCase;

class RiskParityTest extends TestCase
{
    public function test_decision_support_risk_matches_the_shared_scorer_directly(): void
    {
        $scorer = app(AgriculturalRiskScorer::class);
        $decisionSupport = app(DecisionSupportService::class);

        $cases = [
            ['rainfall' => 115, 'temperature' => 33, 'predicted_yield' => 2.5, 'farm_type' => 'Rainfed', 'barangay' => 'Matabungkay'],
            ['rainfall' => 230, 'temperature' => 27, 'predicted_yield' => 4.5, 'farm_type' => 'Irrigated', 'barangay' => ''],
            ['rainfall' => 310, 'temperature' => 30, 'predicted_yield' => 3.5, 'farm_type' => 'Rainfed', 'barangay' => 'Balibago'],
        ];

        foreach ($cases as $case) {
            $decision = $decisionSupport->evaluate([
                'rainfall' => $case['rainfall'],
                'temperature' => $case['temperature'],
                'wind_speed' => 5,
                'humidity' => 75,
                'predicted_yield' => $case['predicted_yield'],
                'season' => 'Wet',
                'farm_type' => $case['farm_type'],
                'barangay' => $case['barangay'],
            ]);

            $directScore = $scorer->score([
                'rainfall' => $case['rainfall'],
                'temperature' => $case['temperature'],
                'predicted_yield' => $case['predicted_yield'],
                'farm_type' => $case['farm_type'],
                'barangay' => $case['barangay'],
                'stress_factor_count' => count($decision['stress_factors']),
            ]);

            $this->assertSame(
                $directScore,
                $decision['risk'],
                "DecisionSupportService's risk output diverged from AgriculturalRiskScorer for barangay [{$case['barangay']}] — unification broken."
            );
        }
    }
}
