<?php

namespace Database\Factories;

use App\Models\HeatmapArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeatmapArea> */
class HeatmapAreaFactory extends Factory
{
    public function definition(): array
    {
        $barangays = [
            'Bagong Pook' => [14.0182000, 120.6408000],
            'Balibago' => [13.9898000, 120.6524000],
            'Barangay 1 (Pob.)' => [14.0361000, 120.6507000],
            'Barangay 2 (Pob.)' => [14.0371000, 120.6530000],
            'Barangay 3 (Pob.)' => [14.0349000, 120.6489000],
            'Barangay 4 (Pob.)' => [14.0383000, 120.6484000],
            'Barangay 5 (Pob.)' => [14.0339000, 120.6525000],
            'Binubusan' => [14.0296000, 120.6364000],
            'Bungahan' => [14.0480000, 120.6285000],
            'Cumba' => [14.0205000, 120.7045000],
            'Humayingan' => [14.0108000, 120.6888000],
            'Kapito' => [14.0557000, 120.6765000],
            'Lumaniag' => [14.0479000, 120.6613000],
            'Luyahan' => [13.9926000, 120.6355000],
            'Malaruhatan' => [14.0119000, 120.6669000],
            'Matabungkay' => [13.9593000, 120.6227000],
            'Prenza' => [14.0353000, 120.6822000],
            'Puting-Kahoy' => [14.0290000, 120.7015000],
            'San Diego' => [13.9793000, 120.6118000],
        ];
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
