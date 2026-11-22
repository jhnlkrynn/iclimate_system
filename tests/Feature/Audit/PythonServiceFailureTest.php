<?php

namespace Tests\Feature\Audit;

use App\Services\PythonService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Audit 2026-11-22: how Laravel calls the Python ML service (app/Services/PythonService.php) and what happens when the
 * service answers well, answers badly, times out or is down. HTTP is faked, so this runs without the service.
 *
 * A live check against a running service runs only when ICLIMATE_ML_LIVE_URL is set (e.g. http://127.0.0.1:8010).
 */
class PythonServiceFailureTest extends TestCase
{
    private const URL = 'http://ml.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.farming_ai.url' => self::URL, 'services.farming_ai.timeout' => 5, 'services.farming_ai.connect_timeout' => 3]);
    }

    private function records(): array
    {
        return collect(range(1, 12))->map(fn (int $m): array => ['record_date' => sprintf('2025-%02d-01', $m), 'rainfall' => 150.0,
            'temperature' => 27.5, 'humidity' => 80.0, 'wind_speed' => 10.0])->all();
    }

    private function monthly(): ?array
    {
        return app(PythonService::class)->monthlyWeatherForecast(now()->addMonth(), $this->records());
    }

    private function weather(): array
    {
        return app(PythonService::class)->farmingAssistant(['prediction_type' => 'weather', 'features' => ['previous_rainfall' => 180,
            'previous_temp' => 28, 'previous_humidity' => 82, 'previous_wind_speed' => 12, 'month_num' => 9, 'season' => 'Wet']]);
    }

    // ------------------------------------------------------------------ monthly weather forecast (returns null on any failure)

    public function test_monthly_forecast_good_answer_is_returned(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['ready' => true, 'predictions' => ['rainfall' => 210.5, 'temperature' => 27.9, 'humidity' => 83, 'wind_speed' => 9.5]])]);

        $this->assertSame(210.5, $this->monthly()['predictions']['rainfall']);
        Http::assertSent(fn (Request $r): bool => $r->url() === self::URL.'/forecast/monthly-weather' && count($r['climate_records']) === 12);
    }

    public function test_monthly_forecast_service_down_gives_null_so_the_php_fallback_runs(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->assertNull($this->monthly());
    }

    public function test_monthly_forecast_timeout_gives_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds'));

        $this->assertNull($this->monthly());
    }

    /** @return array<string, array{0: mixed, 1: int}> */
    public static function badMonthlyAnswers(): array
    {
        return [
            'server error' => [['detail' => 'boom'], 500],
            'validation error' => [['detail' => 'Invalid climate_records entry'], 422],
            'not ready' => [['ready' => false, 'predictions' => null], 200],
            'a prediction is not a number' => [['ready' => true, 'predictions' => ['rainfall' => 'lots', 'temperature' => 27, 'humidity' => 80, 'wind_speed' => 9]], 200],
            'a prediction is missing' => [['ready' => true, 'predictions' => ['rainfall' => 200, 'temperature' => 27, 'humidity' => 80]], 200],
            'not JSON' => ['<html>502 Bad Gateway</html>', 200],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badMonthlyAnswers')]
    public function test_monthly_forecast_bad_answers_give_null_not_a_wrong_forecast(mixed $body, int $status): void
    {
        Http::fake([self::URL.'/*' => Http::response($body, $status)]);

        $this->assertNull($this->monthly());
    }

    public function test_monthly_forecast_without_a_service_url_or_records_asks_nothing(): void
    {
        Http::fake();
        config(['services.farming_ai.url' => '']);
        $this->assertNull($this->monthly());
        config(['services.farming_ai.url' => self::URL]);
        $this->assertNull(app(PythonService::class)->monthlyWeatherForecast(now(), []));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ /predict (throws a clear error on failure)

    public function test_predict_good_answer_is_marked_as_coming_over_http(): void
    {
        Http::fake([self::URL.'/predict' => Http::response(['weather_prediction' => ['predicted_weather' => 'Heavy Rain']])]);

        $result = $this->weather();

        $this->assertSame('Heavy Rain', $result['weather_prediction']['predicted_weather']);
        $this->assertSame('http', $result['inference_transport']);
        $this->assertFalse($result['api_fallback_used']);
    }

    public function test_predict_service_down_is_a_clear_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be reached');
        $this->weather();
    }

    public function test_predict_timeout_is_a_clear_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be reached');
        $this->weather();
    }

    public function test_predict_server_error_is_a_clear_error(): void
    {
        Http::fake([self::URL.'/predict' => Http::response(['detail' => 'Model artifacts unavailable.'], 503)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unavailable');
        $this->weather();
    }

    public function test_predict_invalid_json_is_a_clear_error(): void
    {
        Http::fake([self::URL.'/predict' => Http::response('not json', 200, ['Content-Type' => 'text/plain'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid response');
        $this->weather();
    }

    public function test_predict_bad_input_rejected_by_the_service_is_reported_as_refused_input_not_an_outage(): void
    {
        // Audit M-2 (fixed 2026-11-23): a 422 says which input was refused, is not retried locally and does not count
        // as a service failure.
        Http::fake([self::URL.'/predict' => Http::response(['detail' => 'Out-of-range numeric feature: rainfall'], 422)]);
        $key = 'metrics:python-service:failures:'.\App\Support\BusinessClock::today()->toDateString();
        $failuresBefore = \Illuminate\Support\Facades\Cache::get($key);

        try {
            $this->weather();
            $this->fail('Expected the refused input to be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame('The Farming AI API refused the input: Out-of-range numeric feature: rainfall', $e->getMessage());
        }
        $this->assertSame($failuresBefore, \Illuminate\Support\Facades\Cache::get($key), 'refused input is not a service failure');
        Http::assertSentCount(1);
    }

    public function test_rice_yield_bad_input_is_not_re_run_through_the_local_fallback(): void
    {
        // Before the fix the same input went to python_scripts/predict.py and the error read "... local rice-yield
        // inference also failed"; now the service's own reason comes back at once.
        Http::fake([self::URL.'/predict' => Http::response(['detail' => 'Missing required feature: area'], 422)]);

        try {
            app(PythonService::class)->farmingAssistant(['prediction_type' => 'rice_yield', 'features' => ['rainfall' => 200]]);
            $this->fail('Expected the refused input to be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame('The Farming AI API refused the input: Missing required feature: area', $e->getMessage());
            $this->assertNull($e->getPrevious(), 'no local fallback ran');
        }
    }

    // ------------------------------------------------------------------ the real service, when one is running

    public function test_live_service_answers_a_real_prediction(): void
    {
        $live = (string) getenv('ICLIMATE_ML_LIVE_URL');
        if ($live === '') {
            $this->markTestSkipped('Set ICLIMATE_ML_LIVE_URL (e.g. http://127.0.0.1:8010) to check a running ML service.');
        }
        config(['services.farming_ai.url' => $live]);

        $result = $this->weather();

        $this->assertSame('http', $result['inference_transport']);
        $this->assertNotEmpty($result['weather_prediction']['predicted_weather'] ?? null);
    }
}
