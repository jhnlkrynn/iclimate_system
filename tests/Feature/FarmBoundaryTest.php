<?php

namespace Tests\Feature;

use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_can_save_and_view_their_own_boundary(): void
    {
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id]);
        $points = $this->points();

        $this->actingAs($farmer)
            ->get(route('farmer.boundary.edit'))
            ->assertOk()
            ->assertSee('My Farm Boundary');

        $this->actingAs($farmer)
            ->put(route('farmer.boundary.update'), [
                'farm_area' => '2.50',
                'boundary_coordinates' => json_encode($points),
            ])
            ->assertRedirect(route('farmer.boundary.edit', absolute: false));

        $boundary = FarmBoundary::query()->where('farmer_profile_id', $profile->id)->firstOrFail();
        $this->assertSame('2.50', (string) $profile->fresh()->farm_area);
        $this->assertCount(4, $boundary->boundary_coordinates);
        $this->assertGreaterThan(0, (float) $boundary->calculated_area_hectares);
        $this->assertGreaterThan(0, (float) $boundary->calculated_perimeter_meters);
    }

    public function test_farmer_without_a_profile_gets_one_when_opening_boundary_editor(): void
    {
        $farmer = User::factory()->farmer()->create([
            'barangay' => 'Matabungkay',
        ]);

        $this->actingAs($farmer)
            ->get(route('farmer.boundary.edit'))
            ->assertOk()
            ->assertSee('My Farm Boundary');

        $this->assertDatabaseHas('farmer_profiles', [
            'user_id' => $farmer->id,
            'barangay' => 'Matabungkay',
        ]);
    }

    public function test_farmer_cannot_update_another_farmers_boundary(): void
    {
        $owner = User::factory()->farmer()->create();
        $ownerProfile = FarmerProfile::factory()->create(['user_id' => $owner->id]);
        $attacker = User::factory()->farmer()->create();
        FarmerProfile::factory()->create(['user_id' => $attacker->id]);
        FarmBoundary::factory()->create(['farmer_profile_id' => $ownerProfile->id]);

        $this->actingAs($attacker)
            ->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode($this->points())])
            ->assertRedirect(route('farmer.boundary.edit', absolute: false));

        $this->assertSame(1, FarmBoundary::query()->where('farmer_profile_id', $ownerProfile->id)->count());
        $this->assertSame(2, FarmBoundary::query()->count());
    }

    public function test_non_farmer_roles_cannot_use_farmer_boundary_editor(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $itExpert = User::factory()->itExpert()->create();

        $this->actingAs($mao)->get(route('farmer.boundary.edit'))->assertForbidden();
        $this->actingAs($itExpert)->get(route('farmer.boundary.edit'))->assertForbidden();
    }

    public function test_boundary_requires_three_points_and_lian_coordinates(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $this->actingAs($farmer)
            ->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode([
                ['lat' => 14.03, 'lng' => 120.65],
                ['lat' => 14.04, 'lng' => 120.65],
            ])])
            ->assertSessionHasErrors('boundary_coordinates');

        $this->actingAs($farmer)
            ->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode([
                ['lat' => 15.03, 'lng' => 120.65],
                ['lat' => 15.04, 'lng' => 120.65],
                ['lat' => 15.04, 'lng' => 120.66],
            ])])
            ->assertSessionHasErrors('boundary_coordinates');
    }

    public function test_self_intersecting_boundaries_are_rejected(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $this->actingAs($farmer)
            ->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode([
                ['lat' => 14.0300, 'lng' => 120.6500],
                ['lat' => 14.0310, 'lng' => 120.6510],
                ['lat' => 14.0310, 'lng' => 120.6500],
                ['lat' => 14.0300, 'lng' => 120.6510],
            ])])
            ->assertSessionHasErrors('boundary_coordinates');
    }

    public function test_mao_and_it_expert_can_view_a_saved_boundary(): void
    {
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id]);
        FarmBoundary::factory()->create(['farmer_profile_id' => $profile->id]);

        $this->actingAs(User::factory()->maoPersonnel()->create())
            ->get(route('farmer-profiles.show', $profile))
            ->assertOk()
            ->assertSee('Mapped Farm Boundary');

        $this->actingAs(User::factory()->itExpert()->create())
            ->get(route('farmer-profiles.show', $profile))
            ->assertOk()
            ->assertSee('Mapped Farm Boundary');
    }

    /** @return array<int, array{lat: float, lng: float}> */
    private function points(): array
    {
        return [
            ['lat' => 14.0300, 'lng' => 120.6500],
            ['lat' => 14.0300, 'lng' => 120.6510],
            ['lat' => 14.0310, 'lng' => 120.6510],
            ['lat' => 14.0310, 'lng' => 120.6500],
        ];
    }
}
