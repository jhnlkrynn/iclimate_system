<?php

namespace Database\Factories;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SystemLog> */
class SystemLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->itExpert(),
            'action' => fake()->randomElement(['login', 'create_record', 'update_record', 'generate_report']),
            'details' => fake()->sentence(),
        ];
    }
}
