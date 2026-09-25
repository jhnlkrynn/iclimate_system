<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $pastMonth;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
        $this->skipMonthlyModel();
        Config::set('services.weather_api.enabled', true);
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $this->pastMonth = CarbonImmutable::now('Asia/Manila')->subMonthNoOverflow()->startOfMonth();
    }

    private function forecastFor(CarbonImmutable $month, float $rainfall = 150): MonthlyWeatherPrediction
    {
        return MonthlyWeatherPrediction::query()->create([
            'prediction_year' => $month->year,
            'prediction_month' => $month->month,
            'rainfall' => $rainfall,
            'temperature_average' => 28.5,
            'humidity' => 80,
            'weather_model' => 'test-model-v1',
            'generated_at' => now(),
            'status' => MonthlyWeatherPrediction::STATUS_READY,
        ]);
    }

    private function farmer(?string $barangay = 'Malaruhatan'): User
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);

        return $user;
    }

    private function submitFeedback(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('weather-feedback.store'), $data);
    }

    public function test_farmer_can_submit_feedback_and_snapshot_is_stored(): void
    {
        $forecast = $this->forecastFor($this->pastMonth);
        $farmer = $this->farmer();

        $this->submitFeedback($farmer, [
            'forecast_date' => $this->pastMonth->toDateString(),
            'accuracy' => 'different',
            'observed_condition' => 'Heavy Rain',
        ])->assertRedirect(route('weather-predictions.index', ['tab' => 'weather-forecast']))
            ->assertSessionHas('weather_check_status');

        $row = WeatherForecastFeedback::query()->firstOrFail();
        $this->assertSame($farmer->id, $row->user_id);
        $this->assertSame('Malaruhatan', $row->barangay);
        $this->assertSame($forecast->id, $row->monthly_weather_prediction_id);
        $this->assertSame('Moderate Rain', $row->forecast_condition);
        $this->assertSame('different', $row->accuracy);
        $this->assertSame('Heavy Rain', $row->observed_condition);
        $this->assertEquals(150, (float) $row->forecast_rainfall_mm);

        // The original forecast row is untouched.
        $this->assertEquals(150, (float) $forecast->fresh()->rainfall);
    }

    public function test_accurate_answer_stores_no_observed_condition_even_if_one_is_sent(): void
    {
        $this->forecastFor($this->pastMonth);

        $this->submitFeedback($this->farmer(), [
            'forecast_date' => $this->pastMonth->toDateString(),
            'accuracy' => 'accurate',
            'observed_condition' => 'Sunny',
        ])->assertSessionHas('weather_check_status');

        $this->assertNull(WeatherForecastFeedback::query()->firstOrFail()->observed_condition);
    }

    public function test_duplicate_submission_is_blocked_with_a_friendly_message(): void
    {
        $this->forecastFor($this->pastMonth);
        $farmer = $this->farmer();
        $payload = ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate'];

        $this->submitFeedback($farmer, $payload)->assertSessionHas('weather_check_status');
        $this->submitFeedback($farmer, ['accuracy' => 'different', 'observed_condition' => 'Sunny'] + $payload)
            ->assertSessionHas('weather_check_error', 'You already answered this - thank you.');

        $this->assertSame(1, WeatherForecastFeedback::query()->count());
        $this->assertSame('accurate', WeatherForecastFeedback::query()->first()->accuracy);
    }

    public function test_database_enforces_one_answer_per_farmer_per_forecast(): void
    {
        $farmer = $this->farmer();
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id]);
    }

    public function test_future_or_current_period_is_rejected(): void
    {
        $farmer = $this->farmer();
        $current = CarbonImmutable::now('Asia/Manila')->startOfMonth();
        $next = $current->addMonth();
        $this->forecastFor($current);
        $this->forecastFor($next);

        foreach ([$current, $next] as $month) {
            $this->submitFeedback($farmer, ['forecast_date' => $month->toDateString(), 'accuracy' => 'accurate'])
                ->assertSessionHas('weather_check_error');
        }

        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_missing_stored_forecast_is_rejected_without_inventing_one(): void
    {
        $this->submitFeedback($this->farmer(), ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate'])
            ->assertSessionHas('weather_check_error');

        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_invalid_accuracy_and_observed_values_are_rejected(): void
    {
        $this->forecastFor($this->pastMonth);
        $farmer = $this->farmer();
        $date = $this->pastMonth->toDateString();

        $this->submitFeedback($farmer, ['forecast_date' => $date, 'accuracy' => 'maybe'])->assertSessionHasErrors('accuracy');
        $this->submitFeedback($farmer, ['forecast_date' => $date])->assertSessionHasErrors('accuracy');
        $this->submitFeedback($farmer, ['forecast_date' => $date, 'accuracy' => 'different', 'observed_condition' => 'Tornado'])
            ->assertSessionHasErrors('observed_condition');
        $this->submitFeedback($farmer, ['forecast_date' => 'garbage', 'accuracy' => 'accurate'])->assertSessionHasErrors('forecast_date');

        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_only_farmers_may_submit(): void
    {
        $this->forecastFor($this->pastMonth);
        $payload = ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate'];

        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
            $user = User::factory()->create(['role' => $role, 'barangay' => 'Malaruhatan']);
            $this->submitFeedback($user, $payload)->assertForbidden();
        }

        $this->app['auth']->guard()->logout();
        $this->post(route('weather-feedback.store'), $payload)->assertRedirect(route('login'));
        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_farmer_without_barangay_gets_a_friendly_message(): void
    {
        $this->forecastFor($this->pastMonth);
        $farmer = User::factory()->farmer()->create(['barangay' => null]);

        $this->submitFeedback($farmer, ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate'])
            ->assertRedirect()
            ->assertSessionHas('weather_check_error');
        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_farmer_with_non_lian_barangay_is_not_silently_assigned_one(): void
    {
        $this->forecastFor($this->pastMonth);
        $farmer = $this->farmer('Atlantis');

        $this->submitFeedback($farmer, ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate'])
            ->assertSessionHas('weather_check_error');
        $this->assertSame(0, WeatherForecastFeedback::query()->count());
    }

    public function test_weather_tab_shows_prompt_only_to_farmers_and_only_for_ended_periods(): void
    {
        $this->forecastFor($this->pastMonth);
        $this->forecastFor(CarbonImmutable::now('Asia/Manila')->addMonth()->startOfMonth());
        $farmer = $this->farmer();

        $this->actingAs($farmer)->get(route('weather-predictions.index'))
            ->assertSee('Was this weather forecast accurate in your area?')
            ->assertSee($this->pastMonth->format('F Y'))
            ->assertSee('Partly accurate')
            ->assertSee('No, it was different')
            ->assertSee('What weather did you experience?')
            ->assertSee('Strong Wind');

        $mao = User::factory()->maoPersonnel()->create(['barangay' => 'Malaruhatan']);
        $this->actingAs($mao)->get(route('weather-predictions.index'))
            ->assertDontSee('Was this weather forecast accurate in your area?');

        // After answering, the prompt is gone.
        $this->submitFeedback($farmer, ['forecast_date' => $this->pastMonth->toDateString(), 'accuracy' => 'accurate']);
        $this->actingAs($farmer)->get(route('weather-predictions.index'))
            ->assertDontSee('Was this weather forecast accurate in your area?');
    }

    public function test_feedback_never_writes_climate_records_forecasts_or_dispatches_retraining(): void
    {
        Bus::fake();
        Queue::fake();
        $forecast = $this->forecastFor($this->pastMonth);
        ClimateRecord::factory()->create();
        $climateBefore = ClimateRecord::query()->count();
        $forecastBefore = $forecast->fresh()->only(['rainfall', 'temperature_average', 'humidity', 'weather_model', 'status']);

        $this->submitFeedback($this->farmer(), [
            'forecast_date' => $this->pastMonth->toDateString(),
            'accuracy' => 'different',
            'observed_condition' => 'Sunny',
        ]);

        $this->assertSame($climateBefore, ClimateRecord::query()->count());
        $this->assertSame(1, MonthlyWeatherPrediction::query()->count());
        $this->assertEquals($forecastBefore, $forecast->fresh()->only(['rainfall', 'temperature_average', 'humidity', 'weather_model', 'status']));
        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
    }

    public function test_export_command_writes_validated_rows_only(): void
    {
        $farmer = $this->farmer();
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Malaruhatan']);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wff-export-test.csv';

        $this->artisan('weather:export-feedback', ['path' => $path])->assertSuccessful();

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Malaruhatan', $lines[1]);
        @unlink($path);
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
