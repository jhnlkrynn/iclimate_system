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
 * season's expected yield (farmer: own estimate from the profile; MAO/IT: average of farmers' saved estimates).
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
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictForUser'));
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

        return $user;
    }

    private function storeEstimate(User $farmer, float $yield): void
    {
        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id), ['success' => true, 'yield_tons_per_hectare' => $yield, 'target_month' => now()->format('Y-m'),
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

    public function test_one_page_for_every_role_with_no_date_or_month_picker_and_no_outside_call(): void
    {
        $this->storeLianForecast(16);
        $users = [$this->farmer(), User::factory()->create(['role' => User::ROLE_MAO]), User::factory()->create(['role' => User::ROLE_IT_EXPERT])];

        foreach ($users as $user) {
            $html = $this->actingAs($user)->get(route('weather-predictions.index'))->assertOk()->getContent();
            $this->assertStringContainsString('data-wpx-weather', $html);
            $this->assertStringContainsString('data-wpx-yield', $html);
            $this->assertDoesNotMatchRegularExpression('/type="(date|month)"/', $html, $user->role);
            $this->assertStringNotContainsString('calendar_month', $html);
        }
        Http::assertNothingSent();
    }

    public function test_this_week_is_days_1_to_7_and_next_2_weeks_is_days_1_to_14(): void
    {
        $this->storeLianForecast(16);
        $farmer = $this->farmer();

        $week = $this->actingAs($farmer)->get(route('weather-predictions.index'))->getContent();
        $this->assertStringContainsString('data-days="7"', $week);
        $this->assertSame(7, substr_count($week, '<li class="wpx-day'));
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>This Week</a>#', $week);

        $twoWeeks = $this->actingAs($farmer)->get(route('weather-predictions.index', ['range' => '2weeks']))->getContent();
        $this->assertSame(14, substr_count($twoWeeks, '<li class="wpx-day'));
        $this->assertMatchesRegularExpression('#aria-current="page"\s*>Next 2 Weeks</a>#', $twoWeeks);
        $this->assertStringNotContainsString('data-kind="estimate"', $twoWeeks, 'the provider covers all 14 days');
    }

    public function test_days_the_daily_forecast_does_not_reach_are_tantiya_from_the_monthly_model(): void
    {
        $this->storeLianForecast(5);
        $this->prepareMonthlyForecasts(310.0);                                      // about 10 mm a day: moderate rain

        $html = $this->actingAs($this->farmer())->get(route('weather-predictions.index', ['range' => '2weeks']))->getContent();

        $this->assertSame(5, substr_count($html, 'data-kind="forecast"'));
        $this->assertSame(9, substr_count($html, 'data-kind="estimate"'));
        $this->assertStringContainsString('<span class="wpx-badge">Tantiya</span>', $html);
        $this->assertStringContainsString('data-wpx-estimate-note', $html);
    }

    public function test_without_any_monthly_forecast_a_missing_day_says_no_data_and_nothing_is_invented(): void
    {
        $this->storeLianForecast(3);

        $html = $this->actingAs($this->farmer())->get(route('weather-predictions.index'))->getContent();

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

    public function test_farmers_read_words_and_staff_also_see_numbers(): void
    {
        $this->storeLianForecast(16, rain: 12.0, code: 95);                            // thunderstorm days with 12 mm
        $farmer = $this->text($this->actingAs($this->farmer(barangay: 'Prenza'))->get(route('weather-predictions.index'))->getContent());
        $mao = $this->text($this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('Moderate rain', $farmer);
        $this->assertDoesNotMatchRegularExpression('/\d\s*mm\b/', $farmer);
        $this->assertStringNotContainsString('km/h', $farmer);

        $this->assertStringContainsString('12.0 mm', $mao);
        $this->assertStringContainsString('80%', $mao);
        $this->assertStringContainsString('14.0 km/h', $mao);
    }

    public function test_a_day_never_names_two_different_rains(): void
    {
        $this->storeLianForecast(16, rain: 4.0, code: 65);                             // "Heavy Rain" weather code, 4 mm in the day

        $text = $this->text($this->actingAs($this->farmer(barangay: 'Prenza'))->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('Light rain', $text);
        $this->assertStringNotContainsString('Heavy Rain', $text);
    }

    public function test_the_farmer_sees_their_barangay_in_filipino(): void
    {
        $this->storeBarangayForecast('Prenza', 0.0);
        $farmer = $this->farmer(barangay: 'Prenza', attributes: ['preferred_language' => 'fil']);

        $text = $this->text($this->actingAs($farmer)->get(route('weather-predictions.index'))->getContent());

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

        foreach ([$this->farmer(), User::factory()->create(['role' => User::ROLE_IT_EXPERT])] as $other) {
            $this->assertStringNotContainsString('data-wpx-filter', $this->actingAs($other)->get(route('weather-predictions.index', ['barangay' => 'Matabungkay']))->getContent());
        }
    }

    public function test_only_it_gets_technical_details_without_model_performance_metrics(): void
    {
        $this->prepareMonthlyForecasts();
        MlModel::query()->create(['purpose' => MlModel::PURPOSE_RICE_YIELD, 'name' => 'Rice yield model (production)', 'model_type' => 'random_forest', 'version' => '1.0.0',
            'file_path' => 'storage/models/rice_yield_model_final.pkl', 'rmse' => 0.412, 'mae' => 0.318, 'r2' => 0.871, 'status' => MlModel::STATUS_ACTIVE, 'is_active' => true]);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $html = $this->actingAs($it)->get(route('weather-predictions.index'))->getContent();
        $this->assertStringContainsString('<section class="fad-card wpx-tech" data-wpx-technical aria-labelledby="wpxTechnicalTitle">', $html);
        $this->assertStringContainsString('Technical Details', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('<summary', $html);
        $this->assertStringNotContainsString('Show technical details', $html);
        foreach (['monthly_weather_rf_v5', 'Rice yield model (production)', '1.0.0', '—', '68%'] as $value) {
            $this->assertStringContainsString($value, $html, $value);
        }
        foreach (['RMSE', 'MAE', 'R²', 'Not recorded', 'Last run'] as $metric) {
            $this->assertStringNotContainsString($metric, $html, $metric);
        }
        $this->assertSame(['rmse' => 0.412, 'mae' => 0.318, 'r2' => 0.871], MlModel::query()->firstOrFail()->only(['rmse', 'mae', 'r2']));

        foreach ([$this->farmer(), User::factory()->create(['role' => User::ROLE_MAO])] as $other) {
            $page = $this->actingAs($other)->get(route('weather-predictions.index'))->getContent();
            $this->assertStringNotContainsString('data-wpx-technical', $page, $other->role);
            $this->assertStringNotContainsString('RMSE', $page, $other->role);
        }
    }

    // ------------------------------------------------------------ yield this season

    public function test_the_farmer_sees_their_own_estimate_for_this_season_from_the_profile(): void
    {
        $this->lianYields(4.0);
        $farmer = $this->farmer(area: 1.5);
        $this->storeEstimate($farmer, 4.6);

        $text = $this->text($this->actingAs($farmer)->get(route('weather-predictions.index'))->getContent());

        $this->assertStringContainsString('4.60 t/ha', $text);
        $this->assertStringContainsString('Higher', $text);
        $this->assertStringContainsString('From your farm size (1.50 ha) and farm type (Irrigated', $text);
        $this->assertStringContainsString(\App\Support\RiceSeason::current() === 'Wet' ? 'Wet season' : 'Dry season', $text);
    }

    public function test_a_farmer_without_a_saved_estimate_waits_for_the_background_job_and_without_a_farm_size_is_told_to_add_it(): void
    {
        $text = $this->text($this->actingAs($this->farmer())->get(route('weather-predictions.index'))->getContent());
        $this->assertStringContainsString('Your estimate is being prepared.', $text);
        Queue::assertPushed(\App\Jobs\PrepareFarmerYieldEstimate::class);

        $noArea = $this->farmer(area: null, attributes: ['email' => 'noarea@example.test']);
        Cache::put(FarmerDashboardService::predictionCacheKey($noArea->id), ['failed' => true, 'lacks_records' => false], now()->addMinutes(5));
        $this->assertStringContainsString('Add your farm size to see your estimate.', $this->text($this->actingAs($noArea)->get(route('weather-predictions.index'))->getContent()));
    }

    public function test_staff_see_the_average_of_farmers_saved_estimates_for_the_area_shown(): void
    {
        $this->lianYields(4.0);
        $this->storeEstimate($a = $this->farmer('Binubusan'), 4.0);
        $this->storeEstimate($this->farmer('Binubusan', attributes: ['email' => 'b2@example.test']), 5.0);
        $this->storeEstimate($this->farmer('Prenza', attributes: ['email' => 'p1@example.test']), 2.0);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $all = $this->text($this->actingAs($mao)->get(route('weather-predictions.index'))->getContent());
        $this->assertStringContainsString('3.67 t/ha', $all);
        $this->assertStringContainsString("Average of 3 farmers' saved estimates.", $all);

        $one = $this->text($this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'Binubusan']))->getContent());
        $this->assertStringContainsString('4.50 t/ha', $one);
        $this->assertStringContainsString("Average of 2 farmers' saved estimates.", $one);

        $none = $this->text($this->actingAs($mao)->get(route('weather-predictions.index', ['barangay' => 'Cumba']))->getContent());
        $this->assertStringContainsString('No saved farmer estimates yet for this area.', $none);
    }

    // ------------------------------------------------------------ old URLs

    public function test_old_prediction_forms_and_the_candidate_test_page_redirect_to_the_new_pages(): void
    {
        $farmer = $this->farmer();
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)->post(route('weather-predictions.predict'), ['prediction_type' => 'weather'])->assertRedirect(route('weather-predictions.index'));
        $this->actingAs($farmer)->post(route('rice-yield-predictions.predict'), ['prediction_type' => 'rice_yield'])->assertRedirect(route('weather-predictions.index').'#yield');
        $this->actingAs($it)->get(route('testing.candidate-rice-yield'))->assertRedirect(route('model-evaluation.index'));
        $this->actingAs($it)->post(route('testing.candidate-rice-yield.predict'))->assertRedirect(route('model-evaluation.index'));
        $this->actingAs($farmer)->get(route('weather-predictions.index', ['target_month' => '2026-12', 'calendar_month' => '2026-11', 'tab' => 'rice-yield']))->assertOk();
        $this->assertSame(0, \App\Models\AnalyticsRun::query()->count(), 'nothing is queued by the old forms');
    }

    public function test_a_failing_monthly_model_never_breaks_the_page(): void
    {
        $this->storeLianForecast(3);
        $this->mock(\App\Services\Prediction\PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('preparedMonthlyWeatherForecasts')->andThrow(new \RuntimeException('boom')));

        $this->actingAs($this->farmer())->get(route('weather-predictions.index', ['range' => '2weeks']))->assertOk()->assertDontSee('boom');
    }
}
