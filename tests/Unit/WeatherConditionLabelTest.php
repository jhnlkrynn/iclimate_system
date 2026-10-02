<?php

namespace Tests\Unit;

use App\Support\WeatherConditionLabel;
use PHPUnit\Framework\TestCase;

class WeatherConditionLabelTest extends TestCase
{
    public function test_drizzle_wording_becomes_rain_wording(): void
    {
        $map = [
            'Drizzle' => 'Light Rain',
            'Light Drizzle' => 'Light Rain',
            'Moderate Drizzle' => 'Moderate Rain',
            'Heavy Drizzle' => 'Heavy Rain',
            'Dense drizzle' => 'Heavy Rain',
            'Light freezing drizzle' => 'Light Rain',
            'light drizzle expected' => 'light rain expected',
        ];

        foreach ($map as $in => $out) {
            $this->assertSame($out, WeatherConditionLabel::display($in), $in);
        }
    }

    public function test_other_text_and_empty_values_are_untouched(): void
    {
        $this->assertSame('Thunderstorm', WeatherConditionLabel::display('Thunderstorm'));
        $this->assertSame('Heavy Rain', WeatherConditionLabel::display('Heavy Rain'));
        $this->assertNull(WeatherConditionLabel::display(null));
        $this->assertSame('', WeatherConditionLabel::display(''));
    }
}
