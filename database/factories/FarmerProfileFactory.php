<?php

namespace Database\Factories;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FarmerProfile> */
class FarmerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->farmer(),
            'full_name' => fake()->name(),
            'contact_number' => fake()->numerify('09#########'),
            'address' => fake()->streetAddress(),
            'barangay' => fake()->randomElement(['Binubusan', 'Lumaniag', 'Malaruhatan', 'Matabungkay', 'Prenza']),
            'farm_area' => fake()->randomFloat(2, 0.5, 8),
            'farm_type' => fake()->randomElement([FarmerProfile::FARM_TYPE_RAINFED, FarmerProfile::FARM_TYPE_IRRIGATED]),
        ];
    }
}