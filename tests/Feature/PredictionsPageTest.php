<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\MlModel;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 3 (2026-10): one Predictions page for every role, from saved data only. Weather: [This Week] = days 1–7,
 * [Next 2 Weeks] = days 1–14 of the stored daily forecast, missing days as "Tantiya" from the monthly model. Yield: this
 * season's expected yield (farmer: own estimate from the profile; All of Lian: the trained municipality model).
 * Farmers read words; MAO and IT see numbers; MAO has a barangay filter; IT has the technical details.
 */
class PredictionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        config(['services.weather.provider' => 'openmeteo', 'services.weatherapi.key' => null, 'services.weather.forecast_days' => 7]);
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->zeroOrMoreTimes()->andReturn([
            'success' => false, 'missing_inputs' => ['test fixture'], 'api_error' => 'test fixture',
        ]));
    }

    // ------------------------------------------------------------ fixtures (what the scheduled jobs store)

    /** The shared Lian reading with $days days of forecast, stored by the scheduled refresh. */
    private function storeLianForecast(int $days, float $rain = 12.0, int $code = 63): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => $today->addHours(9)->format('Y-m-d\TH:i'), 'temperature_2m' => 29.0, 'relative_humidity_2m' => 80, 'apparent_temperature' => 32,
                'precipitation' => 0.4, 'rain' => 0.4, 'showers' => 0, 'weather_code' => $code, 'cloud_cover' => 80, 'wind_speed_10m' => 9, 'wind_direction_10m' => 90, 'is_day' => 1],
            'daily' => [
                'time' => array_map(fn ($i) => $today->addDays($i)->toDateString(), range(0, $days - 1)),
                'weather_code' => array_fill(0, $days, $code),
                'temperature_2m_max' => array_fill(0, $days, 31.0), 'temperature_2m_min' => array_fill(0, $days, 24.0),
                'precipitation_sum' => array_fill(0, $days, $rain), 'rain_sum' => array_fill(0, $days, $rain),
                'precipitation_probability_max' => array_fill(0, $days, 80), 'wind_speed_10m_max' => array_fill(0, $days, 14.0),
            ],
            'hourly' => ['time' => [], 'temperature_2m' => [], 'relative_humidity_2m' => []],
        ])]);
        app(FarmerDashboardWeatherService::class)->current(force: true);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response([], 500)]);                                  // the page itself never calls out
    }

    private function storeBarangayForecast(string $barangay, float $rain): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => [$barangay => [
            'barangay' => $barangay, 'temperature_c' => 28.0, 'fetched_at' => now()->toIso8601String(), 'observed_at' => now()->toIso8601String(),
            'daily' => array_map(fn ($i) => ['date' => $today->addDays($i)->toDateString(), 'weather_code' => 3, 'condition' => 'Cloudy', 'rainfall' => $rain,
                'precipitation_probability' => 40, 'temperature_max' => 30.0, 'temperature_min' => 23.0, 'wind_speed_max' => 8.0], range(0, 15)),
        ]], 'status' => []]);
    }

    /** A prepared monthly forecast for every month the next two weeks touch. */
    private function prepareMonthlyForecasts(float $rainfall = 310.0): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        foreach ([$today, $today->addDays(14)] as $month) {
            MonthlyWeatherPrediction::query()->updateOrCreate(
                ['prediction_year' => $month->year, 'prediction_month' => $month->month, 'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION],
                ['rainfall' => $rainfall, 'tmax' => 31.5, 'tmin' => 24.0, 'temperature_average' => 27.7, 'humidity' => 82, 'wind_speed' => 3.0, 'season' => 'Wet',
                    'status' => MonthlyWeatherPrediction::STATUS_READY, 'generated_at' => now(), 'expires_at' => now()->addDay(),
                    'metadata' => ['confidence' => ['value' => 68, 'label' => 'Moderate confidence']]],
            );
        }
    }

    private function farmer(string $barangay = 'Binubusan', ?float $area = 2.0, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => User::ROLE_FARMER, 'barangay' => $barangay]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => $area, 'farm_type' => 'Irrigated']);
        if (\App\Support\ActiveFarm::available()) {
            \App\Models\Farm::query()->where('user_id', $user->id)->update(['verification_status' => \App\Models\Farm::VERIFICATION_APPROVED, 'verified_at' => now()]);
        }

        return $user;
    }

    private function storeEstimate(User $farmer, float $yield): void
    {
        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id), ['success' => true, 'yield_tons_per_hectare' => $yield, 'target_month' => \App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m'),
            'ran_at' => now('Asia/Manila')->toIso8601String()], now()->addHours(6));
    }

    private function lianYields(float $yield): void
    {
        $rows = array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => $yield, 'split' => null], range(1, 12));
        $this->app->instance(ProjectYieldDataset::class, new class($rows) extends ProjectYieldDataset
        {
            public function __construct(private array $custom) {}

            public function available(): bool
            {
                return true;
            }

            public function rows(): Collection
            {
                return collect($this->custom);
            }
        });
    }

    private function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)[^>]*>.*?</\1>#s', '', $html);

        return (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(substr($html, (int) strpos($html, 'data-predictions')))));
    }

    // ------------------------------------------------------------ one page, no pickers, no outside calls



    public function test_this_week_is_days_1_to_7_and_next_2_weeks_is_days_1_to_14(): void
    {
        $this->storeLianForecast(16);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $week = $this->actingAs($mao)->get(route('weather-predictions.index'))->getContent();
        $this->assertStringContainsString('data-days="7"', $week);
        $this->assertSame(7, substr_count($week, '<li class="wpx-day'));
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>This Week</a>#', $week);

        $twoWeeks = $this->actingAs($mao)->get(route('weather-predictions.index', ['range' => '2weeks']))->getContent();
        $this->assertSame(14, substr_count($twoWeeks, '<li class="wpx-day'));
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Next 2 Weeks</a>#', $twoWeeks);
        $this->assertStringNotContainsString('data-kind="estimate"', $twoWeeks, 'the provider covers all 14 days');
    }

    public function test_days_the_daily_forecast_does_not_reach_are_tantiya_from_the_monthly_model(): void
    {
        $this->storeLianForecast(5);
        $this->prepareMonthlyForecasts(310.0);                                      // about 10 mm a day: moderate rain
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('weather-predictions.index', ['range' => '2weeks']))->getContent();

        $this->assertSame(5, substr_count($html, 'data-kind="forecast"'));
        $this->assertSame(9, substr_count($html, 'data-kind="estimate"'));
        $this->assertStringContainsString('<span class="wpx-badge">Tantiya</span>', $html);
        $this->assertStringContainsString('data-wpx-estimate-note', $html);
    }

    public function test_without_any_monthly_forecast_a_missing_day_says_no_data_and_nothing_is_invented(): void
    {
        $this->storeLianForecast(3);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('weather-predictions.index'))->getContent();

        $this->assertSame(4, substr_count($html, 'data-kind="none"'));
        $this->assertStringNotContainsString('data-kind="estimate"', $html);
    }

    public function test_with_nothing_saved_the_weather_says_walang_datos_pa(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->assertStringContainsString('Walang datos pa', $this->text($this->actingAs($mao)->get(route('weather-predictions.index'))->getContent()));
    }

    // ------------------------------------------------------------ words for farmers, numbers for staff

    public function test_farmers_are_redirected_and_staff_see_numbers(): void
    {
        $this->storeLianForecast(16, rain: 12.0, code: 95);                            // thunderstorm days with 12 mm
        $this->actingAs($this->farmer(barangay: 'Prenza'))->get(route('weather-predictions.index'))->assertRedirect(route('farmer.dashboard'));
        $mao = $this->text($this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('12.0 mm', $mao);
        $this->assertStringContainsString('80%', $mao);
        $this->assertStringContainsString('14.0 km/h', $mao);
    }

    public function test_the_farmers_answer_names_the_week_in_plain_words(): void
    {
        // The rules on their own: no rain is careful (hot at 35 °C), rain on most days is good, nothing saved is none.
        $day = fn (int $i, ?string $rain, float $high = 31.0) => ['kind' => 'forecast', 'date' => CarbonImmutable::now('Asia/Manila')->addDays($i), 'rain_key' => $rain,
            'icon' => $rain === 'none' ? 'sun' : 'rain', 'wind_words' => null, 'wind_kmh' => 5.0, 'high' => $high];
        $answer = fn (array $days) => \App\Http\Controllers\WeatherPredictionController::plainForecast(['state' => 'ok', 'days' => $days])['answer']['key'];
        $this->assertSame('dry', $answer(array_map(fn ($i) => $day($i, 'none'), range(0, 6))));
        $this->assertSame('dry_hot', $answer(array_map(fn ($i) => $day($i, 'none', 35.5), range(0, 6))));
        $this->assertSame('rainy', $answer(array_map(fn ($i) => $day($i, $i < 4 ? 'light' : 'none'), range(0, 6))));
        $this->assertSame('mixed', $answer(array_map(fn ($i) => $day($i, $i < 2 ? 'moderate' : 'none'), range(0, 6))));
        $this->assertSame('none', \App\Http\Controllers\WeatherPredictionController::plainForecast(['state' => 'nodata', 'days' => []])['answer']['key']);
    }

    public function test_a_day_never_names_two_different_rains(): void
    {
        $this->storeLianForecast(16, rain: 4.0, code: 65);                             // "Heavy Rain" weather code, 4 mm in the day
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $text = $this->text($this->actingAs($mao)->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('Light rain', $text);
        $this->assertStringNotContainsString('Heavy Rain', $text);
    }

    public function test_the_mao_sees_filipino_labels_when_selected(): void
    {
        $this->storeBarangayForecast('Prenza', 0.0);
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'preferred_language' => 'fil']);

        $text = $this->text($this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'Prenza']))->getContent());

        $this->assertStringContainsString('Para sa Prenza, Lian', $text);
        $this->assertStringContainsString('Ngayong Linggo', $text);
        $this->assertStringContainsString('Walang ulan', $text);
    }

    // ------------------------------------------------------------ MAO barangay filter, IT technical details

    public function test_mao_can_pick_a_barangay_and_farmers_and_it_cannot(): void
    {
        $this->storeLianForecast(16, rain: 12.0);
        $this->storeBarangayForecast('Matabungkay', 0.0);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'Matabungkay']))->getContent();
        $this->assertStringContainsString('data-wpx-filter', $html);
        $this->assertStringContainsString('For Matabungkay, Lian', $this->text($html));
        $this->assertStringContainsString('No rain', $this->text($html));

        $all = $this->actingAs($mao)->get(route('weather-predictions.index'))->getContent();
        $this->assertStringContainsString('For Lian, Batangas', $this->text($all));

        foreach ([$this->farmer()] as $other) {
            $this->actingAs($other)->get(route('weather-predictions.index', ['barangay' => 'Matabungkay']))->assertRedirect(route('farmer.dashboard'));
        }
    }



    // ------------------------------------------------------------ yield this season

    public function test_the_farmer_is_redirected_from_predictions_page(): void
    {
        $farmer = $this->farmer(area: 1.5);
        $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertRedirect(route('farmer.dashboard'));
    }

    public function test_staff_use_the_model_for_all_lian_and_saved_estimates_only_for_a_barangay_filter(): void
    {
        $this->lianYields(4.0);
        $this->storeEstimate($a = $this->farmer('Binubusan'), 4.0);
        $this->storeEstimate($this->farmer('Binubusan', attributes: ['email' => 'b2@example.test']), 5.0);
        $this->storeEstimate($this->farmer('Prenza', attributes: ['email' => 'p1@example.test']), 2.0);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $calls = 0;
        $this->mock(RiceYieldPredictionService::class, function ($mock) use (&$calls): void {
            $mock->shouldReceive('municipalityArea')->once()->andReturn(2.5);
            $mock->shouldReceive('predictForUser')->once()->andReturnUsing(function () use (&$calls): array {
                $calls++;

                return ['success' => true, 'yield_tons_per_hectare' => 4.13, 'predicted_yield' => 4.13, 'farm_area_hectares' => 2.5,
                    'model' => 'Random Forest', 'model_source' => 'storage/models/rice_yield_model_final.pkl', 'model_version' => 'test'];
            });
        });

        $all = $this->text($this->actingAs($mao)->get(route('weather-predictions.index'))->getContent());
        $this->assertStringContainsString('Preparing the latest yield forecast...', $all);
        $this->assertStringNotContainsString("Average of 3 farmers' saved estimates.", $all);
        $this->assertSame(1, $calls, 'the first page starts exactly one after-response inference');
        $this->assertIsArray(Cache::get('predictions:municipal-yield:'.\App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m')));
        Queue::assertNotPushed(\App\Jobs\PrepareMunicipalYieldForecast::class);

        $one = $this->text($this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'Binubusan']))->getContent());
        $this->assertStringContainsString('4.50 t/ha', $one);
        $this->assertStringContainsString("Average of 2 farmers' saved estimates.", $one);

        $none = $this->text($this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'San Diego']))->getContent());
        $this->assertStringContainsString('No saved farmer estimates yet for this area.', $none);
    }

    public function test_the_farmer_can_open_how_the_estimate_was_made(): void
    {
        config(['prediction.rice_yield.gate' => 'label']);
        $this->lianYields(4.0);
        $farmer = $this->farmer(area: 1.5);
        $month = now('Asia/Manila')->startOfMonth();
        $previous = $month->copy()->subMonthNoOverflow()->format('Y-m');
        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id), [
            'success' => true, 'yield_tons_per_hectare' => 3.86, 'uncertainty' => 0.35, 'target_month' => $month->format('Y-m'),
            'ran_at' => now('Asia/Manila')->toIso8601String(), 'model' => 'Random Forest', 'model_version' => 'rice_yield_model_final:687394f77677',
            'validation_status' => 'within_training_range', 'accuracy_verified' => false, 'fallback' => false, 'farm_type' => 'Rainfed',
            'inputs_used' => ['rainfall' => 136.77, 'temp_avg' => 27.61, 'temp_range' => 7.64, 'area' => 1.5, 'previous_rainfall' => 127.25,
                'previous_temp' => 28.64, 'rainfall_6m' => 197.1, 'temp_3m' => 28.23, 'temp_6m' => 29.03, 'seasonal_rainfall' => 708.34,
                'seasonal_temp' => 27.48, 'season' => 'Dry', 'farm_type' => 'Rainfed'],
            'climate_sources' => ['reanalysis_months' => [$previous], 'forecast_months' => [], 'missing_months' => []],
        ], now()->addHours(6));

        $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertRedirect(route('farmer.dashboard'));
    }

    public function test_all_lian_renders_a_cached_model_result_without_farmer_estimates(): void
    {
        $this->storeLianForecast(16);
        Cache::forever('predictions:municipal-yield:'.\App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m'), [
            'state' => 'ok', 'yield' => 4.25, 'usual' => 4.0, 'compare' => 'above', 'compare_label' => 'Higher',
            'ran_at' => now('Asia/Manila')->subHour()->toIso8601String(), 'model' => 'Random Forest',
        ]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $text = $this->text($this->actingAs($mao)->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('4.25 t/ha', $text);
        $this->assertStringContainsString('Early estimate (not final)', $text);
        $this->assertStringContainsString('Municipality-wide forecast from the trained rice-yield model', $text);
        $this->assertStringNotContainsString('No saved farmer estimates yet', $text);
        Queue::assertNotPushed(\App\Jobs\PrepareMunicipalYieldForecast::class);
    }

    public function test_municipality_status_endpoint_returns_only_the_cached_result(): void
    {
        Cache::forever('predictions:municipal-yield:'.\App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m'), [
            'state' => 'ok', 'yield' => 4.13, 'ran_at' => now('Asia/Manila')->toIso8601String(),
        ]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('weather-predictions.municipality-yield-status'))
            ->assertOk()
            ->assertJson(['status' => 'ready', 'yield' => 4.13, 'unit' => 't/ha']);
        Queue::assertNotPushed(\App\Jobs\PrepareMunicipalYieldForecast::class);
    }

    public function test_municipality_failure_cooldown_returns_unavailable_without_restarting_inference(): void
    {
        Cache::put('predictions:municipal-yield-failure:'.\App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m'), ['at' => now()->toIso8601String()], now()->addMinutes(30));
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('weather-predictions.municipality-yield-status'))
            ->assertOk()
            ->assertJsonPath('status', 'unavailable');
        Queue::assertNotPushed(\App\Jobs\PrepareMunicipalYieldForecast::class);
    }

    // ------------------------------------------------------------ old URLs



    public function test_a_failing_monthly_model_never_breaks_the_page(): void
    {
        $this->storeLianForecast(3);
        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('preparedMonthlyWeatherForecasts')->andThrow(new \RuntimeException('boom')));
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('weather-predictions.index', ['range' => '2weeks']))->assertOk()->assertDontSee('boom');
    }
}
