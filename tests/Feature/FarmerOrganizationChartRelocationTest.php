<?php

namespace Tests\Feature;

use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\User;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FarmerOrganizationChartRelocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DashboardController/FarmerOrganizationSummaryService cache the summary
        // for 45s under a shared key; clear it so each test sees fresh data.
        Cache::forget('dashboard:farmer-organizations');
    }

    public function test_mao_dashboard_no_longer_shows_the_organization_chart(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('Farmers by Organization')
            ->assertDontSee('farmersByOrganizationChart', false);
    }

    public function test_mao_dashboard_shows_registered_farmers_with_organizations_linking_to_farmer_profiles(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $organization = FarmerOrganization::query()->where('name', 'Balibago Farmers Association')->firstOrFail();
        FarmerProfile::factory()->create(['farmer_organization_id' => $organization->id]);

        // MAO Dashboard (2026-10): one "Registered farmers" tile that opens Farmer Profiles, where the organizations are.
        $response = $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertSee('Registered farmers')
            ->assertSee('href="'.route('farmer-profiles.index').'" data-kpi="farmers"', false);

        $this->assertSame(1, $response->viewData('mao')['farmers']);
    }



    public function test_farmer_profiles_page_shows_a_compact_organization_chart_to_mao(): void
    {
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
            ->assertSee($organization->name)
            ->assertSee('data-association-kpis', false)
            ->assertSee('Total Farmers')
            ->assertSee('Top Association');
    }

    public function test_farmer_does_not_see_the_organization_chart_on_their_own_profile_page(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $this->actingAs($farmer)
            ->get(route('farmer-profiles.index'))
            ->assertOk()
            ->assertDontSee('Farmers by Organization');
    }

    public function test_farmer_profiles_chart_renders_full_interactive_markup(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('farmer-profiles.index'))
            ->assertOk()
            ->assertSee('data-association-kpis', false)
            ->assertSee('Total Farmers')
            ->assertSee('Associations')
            ->assertSee('Top Association')
            ->assertSee('No Organization');
    }


}
