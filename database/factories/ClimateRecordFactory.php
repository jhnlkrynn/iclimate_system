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
            // Honest provenance label for factory/seeder-generated rows: this
            // is synthetic demo data, never a real PAGASA feed. (Previously
            // 'PAGASA Historical Climate Record', which a 2026-09 audit found
            // could be mistaken for a real agency source; existing DB rows
            // written before this change keep their original stored value.)
            'source' => 'iClimate Seed Data (synthetic)',
        ];
    }
}
