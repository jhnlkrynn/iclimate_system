<?php

namespace Tests\Feature\MachineLearning;

use App\Models\MonthlyWeatherPrediction;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthlyWeatherFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_python_phase34_is_primary_and_php_fallback_recovers_on_outage_and_reconnect(): void
    {
        Cache::flush();
        $month = CarbonImmutable::create(2027, 4, 1)->startOfMonth();
        $orchestrator = app(PredictionOrchestrator::class);

        Http::fakeSequence('*/forecast/monthly-weather')
            ->push([
                'ready' => true,
                'predictions' => [
                    'rainfall' => 55.25,
                    'temperature' => 28.4,
                    'humidity' => 76.5,
                    'wind_speed' => 2.1,
                ],
                'training' => [
                    'rainfall' => ['trees' => 0, 'samples' => 130, 'features' => 15],
                ],
            ], 200)
            ->push(['detail' => 'unavailable'], 500)
            ->push([
                'ready' => true,
                'predictions' => [
                    'rainfall' => 61.10,
                    'temperature' => 28.6,
                    'humidity' => 75.0,
                    'wind_speed' => 2.2,
                ],
            ], 200);

        $primary = $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);
        $this->assertTrue($primary['ready']);
        $this->assertSame('python_fastapi_phase34', $primary['engine']);
        $this->assertFalse($primary['fallback_used']);
        $this->assertStringContainsString('Python FastAPI Phase 34', $primary['source_name']);
        $this->assertSame(55.25, $primary['predictions']['rainfall']);

        $row = MonthlyWeatherPrediction::query()
            ->where('prediction_year', 2027)
            ->where('prediction_month', 4)
            ->firstOrFail();
        $this->assertSame(MonthlyWeatherRandomForest::MODEL_VERSION, $row->weather_model);
        $this->assertSame('python_fastapi_phase34', data_get($row->metadata, 'engine'));
        $this->assertFalse((bool) data_get($row->metadata, 'fallback_used'));
        $this->assertNotNull($row->generated_at);

        // 2. Python FastAPI fails (500) -> automatic PHP fallback, never misrepresented as Python
        Cache::flush();
        $fallback = $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);
        $this->assertTrue($fallback['ready']);
        $this->assertSame('php_fallback_rf', $fallback['engine']);
        $this->assertTrue($fallback['fallback_used']);
        $this->assertStringContainsString('PHP Fallback', $fallback['source_name']);
        $this->assertIsFloat($fallback['predictions']['rainfall']);

        $row->refresh();
        $this->assertSame('php_fallback_rf', data_get($row->metadata, 'engine'));
        $this->assertTrue((bool) data_get($row->metadata, 'fallback_used'));

        // 3. Python FastAPI reconnects -> switches back to Python primary
        Cache::flush();
        $recovered = $orchestrator->monthlyWeatherForecast($month, forceRefresh: true);
        $this->assertSame('python_fastapi_phase34', $recovered['engine']);
        $this->assertFalse($recovered['fallback_used']);
        $this->assertSame(61.10, $recovered['predictions']['rainfall']);
    }
}
