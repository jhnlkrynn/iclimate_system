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
            $existingName = $record['association'] === 'Molino-Resgawa Farmers Irrigators Association Inc'
                ? 'Molino-Resagwa Farmers Irrigators Association Inc'
                : null;
            $alreadyRegistered = FarmerOrganization::query()
                ->where(fn ($query) => $query->where('name', $record['association'])->when($existingName, fn ($query) => $query->orWhere('name', $existingName)))
                ->exists();
            if ($alreadyRegistered) {
                return;
            }

            // Keep an existing association record (including any MAO-maintained fields); only add the official
            // catalog entry when it is not already present.
            FarmerOrganization::query()->firstOrCreate(
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
