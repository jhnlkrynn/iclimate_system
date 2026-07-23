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

    public function test_it_expert_has_full_module_access(): void
    {
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($itExpert)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($itExpert)->get(route('users.index'))->assertOk();
        $this->actingAs($itExpert)->get(route('climate-records.index'))->assertOk();
    }
}
