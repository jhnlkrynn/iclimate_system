<?php

namespace App\Support;

use App\Models\HeatmapArea;

final class HeatMapLegend
{
    public const INSUFFICIENT = 'Insufficient Data';

    private const LEVELS = [
        'Low' => [
            'label' => 'Low Risk',
            'color' => '#2c7bb6',
            'class' => 'risk-low',
            'minimum' => 0.00,
        ],
        'Moderate' => [
            'label' => 'Moderate Risk',
            'color' => '#fff34d',
            'class' => 'risk-moderate',
            'minimum' => 0.45,
        ],
        'High' => [
            'label' => 'High Risk',
            'color' => '#fdae21',
            'class' => 'risk-high',
            'minimum' => 0.68,
        ],
        'Severe' => [
            'label' => 'Critical/Severe Risk',
            'color' => '#d7191c',
            'class' => 'risk-severe',
            'minimum' => 0.85,
        ],
        self::INSUFFICIENT => [
            'label' => self::INSUFFICIENT,
            'color' => '#6b7280',
            'class' => 'risk-insufficient',
            'minimum' => null,
        ],
    ];

    public static function levels(): array
    {
        return self::LEVELS;
    }

    public static function riskLevels(): array
    {
        return ['Low', 'Moderate', 'High', 'Severe'];
    }

    public static function fromScore(null|float|int|string $score): array
    {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return self::forLevel(self::INSUFFICIENT);
        }

        $score = (float) $score;

        return self::forLevel(match (true) {
            $score >= self::LEVELS['Severe']['minimum'] => 'Severe',
            $score >= self::LEVELS['High']['minimum'] => 'High',
            $score >= self::LEVELS['Moderate']['minimum'] => 'Moderate',
            default => 'Low',
        });
    }

    public static function forLevel(?string $level): array
    {
        $normalized = self::normalizeLevel($level);

        return [
            'level' => $normalized,
            ...self::LEVELS[$normalized],
        ];
    }

    public static function fromArea(HeatmapArea|array $area): array
    {
        $score = is_array($area) ? ($area['risk_score'] ?? null) : $area->risk_score;
        $storedLevel = is_array($area) ? ($area['risk_level'] ?? null) : $area->risk_level;
        $classification = self::fromScore($score);

        if ($classification['level'] === self::INSUFFICIENT) {
            return $classification;
        }

        $stored = self::forLevel((string) $storedLevel);

        return $stored['level'] === $classification['level'] ? $stored : $classification;
    }

    public static function legendRows(bool $includeInsufficient = true): array
    {
        $levels = self::LEVELS;

        if (! $includeInsufficient) {
            unset($levels[self::INSUFFICIENT]);
        }

        return array_values(array_map(
            fn (string $level, array $config): array => ['level' => $level, ...$config],
            array_keys($levels),
            $levels,
        ));
    }

    public static function normalizeLevel(?string $level): string
    {
        $level = trim((string) $level);

        return array_key_exists($level, self::LEVELS) ? $level : self::INSUFFICIENT;
    }
}
