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
}
