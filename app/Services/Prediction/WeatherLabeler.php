<?php

namespace App\Services\Prediction;

final class WeatherLabeler
{
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
