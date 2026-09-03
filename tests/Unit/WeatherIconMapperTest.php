<?php

namespace Tests\Unit;

use App\Services\Weather\WeatherIconMapper;
use PHPUnit\Framework\TestCase;

class WeatherIconMapperTest extends TestCase
{
    public function test_maps_clear_day_and_night_icons(): void
    {
        $mapper = new WeatherIconMapper();

        $this->assertSame('/images/weather/clear-day.svg', $mapper->map(0, true)['icon']);
        $this->assertSame('/images/weather/clear-night.svg', $mapper->map(0, false)['icon']);
    }

    public function test_maps_rain_and_thunderstorm_conditions(): void
    {
        $mapper = new WeatherIconMapper();

        $this->assertSame('Light Rain', $mapper->map(61, true)['condition']);
        $this->assertSame('/images/weather/heavy-rain.svg', $mapper->map(82, true)['icon']);
        $this->assertSame('Thunderstorm', $mapper->map(95, true)['condition']);
    }
}
