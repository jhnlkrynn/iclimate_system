<?php

namespace Database\Factories;

use App\Models\RiceProduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiceProduction> */
class RiceProductionFactory extends Factory
{
    public function definition(): array
    {
        $area = fake()->randomFloat(2, 1, 25);
        $yield = fake()->randomFloat(2, 2, 6);

        return [
            'barangay' => fake()->randomElement(['Binubusan', 'Lumaniag', 'Malaruhatan', 'Matabungkay', 'Prenza']),
            'season' => fake()->randomElement(['Wet', 'Dry']),
            'irrigation_type' => fake()->randomElement(['Rainfed', 'Irrigated']),
            'yield_per_hectare' => $yield,
            'area_hectares' => $area,
            'total_production' => round($area * $yield, 2),
            'year' => fake()->numberBetween(2020, 2026),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
