<?php

namespace Database\Factories;

use App\Models\UsabilitySurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsabilitySurveyResponse>
 */
class UsabilitySurveyResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'role' => $user->role,
            'item_1' => $this->faker->numberBetween(1, 5),
            'item_2' => $this->faker->numberBetween(1, 5),
            'item_3' => $this->faker->numberBetween(1, 5),
            'item_4' => $this->faker->numberBetween(1, 5),
            'item_5' => $this->faker->numberBetween(1, 5),
            'item_6' => $this->faker->numberBetween(1, 5),
            'item_7' => $this->faker->numberBetween(1, 5),
            'item_8' => $this->faker->numberBetween(1, 5),
            'item_9' => $this->faker->numberBetween(1, 5),
            'item_10' => $this->faker->numberBetween(1, 5),
            'comments' => null,
        ];
    }
}
