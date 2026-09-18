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

    public function test_organizations_link_is_removed_from_all_user_role_sidebars_and_dashboards(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertDontSee('Organizations')
            ->assertDontSee('farmer-organizations');

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('>Organizations<', false)
            ->assertDontSee('View all organizations', false)
            ->assertDontSee('farmer-organizations');

        $this->actingAs($itExpert)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Organizations<', false)
            ->assertDontSee('farmer-organizations');
    }

    public function test_visiting_old_farmer_organizations_route_redirects_each_role_to_its_dashboard(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)->get('/farmer-organizations')->assertRedirect(route('farmer.dashboard'));
        $this->actingAs($farmer)->get('/farmer-organizations/1')->assertRedirect(route('farmer.dashboard'));

        $this->actingAs($mao)->get('/farmer-organizations')->assertRedirect(route('mao.dashboard'));
        $this->actingAs($mao)->get('/farmer-organizations/1')->assertRedirect(route('mao.dashboard'));

        $this->actingAs($itExpert)->get('/farmer-organizations')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($itExpert)->get('/farmer-organizations/1')->assertRedirect(route('admin.dashboard'));
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
