<?php

namespace Database\Factories;

use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlantingAdvisory> */
class PlantingAdvisoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['Planting', 'Harvesting', 'Irrigation', 'Climate']),
            'target_barangay' => fake()->randomElement(['Binubusan', 'Lumaniag', 'Malaruhatan', 'Matabungkay', 'Prenza']),
            'posted_by' => User::factory()->maoPersonnel(),
            'status' => fake()->randomElement(['Draft', 'Published']),
        ];
    }
}
