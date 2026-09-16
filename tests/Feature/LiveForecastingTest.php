<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveForecastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_forecasting_link_is_removed_from_all_user_role_sidebars_and_dashboards(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)
            ->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertDontSee('Live Forecasting')
            ->assertDontSee('live-forecasting');

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('Live Forecasting')
            ->assertDontSee('live-forecasting');

        $this->actingAs($itExpert)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Live Forecasting')
            ->assertDontSee('live-forecasting');
    }

    public function test_visiting_old_live_forecasting_route_redirects_farmer_to_farmer_dashboard(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get('/live-forecasting')
            ->assertRedirect(route('farmer.dashboard'));

        $this->actingAs($farmer)
            ->get('/live-forecasting/details')
            ->assertRedirect(route('farmer.dashboard'));
    }

    public function test_visiting_old_live_forecasting_route_redirects_mao_to_mao_dashboard(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get('/live-forecasting')
            ->assertRedirect(route('mao.dashboard'));

        $this->actingAs($mao)
            ->get('/live-forecasting/details')
            ->assertRedirect(route('mao.dashboard'));
    }

    public function test_visiting_old_live_forecasting_route_redirects_it_expert_to_admin_dashboard(): void
    {
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($itExpert)
            ->get('/live-forecasting')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($itExpert)
            ->get('/live-forecasting/details')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_unauthenticated_request_to_live_forecasting_redirects_to_login(): void
    {
        $this->get('/live-forecasting')
            ->assertRedirect(route('login'));
    }

    public function test_weather_prediction_and_heatmap_areas_remain_fully_functional(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('weather-predictions.index'))
            ->assertOk();

        $this->actingAs($mao)
            ->get(route('heatmap-areas.index'))
            ->assertOk();
    }
}
