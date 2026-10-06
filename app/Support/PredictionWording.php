<?php

namespace App\Support;

/**
 * Plain-language wording for the Predictions page (display only; no prediction
 * logic or model threshold lives here).
 */
final class PredictionWording
{
    /** Display-only yield status bands, as % change versus the previous yield. */
    public const HIGHER_AT_OR_ABOVE = 5.0;

    public const ABOUT_SAME_ABOVE = -5.0;

    public const SLIGHTLY_LOWER_ABOVE = -15.0;

    /** "High / Medium / Low" instead of a confidence percentage. */
    public static function reliability(mixed $percent): ?string
    {
        if (! is_numeric($percent)) {
            return null;
        }

        return match (true) {
            (float) $percent >= 75 => 'High',
            (float) $percent >= 50 => 'Medium',
            default => 'Low',
        };
    }

    /**
     * @return array{label: string, tone: string, difference: ?string}
     */
    public static function yieldStatus(float $current, ?float $previous): array
    {
        if ($previous === null || $previous <= 0) {
            return ['label' => 'No Comparison Yet', 'tone' => 'tone-amber', 'difference' => null];
        }

        $delta = $current - $previous;
        $percent = ($delta / $previous) * 100;
        $amount = number_format(abs($delta), 1);

        $difference = abs($delta) < 0.05
            ? 'About the same'
            : $amount.' tons/ha '.($delta > 0 ? 'higher' : 'lower');

        return match (true) {
            $percent >= self::HIGHER_AT_OR_ABOVE => ['label' => 'Higher', 'tone' => 'tone-green', 'difference' => $difference],
            $percent > self::ABOUT_SAME_ABOVE => ['label' => 'About the Same', 'tone' => 'tone-green', 'difference' => $difference],
            $percent > self::SLIGHTLY_LOWER_ABOVE => ['label' => 'Slightly Lower', 'tone' => 'tone-amber', 'difference' => $difference],
            default => ['label' => 'Lower', 'tone' => 'tone-red', 'difference' => $difference],
        };
    }

    /**
     * Maps a raw analytics error / state to farmer-safe text. The raw text is
     * never returned; callers log it and show it to IT only.
     *
     * @return array{title: string, detail: string}
     */
    public static function friendlyFailure(?string $raw = null, bool $notEnoughData = false): array
    {
        $text = strtolower((string) $raw);

        if ($notEnoughData || str_contains($text, 'not enough') || str_contains($text, 'insufficient')) {
            return [
                'title' => 'Not Enough Data',
                'detail' => 'We need more records before we can make this forecast. Please check again later.',
            ];
        }

        if (str_contains($text, 'unavailable yet') || str_contains($text, 'not available yet')) {
            return [
                'title' => 'Forecast Not Available Yet',
                'detail' => 'This forecast is not ready yet. Please check again later.',
            ];
        }

        if (str_starts_with($text, 'timed out')) {
            return [
                'title' => 'This Is Taking Longer Than Usual',
                'detail' => 'We could not finish your request in time. Please try again. If it happens again, tell the MAO office.',
            ];
        }

        return [
            'title' => 'Forecast Temporarily Unavailable',
            'detail' => 'Something went wrong on our side. Please try again in a few minutes.',
        ];
    }

    /** Rainfall-based plain condition for the model's monthly output. */
    public static function riskLabel(?string $level): ?string
    {
        if ($level === null || $level === '') {
            return null;
        }

        return match (strtolower($level)) {
            'severe' => 'Severe Farm Risk',
            'high' => 'High Farm Risk',
            'moderate' => 'Moderate Farm Risk',
            'low' => 'Low Farm Risk',
            default => ucfirst($level).' Farm Risk',
        };
    }
}
