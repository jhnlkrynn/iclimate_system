<?php

namespace Database\Factories;

use App\Models\ClimateRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClimateRecord> */
class ClimateRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'record_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'rainfall' => fake()->randomFloat(2, 0, 250),
            'temperature' => fake()->randomFloat(2, 22, 38),
            'humidity' => fake()->randomFloat(2, 55, 98),
            'wind_speed' => fake()->randomFloat(2, 0, 80),
            'season' => fake()->randomElement([ClimateRecord::SEASON_WET, ClimateRecord::SEASON_DRY]),
            'source' => 'PAGASA',
        ];
    }
}