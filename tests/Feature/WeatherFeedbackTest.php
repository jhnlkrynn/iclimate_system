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

    public function test_database_enforces_one_answer_per_farmer_per_forecast(): void
    {
        $farmer = $this->farmer();
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id]);
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

    public function test_feedback_never_writes_climate_records_forecasts_or_dispatches_retraining(): void
    {
        $farmer = $this->farmer();                      // made first: a new farmer profile queues its own yield estimate
        Bus::fake();
        Queue::fake();
        $forecast = $this->forecastFor($this->pastMonth);
        ClimateRecord::factory()->create();
        $climateBefore = ClimateRecord::query()->count();
        $forecastBefore = $forecast->fresh()->only(['rainfall', 'temperature_average', 'humidity', 'weather_model', 'status']);

        $this->submitFeedback($farmer, [
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
