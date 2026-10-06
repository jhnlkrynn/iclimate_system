<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaoOptionalDataFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            '*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);
        config([
            'iclimate.python_binary' => '/nonexistent/python',
            'services.groq.enabled' => false,
        ]);
    }

    private function maoUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function test_weather_api_failure_does_not_crash_mao_dashboard_or_endpoints(): void
    {
        $user = $this->maoUser();

        $this->actingAs($user)->get('/mao/dashboard')->assertOk();
        $this->actingAs($user)->get('/mao/operations')->assertOk();

        $weatherJson = $this->actingAs($user)->getJson('/mao/dashboard/weather')->assertOk()->json();
        $this->assertFalse($weatherJson['ok']);

        $lianWeatherJson = $this->actingAs($user)->getJson('/api/weather/lian-barangays')->assertOk()->json();
        $this->assertIsArray($lianWeatherJson['data']);
    }

    public function test_pagasa_failure_differentiates_unavailable_from_zero_advisories(): void
    {
        $user = $this->maoUser();

        // Simulate PAGASA service unavailable status in cache
        Cache::put('pagasa-advisories:status', [
            'ok' => false,
            'sources_checked' => 3,
            'errors' => ['timeout', '503 error', 'connection refused'],
            'checked_at' => now()->toISOString(),
        ], 300);

        $response = $this->actingAs($user)->get('/planting-advisories')->assertOk();
        $content = (string) $response->getContent();

        // Advisories list (2026-10): one status line says the source is unreachable; the page never says "none right now".
        $this->assertStringContainsString('data-adv-check="unavailable"', $content);
        $this->assertStringContainsString('Advisory information is temporarily unavailable.', $content);
        $this->assertStringNotContainsString('data-adv-empty', $content);
        $this->assertStringNotContainsString('No planting advisory right now.', $content);
    }

    public function test_heat_map_and_risk_endpoints_render_with_placeholder_or_empty_risk_data(): void
    {
        $user = $this->maoUser();

        // Ensure HeatmapArea has only seed placeholders or no computed records
        HeatmapArea::query()->delete();
        HeatmapArea::query()->create([
            'barangay' => 'Binubusan',
            'risk_level' => 'Low',
            'risk_type' => 'Drought',
            'risk_score' => 0.0,
            'is_seed' => true,
        ]);

        $this->actingAs($user)->get('/heat-map')->assertOk();
        $this->actingAs($user)->getJson('/api/lian/risk-map')->assertOk();
        $this->actingAs($user)->getJson('/api/lian/risk-forecast')->assertOk();
    }

    public function test_predictions_page_returns_200_when_python_and_ml_are_unavailable(): void
    {
        $user = $this->maoUser();

        $response = $this->actingAs($user)->get('/weather-predictions')->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringContainsString('Prediction', $content);
    }

    public function test_empty_database_does_not_crash_any_mao_page(): void
    {
        $user = $this->maoUser();

        $routes = [
            '/mao/dashboard',
            '/mao/operations',
            '/calendar',
            '/climate-records',
            '/planting-advisories',
            '/farmer-profiles',
            '/messages',
            '/notifications',
            '/rice-productions',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)->get($route)->assertOk();
        }
    }

    public function test_incomplete_farmer_profile_does_not_crash_list_or_detail_view(): void
    {
        $user = $this->maoUser();
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'name' => 'Incomplete Farmer',
            'barangay' => 'Binubusan',
        ]);

        $profile = FarmerProfile::query()->create([
            'user_id' => $farmer->id,
            'full_name' => 'Incomplete Farmer',
            'contact_number' => null,
            'address' => null,
            'barangay' => 'Binubusan',
            'farm_area' => null,
            'farm_type' => 'Rainfed',
            'farmer_organization_id' => null,
        ]);

        $list = $this->actingAs($user)->get('/farmer-profiles')->assertOk();
        $this->assertStringContainsString('Incomplete Farmer', (string) $list->getContent());

        $detail = $this->actingAs($user)->get("/farmer-profiles/{$profile->id}")->assertOk();
        $detailContent = (string) $detail->getContent();
        $this->assertStringContainsString('Incomplete Farmer', $detailContent);
        $this->assertStringContainsString('Not set', $detailContent);
    }

    public function test_hostile_array_query_parameters_do_not_throw_500(): void
    {
        $user = $this->maoUser();

        $queries = [
            '/mao/operations?schedule_season[]=foo&schedule_barangay[]=bar&schedule_status[]=baz&fb_barangay[]=luyahan',
            '/planting-advisories?search[]=test&type[]=planting&status[]=published&target_barangay[]=Binubusan',
            '/farmer-profiles?search[]=john&barangay[]=Binubusan&farm_type[]=Rainfed',
            '/calendar?year[]=2026&month[]=10',
            '/calendar?year=-999&month=99',
            '/messages/search?q[]=alice',
            '/ai-admin/topics?q[]=rice',
            '/notifications?search[]=alert&type[]=Warning&filter[]=unread',
        ];

        foreach ($queries as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_error_pages_link_back_to_mao_dashboard_for_mao_user(): void
    {
        $user = $this->maoUser();

        $view500 = $this->actingAs($user)->view('errors.500');
        $view500->assertSee(route('mao.dashboard'));
        $view500->assertSee('Back to Dashboard');

        $view503 = $this->actingAs($user)->view('errors.503');
        $view503->assertSee(route('mao.dashboard'));
        $view503->assertSee('Back to Dashboard');
    }
}
