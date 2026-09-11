<?php

namespace Tests\Unit;

use App\Services\Prediction\WeatherLabeler;
use PHPUnit\Framework\TestCase;

class WeatherLabelerTest extends TestCase
{
    /**
     * Pins the exact thresholds that must match python_scripts/rice_yield_service.py's
     * weather_label(). If this test needs to change, the Python function must change too.
     */
    public function test_rainfall_thresholds(): void
    {
        $this->assertSame('Dry', WeatherLabeler::fromRainfall(70));
        $this->assertSame('Cloudy', WeatherLabeler::fromRainfall(71));
        $this->assertSame('Cloudy', WeatherLabeler::fromRainfall(119));
        $this->assertSame('Rain', WeatherLabeler::fromRainfall(120));
        $this->assertSame('Rain', WeatherLabeler::fromRainfall(299));
        $this->assertSame('Heavy Rain', WeatherLabeler::fromRainfall(300));
    }
}
