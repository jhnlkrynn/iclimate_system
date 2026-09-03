<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', Str::password(24));

        $farmer = User::query()->updateOrCreate(
            ['email' => 'farmer@iclimate.com'],
            [
                'name' => 'Default Farmer',
                'password' => Hash::make($defaultPassword),
                'role' => User::ROLE_FARMER,
                'contact_number' => '09170000001',
                'address' => 'Lian, Batangas',
                'barangay' => 'Matabungkay',
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );

        $mao = User::query()->updateOrCreate(
            ['email' => 'mao@iclimate.com'],
            [
                'name' => 'Default MAO Personnel',
                'password' => Hash::make($defaultPassword),
                'role' => User::ROLE_MAO,
                'contact_number' => '09170000002',
                'address' => 'Municipal Agriculture Office, Lian, Batangas',
                'barangay' => 'Poblacion',
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );

        $itExpert = User::query()->updateOrCreate(
            ['email' => 'admin@iclimate.com'],
            [
                'name' => 'Default IT Expert',
                'password' => Hash::make($defaultPassword),
                'role' => User::ROLE_IT_EXPERT,
                'contact_number' => '09170000003',
                'address' => 'Lian, Batangas',
                'barangay' => 'Poblacion',
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
            ],
        );

        FarmerProfile::query()->updateOrCreate(
            ['user_id' => $farmer->id],
            [
                'full_name' => $farmer->name,
                'contact_number' => $farmer->contact_number,
                'address' => $farmer->address,
                'barangay' => $farmer->barangay,
                'farm_area' => 2.50,
                'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED,
            ],
        );

        if (ClimateRecord::query()->doesntExist()) {
            ClimateRecord::factory()->count(5)->create();
        }

        if (RiceProduction::query()->doesntExist()) {
            RiceProduction::factory()->count(5)->create();
        }

        if (HeatmapArea::query()->count() < 22) {
            HeatmapArea::factory()->count(5)->create();
        }

        if (Announcement::query()->doesntExist()) {
            Announcement::factory()->count(3)->create(['posted_by' => $mao->id, 'status' => 'Published']);
        }

        if (PlantingAdvisory::query()->doesntExist()) {
            PlantingAdvisory::factory()->count(3)->create(['posted_by' => $mao->id, 'status' => 'Published']);
        }

        if (Notification::query()->where('user_id', $farmer->id)->doesntExist()) {
            Notification::factory()->count(3)->create(['user_id' => $farmer->id]);
        }

        $this->call([
            KnowledgeBaseSeeder::class,
            AdvisoryRuleSeeder::class,
        ]);
    }
}
