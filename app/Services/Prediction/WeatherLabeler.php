<?php

namespace App\Services\Prediction;

final class WeatherLabeler
{
    /**
     * Keep these thresholds identical to python_scripts/rice_yield_service.py's
     * weather_label() function — there is no shared config between the two
     * languages, so a change here without the matching Python change will
     * silently make PHP and the ML service disagree on weather labels.
     */
    public static function fromRainfall(float $rainfall): string
    {
        return match (true) {
            $rainfall >= 300 => 'Heavy Rain',
            $rainfall >= 120 => 'Rain',
            $rainfall <= 70 => 'Dry',
            default => 'Cloudy',
        };
    }

    public static function fromTemperature(float $temperature): string
    {
        return match (true) {
            $temperature > 32 => 'Hot',
            $temperature < 24 => 'Cool',
            default => 'Favorable',
        };
    }
}
