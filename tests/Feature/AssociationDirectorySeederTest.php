<?php

namespace Tests\Feature;

use App\Models\FarmerOrganization;
use Database\Seeders\FarmerOrganizationSeeder;
use Database\Seeders\FarmersAssociationDirectorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification 2026-11-18: on MariaDB the directory seeder looked up "Tan-Ag Farmers Association" in barangay
 * "Sandiego", missed the existing "Tan-ag Farmers Association" (San Diego) and tried to insert it again — the
 * case-insensitive unique index rejected it, so `db:seed` stopped on a fresh MariaDB database. On SQLite the same
 * run silently created a duplicate association instead.
 */
class AssociationDirectorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_seeding_reuses_existing_associations_and_official_barangay_names(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        $this->seed(FarmersAssociationDirectorySeeder::class);
        $this->seed(FarmersAssociationDirectorySeeder::class);

        $tanAg = FarmerOrganization::query()->whereRaw('LOWER(name) = ?', ['tan-ag farmers association'])->get();
        $this->assertCount(1, $tanAg, 'one Tan-ag association, whatever the capitalisation');
        $this->assertSame('San Diego', $tanAg->first()->barangay);
        $this->assertNotNull($tanAg->first()->directory_key);
        $this->assertSame(0, FarmerOrganization::query()->where('barangay', 'Sandiego')->count());

        $names = FarmerOrganization::query()->pluck('name')->map(fn ($n) => mb_strtolower($n));
        $this->assertSame($names->count(), $names->unique()->count(), 'no association appears twice');
    }
}
