<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When the weather provider is down or the scheduler has not run yet, pages show the last saved data with an
 * "Updated: <time>" label, or "Walang datos pa" when nothing is saved at all — never an error or a "try again".
 */
class SavedDataStatesTest extends TestCase
{
    use RefreshDatabase;

    private function visible(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#s', '', $html);
        // Hidden elements (a Refresh button's failure banner, closed pop-ups) are not what the page shows.
        $html = (string) preg_replace('#<(div|p|section)[^>]*\shidden[^>]*>.*?</\1>#s', '', $html);

        return strtolower((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));
    }

    public function test_with_nothing_saved_and_the_provider_down_pages_say_no_data_yet(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $farmer = User::factory()->farmer()->create(['barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Binubusan']);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        foreach ([[$mao, '/mao/dashboard'], [$mao, '/weather-predictions'], [$farmer, '/weather-predictions'], [$farmer, '/planting-advisories']] as [$user, $path]) {
            $text = $this->visible($this->actingAs($user)->get($path)->assertOk()->getContent());

            $this->assertStringContainsString('walang datos pa', $text, $path);
            foreach (['weather is temporarily unavailable', 'forecast is not available right now', 'please try again later'] as $error) {
                $this->assertStringNotContainsString($error, $text, "{$path}: {$error}");
            }
        }
    }

    public function test_saved_weather_is_shown_with_its_updated_time(): void
    {
        config(['services.weather_api.enabled' => true]);
        // Made first: the scheduled refresh stores a forecast for every barangay an account has.
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => now('Asia/Manila')->format('Y-m-d\TH:00'), 'temperature_2m' => 29.4, 'relative_humidity_2m' => 80, 'precipitation' => 0, 'weather_code' => 2, 'wind_speed_10m' => 9, 'is_day' => 1],
            'daily' => ['time' => [now('Asia/Manila')->toDateString()], 'weather_code' => [2], 'temperature_2m_max' => [31], 'temperature_2m_min' => [25],
                'precipitation_sum' => [0], 'precipitation_probability_max' => [10], 'wind_speed_10m_max' => [10]],
        ])]);
        $this->artisan('iclimate:refresh-weather-cache');
        Http::fake(['*' => Http::response([], 500)]);                                    // the provider goes down afterwards

        foreach (['/mao/dashboard', '/weather-predictions'] as $path) {
            $html = $this->actingAs($mao)->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/class="[^"]*ic-updated[^"]*"[^>]*>Updated: /', $html, $path);
        }
    }
}
