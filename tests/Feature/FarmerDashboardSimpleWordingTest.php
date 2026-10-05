<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FarmerDashboardSimpleWordingTest extends TestCase
{
    use RefreshDatabase;

    private function visibleText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    }

    private function fakeWeather(): void
    {
        Cache::flush();
        Http::fake(['api.open-meteo.com/*' => fn () => Http::response([
            'current' => [
                'time' => now()->toIso8601String(),
                'temperature_2m' => 28,
                'relative_humidity_2m' => 70,
                'precipitation' => 0,
                'wind_speed_10m' => 10,
                'weather_code' => 0,
                'is_day' => 1,
            ],
            'hourly' => [
                'precipitation' => array_fill(0, 24, 0),
                'precipitation_probability' => array_fill(0, 24, 50),
            ],
            'daily' => [
                'precipitation_sum' => [0],
                'precipitation_probability_max' => [50],
            ],
        ], 200)]);
    }

    private function farmer(?string $barangay = 'Bagong Pook'): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        if ($barangay !== null) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);
        }

        return $user;
    }

    public function test_farmer_dashboard_shows_no_technical_terms(): void
    {
        $this->fakeWeather();

        $response = $this->actingAs($this->farmer())->get(route('farmer.dashboard'));
        $response->assertOk();
        $text = $this->visibleText($response->getContent());

        // The weather provider is named on purpose ("Source: Open-Meteo"), so farmers know where the reading comes from.
        foreach (['Random Forest', 'Python', 'Confidence', 'Prediction tool', 'Historical Production Comparison', 'Agricultural Risk Classification', 'model output', 'Prediction unavailable', 'insufficient input'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $text, "Farmer dashboard should not show \"{$term}\".");
        }

        foreach (['My Farm Today', "Today's Highlights", 'Weather for Lian, Batangas', 'My Farm Schedule', 'Quick Actions'] as $heading) {
            $this->assertStringContainsString($heading, $text);
        }
        $response->assertSee(route('weather-predictions.index', ['tab' => 'weather-forecast']), false);
        foreach (['Upcoming Weather', 'Farm Status Summary', 'Recommended Action', 'More for Your Farm', 'My Farm Status', 'My Farm at a Glance', 'What You Should Know Today', 'Weather This Week'] as $old) {
            $this->assertStringNotContainsString($old, $text);              // the retired section names
        }
    }

    public function test_risk_panel_keeps_planning_guide_note(): void
    {
        $this->fakeWeather();

        $response = $this->actingAs($this->farmer())->get(route('farmer.dashboard'));
        $response->assertOk();

        $this->assertStringContainsString(
            'planning guide',
            $this->visibleText($response->getContent())
        );
    }

    public function test_no_data_risk_is_never_shown_as_low(): void
    {
        // No live weather available -> the barangay has no rating.
        Cache::flush();
        Http::fake(['api.open-meteo.com/*' => fn () => Http::response([], 500)]);

        $response = $this->actingAs($this->farmer())->get(route('farmer.dashboard'));
        $response->assertOk();
        $text = $this->visibleText($response->getContent());

        // With no rating the risk card says so (and offers a retry): an unrated barangay is never presented as Low.
        $this->assertStringContainsString('We do not have a risk rating for your barangay yet.', $text);
        $this->assertStringNotContainsString('Low Farm Risk', $text);
        $this->assertDoesNotMatchRegularExpression('/(?<!not )Low risk/', $text);   // the map legend's "not Low risk" note is fine
    }

    public function test_dashboard_renders_with_and_without_barangay(): void
    {
        $this->fakeWeather();

        $this->actingAs($this->farmer('Bagong Pook'))->get(route('farmer.dashboard'))->assertOk();
        $this->actingAs($this->farmer(null))->get(route('farmer.dashboard'))->assertOk();
    }
}
