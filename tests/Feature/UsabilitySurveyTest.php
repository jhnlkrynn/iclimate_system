<?php

namespace Tests\Feature;

use App\Models\UsabilitySurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsabilitySurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_evaluation_link_is_removed_from_all_user_role_sidebars_and_dashboards(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertDontSee('System Evaluation')
            ->assertDontSee('usability-survey');

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('System Evaluation')
            ->assertDontSee('usability-survey');

        $this->actingAs($itExpert)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('System Evaluation')
            ->assertDontSee('usability-survey');
    }

    public function test_visiting_old_usability_survey_route_redirects_farmer_to_farmer_dashboard(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get('/usability-survey')
            ->assertRedirect(route('farmer.dashboard'));

        $this->actingAs($farmer)
            ->get('/usability-survey/results')
            ->assertRedirect(route('farmer.dashboard'));
    }

    public function test_visiting_old_usability_survey_route_redirects_mao_to_mao_dashboard(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get('/usability-survey')
            ->assertRedirect(route('mao.dashboard'));

        $this->actingAs($mao)
            ->get('/usability-survey/results')
            ->assertRedirect(route('mao.dashboard'));
    }

    public function test_visiting_old_usability_survey_route_redirects_it_expert_to_admin_dashboard(): void
    {
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($itExpert)
            ->get('/usability-survey')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($itExpert)
            ->get('/usability-survey/results')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_unauthenticated_request_to_usability_survey_redirects_to_login(): void
    {
        $this->get('/usability-survey')
            ->assertRedirect(route('login'));
    }

    public function test_existing_database_records_and_model_remain_intact(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = UsabilitySurveyResponse::query()->create([
            'user_id' => $farmer->id,
            'role' => User::ROLE_FARMER,
            'item_1' => 5,
            'item_2' => 1,
            'item_3' => 5,
            'item_4' => 1,
            'item_5' => 5,
            'item_6' => 1,
            'item_7' => 5,
            'item_8' => 1,
            'item_9' => 5,
            'item_10' => 1,
            'comments' => 'Historical evaluation record test',
        ]);

        $this->assertDatabaseHas('usability_survey_responses', [
            'id' => $response->id,
            'comments' => 'Historical evaluation record test',
        ]);
        $this->assertSame(100.0, $response->score());
    }
}
