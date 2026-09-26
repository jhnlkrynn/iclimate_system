<?php

namespace Tests\Feature;

use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

        Cache::clear();
        Config::set('services.farming_ai.url', 'http://farming-ai.test');
        Config::set('services.weather_api.enabled', true);
        Http::fake([
            'farming-ai.test/predict' => Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 4.21, 'unit' => 'tons/hectare'],
                'confidence_score' => 88,
            ]),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => [
                    'time' => '2026-09-13T12:00',
                    'temperature_2m' => 29.4,
                    'relative_humidity_2m' => 83,
                    'precipitation' => 0.8,
                    'weather_code' => 61,
                    'wind_speed_10m' => 9,
                    'is_day' => 1,
                ],
                'daily' => [
                    'time' => ['2026-09-13'],
                    'weather_code' => [61],
                    'temperature_2m_max' => [30],
                    'temperature_2m_min' => [28],
                    'precipitation_sum' => [4],
                    'precipitation_probability_max' => [60],
                    'wind_speed_10m_max' => [10],
                ],
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
            ->assertSee('Predictions')
            ->assertSee('Weather Now')
            ->assertSee('Monthly Weather Calendar')
            ->assertSee('Daily forecast values:')
            // Source/cache details are IT-only now (MAO is a non-technical role).
            ->assertDontSee('Open-Meteo Forecast API')
            ->assertDontSee('Open-Meteo responses are cached')
            ->assertSee('Light Rain')
            ->assertDontSee('PAGASA Official Weather Context')
            ->assertDontSee('Machine Learning Prediction')
            ->assertDontSee('Ready to forecast weather')
            ->assertDontSee('Predict Weather Result')
            ->assertSee('Rice Yield')
            ->assertSee('data-initial-weather-tab="weather-forecast"', false)
            ->assertSee('id="weather-forecast-panel"', false)
            ->assertSee('id="rice-yield-panel"', false);
    }

    public function test_user_without_barangay_gets_cached_lian_municipal_preview_from_real_api_values(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MAO,
            'barangay' => null,
        ]);

        $first = $this->actingAs($user)->get(route('weather-predictions.index'));

        $first->assertOk()
            ->assertSee('Lian, Batangas municipal preview')
            ->assertSee('29.4')
            ->assertSee('Light Rain')
            ->assertDontSee('Add a Lian barangay to your profile');

        // Uses the one shared Lian location the dashboards use.
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.open-meteo.com/v1/forecast')
                && (float) $request['latitude'] === (float) config('services.weather.latitude')
                && (float) $request['longitude'] === (float) config('services.weather.longitude');
        });

        // Forecast + shared current reading on the first load; fully cached after.
        Http::assertSentCount(2);
        $this->actingAs($user)->get(route('weather-predictions.index'))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_user_without_barangay_can_still_queue_weather_ml_prediction(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'role' => User::ROLE_MAO,
            'barangay' => null,
        ]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => $futureDate,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame('weather_prediction', $run->type);
        $this->assertSame('Lian, Batangas municipal preview', data_get($run->input, 'location'));
        $this->assertNull(data_get($run->input, 'barangay'));
        $this->assertSame('Open-Meteo', data_get($run->input, 'live_weather.source'));
        Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);
    }

    public function test_calendar_uses_municipal_location_real_dates_and_explicit_missing_data_cells(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'barangay' => 'Malaruhatan']);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['calendar_month' => '2026-09']))
            ->assertOk()
            ->assertSee('Forecast for')
            ->assertSee('Lian, Batangas municipal preview')
            ->assertSee('September 2026')
            ->assertSeeInOrder(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])
            ->assertSee('Sunday, September 13, 2026')
            ->assertSee('Light Rain')
            ->assertSee('Rainfall:')
            ->assertSee('4.0 mm')
            ->assertSee('28.0° / 30.0°C')
            ->assertSee('Moderate')
            ->assertSee('No forecast data')
            ->assertSee('calendar_month=2026-08', false)
            ->assertSee('calendar_month=2026-10', false)
            ->assertDontSee('Official context:')
            ->assertDontSee('PAGASA Official Weather Context');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.open-meteo.com/v1/forecast')
                && (float) $request['latitude'] === (float) config('services.weather.latitude')
                && (float) $request['longitude'] === (float) config('services.weather.longitude');
        });
    }

    public function test_barangay_reading_is_shown_beside_the_lian_wide_dashboard_reading(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Matabungkay']);

        $this->actingAs($user)
            ->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertSee('Weather Now in Matabungkay')
            ->assertSee('(your barangay)')
            ->assertSee('Lian-wide (town center)')
            ->assertSee('same reading as your Dashboard');
    }

    public function test_initial_weather_tab_resolves_safely_for_missing_invalid_and_valid_tab_values(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureMonth = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m');

        // Missing `tab` query param: must default to weather-forecast, never null/blank.
        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth]))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="weather-forecast"', false);

        // Invalid/unexpected `tab` value: must fall back to weather-forecast rather than
        // rendering the raw invalid string or leaving the attribute empty.
        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth, 'tab' => 'not-a-real-tab']))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="weather-forecast"', false)
            ->assertDontSee('data-initial-weather-tab="not-a-real-tab"', false);

        // Explicit valid `tab=rice-yield` must be honored.
        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth, 'tab' => 'rice-yield']))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="rice-yield"', false);

        // Explicit valid `tab=weather-forecast` must be honored.
        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['target_month' => $futureMonth, 'tab' => 'weather-forecast']))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="weather-forecast"', false);
    }

    public function test_completed_weather_run_renders_prediction_results(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        $run = AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate],
            'result' => [
                'ready' => true,
                'message' => 'Python weather result is ready.',
                'months_available' => 8,
                'predictions' => [
                    'rainfall' => 142.5,
                    'temperature' => 28.4,
                    'humidity' => 79.0,
                    'wind_speed' => 9.2,
                    'season' => 'Wet',
                ],
                'confidence' => ['value' => 82, 'label' => 'High confidence', 'note' => 'Enough history.'],
                'source_name' => 'Python weather model',
            ],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('Expected Weather')
            ->assertSee('Prediction saved')
            ->assertSee('142.5 mm')
            ->assertSee('Moderate Rain')
            // The model's own status message is technical and no longer shown to MAO.
            ->assertDontSee('Python weather result is ready.');
    }

    public function test_pending_and_failed_runs_render_visible_states_on_the_correct_tab(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        $pending = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_PENDING,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate, 'area' => 2.5],
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $pending->uuid]))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="rice-yield"', false)
            ->assertSee('Estimating your harvest');

        $pending->update([
            'status' => AnalyticsRun::STATUS_FAILED,
            'error' => 'The Python analytics service could not be reached.',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $pending->uuid]))
            ->assertOk()
            ->assertSee('Forecast Temporarily Unavailable')
            ->assertDontSee('The Python analytics service could not be reached.');
    }

    public function test_completed_python_rice_yield_run_renders_without_missing_state_keys(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        $weather = [
            'ready' => true,
            'message' => 'Prediction generated by Python.',
            'predictions' => ['rainfall' => 150, 'temperature' => 29, 'humidity' => 80, 'wind_speed' => 8, 'season' => 'Wet'],
        ];
        $run = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate, 'area' => 2.5],
            'result' => [
                'weather_result' => $weather,
                'ml_result' => [
                    'predicted_yield' => 4.2,
                    'unit' => 'tons/hectare',
                    'farm_area_hectares' => 2.5,
                    'estimated_total_production_tons' => 10.5,
                    'model_input' => ['rainfall' => 150, 'temp_avg' => 29, 'humidity' => 80, 'wind_speed' => 8, 'season' => 'Wet'],
                    'risk_level' => 'Low',
                    'risk_label' => 'Low Risk',
                    'condition_score' => 88,
                    'condition_score_label' => 'High confidence',
                    'planting_advisory' => 'Plant within the recommended window.',
                    'irrigation_recommendation' => 'Maintain normal irrigation.',
                    'weather_assessment' => 'Favorable',
                    'source' => 'iClimate Python model',
                ],
            ],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('data-initial-weather-tab="rice-yield"', false)
            ->assertSee('Expected Rice Yield')
            ->assertSee('4.20')
            ->assertSee('10.50');
    }

    public function test_rice_yield_result_shows_short_term_outlook_and_last_year_comparison(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'barangay' => 'Malaruhatan']);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        $lastYear = (int) Carbon::parse($futureDate)->subYear()->format('Y');

        RiceProduction::factory()->create([
            'barangay' => 'Malaruhatan',
            'year' => $lastYear,
            'yield_per_hectare' => 5.0,
        ]);

        $weather = [
            'ready' => true,
            'message' => 'Prediction generated by Python.',
            'predictions' => ['rainfall' => 150, 'temperature' => 29, 'humidity' => 80, 'wind_speed' => 8, 'season' => 'Wet'],
            'model_input' => [
                'rainfall' => 150, 'temp_avg' => 29, 'humidity' => 80, 'wind_speed' => 8,
                'previous_rainfall' => 90, 'previous_temp' => 27, 'previous_humidity' => 70, 'previous_wind_speed' => 6,
            ],
        ];
        $run = AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate, 'area' => 2.5],
            'result' => [
                'weather_result' => $weather,
                'ml_result' => [
                    'predicted_yield' => 4.0,
                    'unit' => 'tons/hectare',
                    'farm_area_hectares' => 2.5,
                    'estimated_total_production_tons' => 10.0,
                    'model_input' => $weather['model_input'],
                    'risk_level' => 'Low',
                    'risk_label' => 'Low Risk',
                    'condition_score' => 88,
                    'condition_score_label' => 'High confidence',
                    'planting_advisory' => 'Plant within the recommended window.',
                    'irrigation_recommendation' => 'Maintain normal irrigation.',
                    'weather_assessment' => 'Favorable',
                    'source' => 'iClimate Python model',
                ],
            ],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('Short-Term Planning Outlook')
            ->assertSee('Previous Yield')
            ->assertSee((string) $lastYear)
            ->assertSee('5.00 tons/ha')
            ->assertSee('1.0 tons/ha lower')
            ->assertSee('Lower');
    }

    public function test_rice_yield_form_shows_visible_farm_type_selector(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'barangay' => 'Malaruhatan']);

        $this->actingAs($user)
            ->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertSee('id="farm_type"', false)
            ->assertSee('<option value="Rainfed"', false)
            ->assertSee('<option value="Irrigated"', false)
            ->assertDontSee('type="hidden" name="farm_type"', false);
    }

    public function test_weather_prediction_route_uses_weather_service_not_yield_service(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'barangay' => 'Malaruhatan']);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        Queue::fake();

        $this->actingAs($user)
            ->post(route('weather-predictions.predict'), [
                'prediction_type' => 'weather',
                'prediction_date' => $futureDate,
            ])
            ->assertRedirect();

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame('weather_prediction', $run->type);
        $this->assertSame($futureDate, data_get($run->input, 'target_date'));
        $this->assertSame('Malaruhatan, Lian, Batangas', data_get($run->input, 'location'));
        $this->assertSame('Open-Meteo', data_get($run->input, 'live_weather.source'));
        $this->assertSame('Light Rain', data_get($run->input, 'live_weather.current_condition'));
        Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);
    }

    public function test_saved_weather_prediction_is_not_shown_without_its_run_link(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');
        AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate],
            'result' => ['ready' => true, 'predictions' => ['rainfall' => 111.11, 'temperature' => 28, 'humidity' => 80, 'wind_speed' => 8, 'season' => 'Wet']],
            'completed_at' => now()->subHour(),
        ]);
        AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => ['target_date' => $futureDate],
            'result' => ['ready' => true, 'message' => 'Latest saved result.', 'predictions' => ['rainfall' => 222.22, 'temperature' => 29, 'humidity' => 82, 'wind_speed' => 9, 'season' => 'Wet']],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertDontSee('Latest saved result.')
            ->assertDontSee('222.22')
            ->assertDontSee('111.11')
            ->assertDontSee('Predict Weather Result');
    }

    public function test_authenticated_user_can_predict_rice_yield(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();
        Queue::fake();

        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
                'area' => 2.5,
            ])
            ->assertRedirect();

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame('rice_yield_prediction', $run->type);
        $this->assertSame(2.5, data_get($run->input, 'area'));
        Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);
    }

    public function test_rice_yield_result_contains_no_blank_required_summary_fields(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->seedClimateRecords();
        Queue::fake();
        $futureDate = Carbon::now()->addMonthsNoOverflow(2)->format('Y-m-d');

        $this->actingAs($user)
            ->post(route('rice-yield-predictions.predict'), [
                'prediction_type' => 'rice_yield',
                'prediction_date' => $futureDate,
                'farm_type' => 'Rainfed',
                'area' => 2,
            ])
            ->assertRedirect();

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame('Rainfed', data_get($run->input, 'farm_type'));
        $this->assertSame(2.0, (float) data_get($run->input, 'area'));
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
            ->assertRedirect()
            ->assertSessionHasErrors(['prediction_date']);
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
            ->assertRedirect()
            ->assertSessionHasErrors(['prediction_date']);
    }

    /**
     * Regression coverage for the 2026-09 pre-generation work: a genuine
     * PredictionOrchestrator failure (not the model's own normal
     * "not enough history yet" result) must never fatal the whole
     * weather-predictions page — the calendar simply shows no Monthly
     * Prediction summary for that month, same as any other not-ready case.
     */
    public function test_orchestrator_failure_does_not_fatal_the_weather_predictions_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);

        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('monthlyWeatherForecast')
                ->andThrow(new \RuntimeException('Simulated orchestrator failure for test.'));
        });

        $this->actingAs($user)
            ->get(route('weather-predictions.index'))
            ->assertOk()
            ->assertSee('Monthly Weather Calendar')
            ->assertDontSee('Simulated orchestrator failure');
    }
}
