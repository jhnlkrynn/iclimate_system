<?php

namespace Tests\Feature;

use App\Models\FarmBoundary;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Farm boundary drawing was removed from the farmer pages (2026-10): the editor URLs open My Farm. Boundaries already
 * saved stay in the database and MAO / IT still see them on the farmer's profile.
 */
class FarmBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_farmer_boundary_editor_opens_my_farm_and_saves_nothing(): void
    {
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $this->actingAs($farmer)->get(route('farmer.boundary.edit'))->assertRedirect(route('farmer.my-farm'));
        $this->actingAs($farmer)->put(route('farmer.boundary.update'), ['points' => []])->assertRedirect(route('farmer.my-farm'));
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), ['points' => []])->assertRedirect(route('farmer.my-farm'));
        $this->assertSame(0, FarmBoundary::query()->where('farmer_profile_id', $profile->id)->count());
    }

    public function test_non_farmer_roles_cannot_use_farmer_boundary_editor(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $itExpert = User::factory()->itExpert()->create();

        $this->actingAs($mao)->get(route('farmer.boundary.edit'))->assertForbidden();
        $this->actingAs($itExpert)->get(route('farmer.boundary.edit'))->assertForbidden();
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
}
