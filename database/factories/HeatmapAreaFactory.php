<?php

namespace Database\Factories;

use App\Models\HeatmapArea;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeatmapArea> */
class HeatmapAreaFactory extends Factory
{
    public function definition(): array
    {
        $barangays = LianBarangays::coordinates();
        $barangay = fake()->randomElement(array_keys($barangays));
        $predictedYield = fake()->randomFloat(2, 2.4, 5.4);
        $rainfall = fake()->randomElement(['Low rainfall', 'Moderate rainfall', 'High rainfall']);
        $riskLevel = $predictedYield < 3.0 || $rainfall === 'Low rainfall'
            ? 'High'
            : ($predictedYield < 4.0 ? 'Moderate' : 'Low');
        $riskScore = ['Low' => 0.30, 'Moderate' => 0.60, 'High' => 0.90][$riskLevel];

        return [
            'barangay' => $barangay,
            'latitude' => $barangays[$barangay][0],
            'longitude' => $barangays[$barangay][1],
            'risk_level' => $riskLevel,
            'risk_type' => fake()->randomElement(['Flood', 'Drought', 'Typhoon', 'Heat']),
            'risk_score' => $riskScore,
            'predicted_yield' => $predictedYield,
            'rainfall_status' => $rainfall,
            'planting_advisory' => $riskLevel === 'High' ? 'Delay planting until rainfall stabilizes.' : 'Planting window is acceptable with routine monitoring.',
            'irrigation_recommendation' => $rainfall === 'Low rainfall' ? 'Prioritize irrigation support within 7 days.' : 'Maintain scheduled irrigation monitoring.',
            'description' => fake()->sentence(),
        ];
    }
}
