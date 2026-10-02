<?php

namespace Tests\Unit;

use App\Services\Weather\WeatherIconMapper;
use PHPUnit\Framework\TestCase;

class WeatherIconMapperTest extends TestCase
{
    public function test_drizzle_codes_are_shown_with_rain_wording_and_keep_intensity(): void
    {
        $expected = [51 => 'Light Rain', 56 => 'Light Rain', 53 => 'Moderate Rain', 55 => 'Heavy Rain', 57 => 'Heavy Rain'];

        foreach ($expected as $code => $label) {
            $result = (new WeatherIconMapper)->map($code);

            $this->assertSame($label, $result['condition']);
            $this->assertStringNotContainsStringIgnoringCase('drizzle', $result['condition']);
            $this->assertSame('/images/weather/light-rain.svg', $result['icon']);
        }
    }

    public function test_regular_rain_codes_keep_their_intensity_wording(): void
    {
        $this->assertSame('Light Rain', (new WeatherIconMapper)->map(61)['condition']);
        $this->assertSame('Moderate Rain', (new WeatherIconMapper)->map(63)['condition']);
        $this->assertSame('Heavy Rain', (new WeatherIconMapper)->map(65)['condition']);
    }
}
