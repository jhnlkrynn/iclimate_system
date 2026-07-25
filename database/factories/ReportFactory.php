<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_type' => fake()->randomElement(['Climate', 'Production', 'Advisory', 'Heat Map']),
            'title' => fake()->sentence(5),
            'generated_by' => User::factory()->maoPersonnel(),
            'payload' => [
                'summary' => fake()->sentence(),
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }
}
