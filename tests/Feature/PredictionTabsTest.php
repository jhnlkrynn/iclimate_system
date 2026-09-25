<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Predictions page: Rice Yield / Weather tabs, role-gated technical
 * details, and friendly failure text.
 */
class PredictionTabsTest extends TestCase
{
    use RefreshDatabase;

    private const TECHNICAL_TERMS = [
        'random forest', 'regression', 'feature values', 'model confidence', 'algorithm',
        'machine learning', 'api response', 'model parameters', 'dataset preprocessing',
        'traceback', 'stack trace', 'sqlstate', 'python',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        $this->skipMonthlyModel();
        Config::set('services.weather_api.enabled', true);
        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'time' => '2026-09-13T12:00', 'temperature_2m' => 29.4, 'relative_humidity_2m' => 83,
                    'precipitation' => 0.8, 'weather_code' => 61, 'wind_speed_10m' => 9, 'is_day' => 1,
                ],
                'daily' => [
                    'time' => ['2026-09-13'], 'weather_code' => [61], 'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28], 'precipitation_sum' => [4],
                    'precipitation_probability_max' => [60], 'wind_speed_10m_max' => [10],
                ],
            ]),
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'barangay' => 'Malaruhatan']);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Malaruhatan']);

        return $user;
    }

    private function completedYieldRun(User $user, array $mlOverrides = []): AnalyticsRun
    {
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        RiceProduction::factory()->create([
            'barangay' => 'Malaruhatan',
            'year' => (int) Carbon::parse($futureDate)->subYear()->format('Y'),
            'yield_per_hectare' => 4.4,
        ]);
        $weather = [
            'ready' => true,
            'message' => 'Model says Random Forest regression ready.',
            'source_name' => 'iClimate monthly Random Forest model',
            'predictions' => ['rainfall' => 150, 'temperature' => 29, 'humidity' => 80, 'wind_speed' => 8, 'season' => 'Wet'],
            'confidence' => ['value' => 82, 'label' => 'High confidence'],
        ];

        return AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate, 'area' => 2.5],
            'result' => [
                'weather_result' => $weather,
                'ml_result' => $mlOverrides + [
                    'predicted_yield' => 4.2,
                    'unit' => 'tons/hectare',
                    'farm_area_hectares' => 2.5,
                    'estimated_total_production_tons' => 10.5,
                    'model_input' => ['rainfall' => 150, 'temp_avg' => 29],
                    'features' => ['Rainfall' => 150],
                    'risk_level' => 'Low',
                    'planting_advisory' => 'Plant within the recommended window.',
                    'irrigation_recommendation' => 'Maintain normal irrigation.',
                    'source' => 'iClimate Python model',
                    'api_confidence' => 88,
                ],
            ],
            'completed_at' => now(),
        ]);
    }

    private function panel(string $html, string $name): string
    {
        $start = strpos($html, 'id="'.$name.'-panel"');
        $this->assertNotFalse($start, "Missing $name panel");
        $order = ['rice-yield-panel', 'weather-forecast-panel', 'yieldConfirmModal'];
        $key = $name.'-panel';
        $next = $order[array_search($key, $order, true) + 1];
        $end = strpos($html, 'id="'.$next.'"', $start);

        return substr($html, $start, $end - $start);
    }

    public static function roles(): array
    {
        return [[User::ROLE_FARMER], [User::ROLE_MAO]];
    }

    #[DataProvider('roles')]
    public function test_tabs_are_accessible_and_titled_predictions(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertSee('Predictions')
            ->assertSee('role="tablist"', false)
            ->assertSee('role="tab"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('>Rice Yield</button>', false)
            ->assertSee('>Weather</button>', false);
    }

    #[DataProvider('roles')]
    public function test_rice_yield_tab_has_no_weather_content_and_weather_tab_has_no_yield_content(string $role): void
    {
        $user = $this->userWithRole($role);
        $run = $this->completedYieldRun($user);

        $html = $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->getContent();

        $rice = $this->panel($html, 'rice-yield');
        $this->assertStringContainsString('Expected Rice Yield', $rice);
        $this->assertStringContainsString('4.20', $rice);
        $this->assertStringContainsString('Previous Yield', $rice);
        $this->assertStringContainsString('0.2 tons/ha lower', $rice);
        $this->assertStringContainsString('About the Same', $rice);
        foreach (['Expected Rainfall', 'Humidity', 'Temperature', 'Wind', 'Weather Now', 'Monthly Weather Calendar', 'Weather Check'] as $weatherWord) {
            $this->assertStringNotContainsString($weatherWord, $rice, "Rice tab leaked: $weatherWord");
        }

        $weather = $this->panel($html, 'weather-forecast');
        $this->assertStringContainsString('Expected Weather', $weather);
        $this->assertStringContainsString('Expected Rainfall', $weather);
        foreach (['Expected Rice Yield', 'Previous Yield', 'tons/ha', 'Estimate My Harvest', 'Farm Size'] as $yieldWord) {
            $this->assertStringNotContainsString($yieldWord, $weather, "Weather tab leaked: $yieldWord");
        }
    }

    #[DataProvider('roles')]
    public function test_technical_terms_and_model_details_are_hidden_from_farmer_and_mao(string $role): void
    {
        $user = $this->userWithRole($role);
        $run = $this->completedYieldRun($user);

        $html = strtolower($this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->getContent());

        $body = preg_replace('#<script.*?</script>|<style.*?</style>#s', '', $html);
        foreach (self::TECHNICAL_TERMS as $term) {
            $this->assertStringNotContainsString($term, $body, "Technical term shown: $term");
        }
        $this->assertStringNotContainsString('open-meteo', $body);
        $this->assertStringNotContainsString('technical details', $body);
        $this->assertStringContainsString('prediction reliability', $body);
        $this->assertStringNotContainsString('100% accurate', $body);
    }

    public function test_it_expert_still_sees_technical_details(): void
    {
        $user = $this->userWithRole(User::ROLE_IT_EXPERT);
        $run = $this->completedYieldRun($user);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('Technical Details (IT only)')
            ->assertSee('iClimate Python model');
    }

    public function test_tab_query_param_is_honoured_and_weather_alias_works(): void
    {
        $user = $this->userWithRole(User::ROLE_FARMER);

        $this->actingAs($user)->get(route('weather-predictions.index', ['tab' => 'rice-yield']))
            ->assertSee('data-initial-weather-tab="rice-yield"', false);
        $this->actingAs($user)->get(route('weather-predictions.index', ['tab' => 'weather']))
            ->assertSee('data-initial-weather-tab="weather-forecast"', false);
    }

    #[DataProvider('roles')]
    public function test_failed_run_shows_friendly_text_without_internal_details(string $role): void
    {
        $user = $this->userWithRole($role);
        $run = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_FAILED,
            'requested_by' => $user->id,
            'input' => ['target_date' => Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d'), 'area' => 2],
            'error' => 'Traceback (most recent call last): File "/srv/py/x.py" SQLSTATE[HY000] python exploded',
            'completed_at' => now(),
        ]);

        $html = $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('Forecast Temporarily Unavailable')
            ->getContent();

        foreach (['Traceback', 'SQLSTATE', 'x.py', 'exploded'] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }

        $this->actingAs($user)->getJson(route('analytics-runs.show', $run))
            ->assertJsonPath('error', 'Forecast Temporarily Unavailable');
    }

    public function test_it_expert_can_still_see_raw_failure_text(): void
    {
        $user = $this->userWithRole(User::ROLE_IT_EXPERT);
        $run = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_FAILED,
            'requested_by' => $user->id,
            'input' => ['target_date' => Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d'), 'area' => 2],
            'error' => 'boom-internal-detail',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertSee('boom-internal-detail');
    }

    public function test_weather_not_ready_result_says_not_enough_data(): void
    {
        $user = $this->userWithRole(User::ROLE_FARMER);
        $run = AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d')],
            'result' => ['ready' => false, 'message' => 'Python model needs 12 rows', 'months_available' => 3],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertSee('Not Enough Data')
            ->assertDontSee('Python model needs 12 rows');
    }

    /**
     * The page reads the monthly forecast through PredictionOrchestrator, which
     * would otherwise train the monthly model inline (minutes) when nothing is
     * persisted. These tests are about page structure, so simulate "no monthly
     * summary available" exactly like the existing orchestrator-failure test.
     */
    private function skipMonthlyModel(): void
    {
        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('monthlyWeatherForecast')->andThrow(new \RuntimeException('skipped in test'));
        });
    }
}
