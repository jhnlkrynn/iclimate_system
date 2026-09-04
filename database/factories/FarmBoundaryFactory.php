<?php

namespace Database\Factories;

use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FarmBoundary> */
class FarmBoundaryFactory extends Factory
{
    protected $model = FarmBoundary::class;

    public function definition(): array
    {
        return [
            'farmer_profile_id' => FarmerProfile::factory(),
            'boundary_coordinates' => [
                ['lat' => 14.0300, 'lng' => 120.6500],
                ['lat' => 14.0300, 'lng' => 120.6510],
                ['lat' => 14.0310, 'lng' => 120.6510],
                ['lat' => 14.0310, 'lng' => 120.6500],
            ],
            'calculated_area_hectares' => 0.0115,
            'calculated_perimeter_meters' => 432.0,
        ];
    }
}
