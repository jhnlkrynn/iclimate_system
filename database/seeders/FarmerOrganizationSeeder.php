<?php

namespace Database\Seeders;

use App\Models\FarmerOrganization;
use App\Support\LianFarmTypes;
use Illuminate\Database\Seeder;

class FarmerOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        LianFarmTypes::records()->each(function (array $record): void {
            FarmerOrganization::query()->updateOrCreate(
                ['name' => $record['association']],
                [
                    'barangay' => $record['barangay'],
                    'farm_type' => $record['farm_type'],
                    'area_hectares' => $record['area_hectares'],
                    'farmer_count' => $record['farmers'],
                ],
            );
        });
    }
}
