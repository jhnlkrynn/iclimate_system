<?php

namespace App\Services\Weather;

class WeatherIconMapper
{
    /**
     * @return array{condition: string, icon: string}
     */
    public function map(?int $code, ?bool $isDay = true): array
    {
        $daySuffix = $isDay === false ? 'night' : 'day';

        return match (true) {
            $code === 0 => ['condition' => $isDay === false ? 'Clear Night' : 'Clear', 'icon' => "/images/weather/clear-{$daySuffix}.svg"],
            $code === 1 => ['condition' => $isDay === false ? 'Mostly Clear' : 'Mainly Clear', 'icon' => "/images/weather/clear-{$daySuffix}.svg"],
            $code === 2 => ['condition' => 'Partly Cloudy', 'icon' => "/images/weather/partly-cloudy-{$daySuffix}.svg"],
            $code === 3 => ['condition' => 'Overcast', 'icon' => '/images/weather/overcast.svg'],
            in_array($code, [45, 48], true) => ['condition' => 'Foggy', 'icon' => '/images/weather/fog.svg'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['condition' => $this->intensity($code, 'Drizzle'), 'icon' => '/images/weather/light-rain.svg'],
            in_array($code, [61, 63, 65, 66, 67], true) => ['condition' => $this->intensity($code, 'Rain'), 'icon' => $code >= 65 ? '/images/weather/heavy-rain.svg' : '/images/weather/rain.svg'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['condition' => 'Rain Showers', 'icon' => '/images/weather/rain.svg'],
            in_array($code, [80, 81, 82], true) => ['condition' => $code === 82 ? 'Heavy Rain Showers' : 'Rain Showers', 'icon' => $code === 82 ? '/images/weather/heavy-rain.svg' : '/images/weather/rain.svg'],
            in_array($code, [95, 96, 99], true) => ['condition' => 'Thunderstorm', 'icon' => '/images/weather/thunderstorm.svg'],
            default => ['condition' => 'Weather Temporarily Unavailable', 'icon' => '/images/weather/unavailable.svg'],
        };
    }

    private function intensity(?int $code, string $label): string
    {
        return match ($code) {
            51, 56, 61, 66 => 'Light '.$label,
            53, 63 => 'Moderate '.$label,
            55, 57, 65, 67 => 'Heavy '.$label,
            default => $label,
        };
    }
}
