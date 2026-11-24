<?php

namespace Tests\Feature;

use App\Models\MaoUserBarangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastMovedToMaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_is_redirected_from_forecast_url_and_tab_is_hidden_from_nav(): void
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            'barangay' => 'Binubusan',
        ]);

        // Hitting the old URL redirects to the farmer dashboard (303, never 403/404)
        $this->actingAs($farmer)
            ->get(route('weather-predictions.index'))
            ->assertRedirect(route('farmer.dashboard'));

        // Farmer dashboard still renders weather/day-by-day cards and does not show the Forecast nav tab
        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $html = $dashboard->getContent();
        $this->assertStringNotContainsString('href="'.route('weather-predictions.index').'"', $html);
    }

    public function test_mao_staff_and_head_can_open_forecast_see_it_in_nav_and_respect_mao_scope(): void
    {
        $maoStaff = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Agricultural Technologist',
        ]);
        MaoUserBarangay::query()->create(['user_id' => $maoStaff->id, 'barangay' => 'Binubusan']);
        MaoUserBarangay::query()->create(['user_id' => $maoStaff->id, 'barangay' => 'Malaruhatan']);
        User::forgetMaoPersonnel();

        $staffPage = $this->actingAs($maoStaff)->get(route('weather-predictions.index'));
        $staffPage->assertOk();
        $staffPage->assertSee('Forecast');
        $staffPage->assertSee('Binubusan');
        $staffPage->assertSee('Malaruhatan');
        $staffPage->assertDontSee('<option value="Cumba">', false);
        $staffPage->assertSee('href="'.route('weather-predictions.index').'"', false);

        $maoHead = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        $headPage = $this->actingAs($maoHead)->get(route('weather-predictions.index'));
        $headPage->assertOk();
        $headPage->assertSee('Forecast');
        $headPage->assertSee('Cumba');
        $headPage->assertSee('Binubusan');
    }
}
