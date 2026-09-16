<?php

namespace App\Services\Weather;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class MonthlyWeatherCalendarService
{
    public function build(CarbonImmutable $month, ?array $weather): array
    {
        $month = $month->startOfMonth();
        $forecastByDate = $this->forecastByDate($weather);
        $gridStart = $month->startOfWeek(CarbonInterface::SUNDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);
        $weeks = [];

        for ($cursor = $gridStart; $cursor->lte($gridEnd); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            $forecast = $forecastByDate[$date] ?? null;
            $weeks[(int) $cursor->weekOfYear][] = [
                'date' => $date,
                'day' => $cursor->day,
                'day_label' => $cursor->format('l, F j, Y'),
                'in_month' => $cursor->month === $month->month && $cursor->year === $month->year,
                'is_today' => $cursor->isToday(),
                'forecast' => $forecast,
                'risk' => $forecast ? $this->risk($forecast) : null,
            ];
        }

        $forecastCount = collect($forecastByDate)
            ->keys()
            ->filter(fn (string $date): bool => str_starts_with($date, $month->format('Y-m').'-'))
            ->count();

        return [
            'month' => $month,
            'label' => $month->format('F Y'),
            'previous_month' => $month->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $month->addMonthNoOverflow()->format('Y-m'),
            'weekdays' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'weeks' => array_values($weeks),
            'forecast_count' => $forecastCount,
            'source_name' => data_get($weather, 'source_name'),
            'source_url' => data_get($weather, 'source_url'),
            'fetched_at' => data_get($weather, 'fetched_at'),
            'is_cached_fallback' => (bool) data_get($weather, 'is_cached_fallback', false),
        ];
    }

    private function forecastByDate(?array $weather): array
    {
        if (! is_array($weather)) {
            return [];
        }

        $series = (array) data_get($weather, 'daily_series', []);
        $result = [];

        foreach ((array) ($series['labels'] ?? []) as $index => $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $result[$date] = [
                'condition' => $this->textAt($series, 'conditions', $index),
                'rainfall' => $this->numberAt($series, 'rainfall', $index),
                'temperature' => $this->numberAt($series, 'temperature', $index),
                'temperature_max' => $this->numberAt($series, 'temperatureMax', $index),
                'temperature_min' => $this->numberAt($series, 'temperatureMin', $index),
                'humidity' => $this->numberAt($series, 'humidity', $index),
                'wind' => $this->numberAt($series, 'windSpeed', $index),
                'rain_chance' => $this->numberAt($series, 'precipitationProbability', $index),
                'source_name' => (string) ($weather['source_name'] ?? 'Open-Meteo Forecast API'),
            ];
        }

        return $result;
    }

    private function risk(array $forecast): ?array
    {
        $rainfall = $forecast['rainfall'];
        $probability = $forecast['rain_chance'];
        $wind = $forecast['wind'];
        $condition = strtolower((string) ($forecast['condition'] ?? ''));

        if ($rainfall === null && $probability === null && $wind === null && $condition === '') {
            return null;
        }

        $level = match (true) {
            str_contains($condition, 'thunderstorm') || ($rainfall ?? 0) >= 50 || ($wind ?? 0) >= 62 => 'Severe',
            str_contains($condition, 'heavy') || ($rainfall ?? 0) >= 25 || ($probability ?? 0) >= 80 || ($wind ?? 0) >= 35 => 'High',
            ($rainfall ?? 0) >= 10 || ($probability ?? 0) >= 50 || ($wind ?? 0) >= 20 => 'Moderate',
            default => 'Low',
        };

        return [
            'level' => $level,
            'label' => $level.' daily planning risk',
            'class' => 'risk-'.strtolower($level),
        ];
    }

    private function numberAt(array $series, string $key, int $index): ?float
    {
        $value = $series[$key][$index] ?? null;

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function textAt(array $series, string $key, int $index): ?string
    {
        $value = $series[$key][$index] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
