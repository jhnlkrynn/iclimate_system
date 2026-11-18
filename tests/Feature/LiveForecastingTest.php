<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveForecastingTest extends TestCase
{
    use RefreshDatabase;



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



    public function test_unauthenticated_request_to_live_forecasting_redirects_to_login(): void
    {
        $this->get('/live-forecasting')
            ->assertRedirect(route('login'));
    }

    public function test_weather_prediction_remains_fully_functional(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('weather-predictions.index'))
            ->assertOk();
    }
}
