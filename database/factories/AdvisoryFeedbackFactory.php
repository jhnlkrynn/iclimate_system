<?php

namespace Database\Factories;

use App\Models\AdvisoryFeedback;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdvisoryFeedback> */
class AdvisoryFeedbackFactory extends Factory
{
    protected $model = AdvisoryFeedback::class;

    public function definition(): array
    {
        return [
            'planting_advisory_id' => PlantingAdvisory::factory(),
            'user_id' => User::factory(),
            'is_helpful' => fake()->boolean(70),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
