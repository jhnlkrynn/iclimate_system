<?php

namespace App\Support;

/**
 * The one place that turns weather-condition wording into what users see.
 *
 * "Drizzle" is not clear to most users, so it is always shown as plain "Rain" wording. Only the
 * DISPLAY text changes: stored/API values (including "drizzle") are never rewritten, and matching
 * code that reads provider text can keep recognising "drizzle".
 *
 *   Light Drizzle / Drizzle    -> Light Rain
 *   Moderate Drizzle           -> Moderate Rain
 *   Heavy / Dense Drizzle      -> Heavy Rain
 */
final class WeatherConditionLabel
{
    public static function display(?string $condition): ?string
    {
        if ($condition === null || trim($condition) === '') {
            return $condition;
        }

        return preg_replace_callback(
            '/\b(?:(light|slight|moderate|heavy|dense)\s+)?(?:freezing\s+)?drizzle\b/i',
            function (array $m): string {
                $label = match (strtolower($m[1] ?? '')) {
                    'moderate' => 'Moderate Rain',
                    'heavy', 'dense' => 'Heavy Rain',
                    default => 'Light Rain',
                };

                // Keep sentence-style lowercase text lowercase ("light drizzle expected").
                return ctype_lower($m[0][0]) ? strtolower($label) : $label;
            },
            $condition
        );
    }
}
