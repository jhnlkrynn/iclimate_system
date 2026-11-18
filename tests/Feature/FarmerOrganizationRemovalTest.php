<?php

namespace Tests\Feature;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FarmerOrganizationRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DashboardController caches the "Farmers by Organization" summary for
        // 45s under a shared key; an earlier test in this class hitting a
        // dashboard before organizations are seeded would otherwise leak a
        // stale empty result into a later test.
        Cache::forget('dashboard:farmer-organizations');
    }





    public function test_unauthenticated_request_to_farmer_organizations_redirects_to_login(): void
    {
        $this->get('/farmer-organizations')->assertRedirect(route('login'));
    }

    public function test_farmer_organization_named_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('farmer-organizations.index'));
        $this->assertFalse(Route::has('farmer-organizations.show'));
    }

    public function test_farmer_organization_chart_still_shows_live_data_on_farmer_profiles_page(): void
    {
        // The dedicated organizations list page was removed, but the "Farmers by
        // Organization" chart itself lives on now (moved to the Farmer Profiles
        // page) — see FarmerOrganizationChartRelocationTest for full coverage.
        $this->seed(FarmerOrganizationSeeder::class);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $organization = FarmerOrganization::query()->where('name', 'Balibago Farmers Association')->firstOrFail();
        FarmerProfile::factory()->create([
            'farmer_organization_id' => $organization->id,
            'barangay' => $organization->barangay,
            'farm_type' => $organization->farm_type,
        ]);

        $this->actingAs($mao)
            ->get(route('farmer-profiles.index'))
            ->assertOk()
            ->assertSee('Farmers by Organization')
            ->assertSee($organization->name);
    }
}
