<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_is_redirected_to_farmer_dashboard(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get('/dashboard')
            ->assertRedirect(route('farmer.dashboard', absolute: false));
    }

    public function test_farmer_cannot_access_mao_or_admin_dashboards(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->get(route('mao.dashboard'))->assertForbidden();
        $this->actingAs($farmer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_mao_personnel_can_manage_mao_modules_but_not_it_expert_tools(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('climate-records.index'))->assertOk();
        $this->actingAs($mao)->get(route('users.index'))->assertForbidden();
    }

    public function test_it_expert_opens_system_pages_and_is_sent_back_from_field_pages(): void
    {
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($itExpert)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($itExpert)->get(route('users.index'))->assertOk();
        $this->actingAs($itExpert)->get(route('climate-records.index'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_only_it_expert_can_access_the_candidate_testing_page(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)->get(route('testing.candidate-rice-yield'))->assertForbidden();
        $this->actingAs($mao)->get(route('testing.candidate-rice-yield'))->assertForbidden();
        // The candidate model test page moved to Model Evaluation (2026-10): the old URL opens it.
        $this->actingAs($itExpert)->get(route('testing.candidate-rice-yield'))->assertRedirect(route('model-evaluation.index'));
    }
}
