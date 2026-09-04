<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\User;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\WeatherPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherPredictionTest extends TestCase
{
    use RefreshDatabase;

    private function seedClimateRecords(): void
    {
        foreach (range(1, 6) as $month) {
            ClimateRecord::query()->create([
                'record_date' => sprintf('2026-%02d-15', $month),
                'rainfall' => 80 + ($month * 8),
                'temperature' => 27 + ($month * 0.2),
                'humidity' => 70 + $month,
                'wind_speed' => 8 + ($month * 0.3),
                'season' => $month >= 5 ? ClimateRecord::SEASON_WET : ClimateRecord::SEASON_DRY,
                'source' => 'PAGASA',
            ]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.farming_ai.url', 'http://farming-ai.test');
        Http::fake([
            'farming-ai.test/predict' => Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 4.21, 'unit' => 'tons/hectare'],
                'confidence_score' => 88,
            ]),
        ]);
    }

    public function test_authenticated_user_can_view_monthly_weather_prediction(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $futureMonth = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m');

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth]))
            ->assertOk()
            ->assertSee('Weather Prediction')
            ->assertSee('Random Forest')
            ->assertSee('Rainfall');
    }

    public function test_weather_prediction_route_uses_weather_service_not_yield_service(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->mock(WeatherPredictionService::class, function ($mock) {
            $mock->shouldReceive('predict')
                ->once()
                ->andReturn([
                    'ready' => true,
                    'message' => 'Prediction generated using monthly climate history and Random Forest regression.',
                    'source_name' => 'iClimate monthly Random Forest model',
                    'source_note' => 'Model output trained from saved monthly climate records.',
                    'months_available' => 6,
                    'target_month' => Carbon::now()->addMonthsNoOverflow(2)->startOfMonth(),
                    'predictions' => [
                        'rainfall' => 150,
                        'temperature' => 29,
                        'humidity' => 80,
                        'wind_speed' => 8,
                        'season' => 'Wet',
                    ],
                    'confidence' => ['value' => 80, 'label' => 'High confidence', 'note' => 'Test'],
                    'insights' => [],
                    'model_input' => [
                        'rainfall' => 150,
                        'temp_avg' => 29,
                        'humidity' => 80,
                        'wind_speed' => 8,
                        'season' => 'Wet',
                    ],
                ]);
        });

        $this->mock(RiceYieldPredictionService::class, function ($mock) {
            $mock->shouldNotReceive('predictForUser');
        });

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => $futureDate,
            ])
            ->assertOk()
            ->assertSee('Forecast Summary')
            ->assertDontSee('Rice Yield Prediction Result');
    }

    public function test_authenticated_user_can_predict_rice_yield(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
                'area' => 2.5,
            ])
            ->assertOk()
            ->assertSee('Rice Yield Prediction Result')
            ->assertSee('Estimated Rice Yield')
            ->assertSee('2.50 hectares')
            ->assertSee('10.53 tons')
            ->assertDontSee('Prediction error:');

        $this->assertSame('rice_yield', $response->viewData('mlResult')['prediction_type']);
        $this->assertSame(2.5, $response->viewData('mlResult')['farm_area_hectares']);
    }

    public function test_rice_yield_result_contains_no_blank_required_summary_fields(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $response = $this->actingAs($user)
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
                'area' => 2,
            ]);

        $response->assertOk()
            ->assertSee('Farm Area Used')
            ->assertSee('2.00 hectares')
            ->assertSee('Estimated Total Production')
            ->assertSee('Risk Level')
            ->assertSee('Condition Score');

        $result = $response->viewData('mlResult');

        foreach ([
            'yield_tons_per_hectare',
            'farm_area_hectares',
            'estimated_total_production_tons',
            'risk_level',
            'condition_score',
            'planting_advisory',
            'irrigation_recommendation',
        ] as $field) {
            $this->assertArrayHasKey($field, $result);
            $this->assertNotNull($result[$field], "{$field} should not be null.");
            $this->assertNotSame('', $result[$field], "{$field} should not be blank.");
        }

        $this->assertIsNumeric($result['yield_tons_per_hectare']);
        $this->assertIsNumeric($result['farm_area_hectares']);
        $this->assertIsNumeric($result['estimated_total_production_tons']);
        $this->assertIsNumeric($result['condition_score']);
        $this->assertEqualsWithDelta(
            $result['yield_tons_per_hectare'] * $result['farm_area_hectares'],
            $result['estimated_total_production_tons'],
            0.01
        );
    }

    public function test_rice_yield_prediction_rejects_unrealistic_inputs(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($user)
            ->from(route('weather-predictions.index'))
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => 'not-a-date',
                'farm_type' => 'Rainfed',
                'area' => 2.5,
            ])
            ->assertRedirect(route('weather-predictions.index'))
            ->assertSessionHasErrors([
                'prediction_date',
            ]);
    }

    public function test_rice_yield_prediction_requires_valid_area(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        foreach ([null, 0, -2, 'abc'] as $area) {
            $this->actingAs($user)
                ->from(route('weather-predictions.index'))
                ->post(route('rice-yield-predictions.predict'), [
                    'prediction_type' => 'rice_yield',
                    'prediction_date' => $futureDate,
                    'farm_type' => 'Rainfed',
                    'area' => $area,
                ])
                ->assertRedirect(route('weather-predictions.index'))
                ->assertSessionHasErrors(['area']);
        }
    }

    public function test_invalid_prediction_type_is_rejected(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->actingAs($user)
            ->from(route('weather-predictions.index'))
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => $futureDate,
            ])
            ->assertRedirect(route('weather-predictions.index'))
            ->assertSessionHasErrors(['prediction_type']);

        $this->actingAs($user)
            ->from(route('weather-predictions.index'))
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
                'area' => 2.5,
            ])
            ->assertRedirect(route('weather-predictions.index'))
            ->assertSessionHasErrors(['prediction_type']);
    }

    public function test_rice_yield_prediction_rejects_todays_date(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertOk()
            ->assertSee(PredictionDateValidator::ERROR_MESSAGE)
            ->assertDontSee('Rice Yield Prediction Result');
    }

    public function test_rice_yield_prediction_rejects_past_date(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => '2020-01-01',
            ])
            ->assertOk()
            ->assertSee(PredictionDateValidator::ERROR_MESSAGE)
            ->assertDontSee('Rice Yield Prediction Result');
    }
}
