<?php

namespace Database\Factories;

use App\Models\HeatmapArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeatmapArea> */
class HeatmapAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barangay' => fake()->randomElement(['Binubusan', 'Lumaniag', 'Malaruhatan', 'Matabungkay', 'Prenza']),
            'risk_level' => fake()->randomElement(['Low', 'Moderate', 'High', 'Severe']),
            'risk_type' => fake()->randomElement(['Flood', 'Drought', 'Typhoon', 'Heat']),
            'description' => fake()->sentence(),
        ];
    }
}