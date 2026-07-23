<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(),
            'category' => fake()->randomElement(['News', 'Event', 'Training', 'Seed Distribution', 'Fertilizer Distribution']),
            'posted_by' => User::factory()->maoPersonnel(),
            'status' => fake()->randomElement(['Draft', 'Published']),
        ];
    }
}
