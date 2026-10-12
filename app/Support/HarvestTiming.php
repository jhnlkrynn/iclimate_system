<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Panel revision 1.3: On time / Early / Late for a recorded harvest against its expected harvest date, and the reason a
 * farmer gives for an early or late one. Tolerance and reasons: config/farm_calendar.php (harvest_timing).
 */
final class HarvestTiming
{
    public const ON_TIME = 'On time';

    public const EARLY = 'Early';

    public const LATE = 'Late';

    public const OTHER = 'other';

    public static function toleranceDays(): int
    {
        return max(0, (int) config('farm_calendar.harvest_timing.on_time_tolerance_days', 3));
    }

    /** @return array<string, string> reason key => English label */
    public static function reasons(): array
    {
        return (array) config('farm_calendar.harvest_timing.reasons', []);
    }

    /** On time / Early / Late, or null when either date is unknown. */
    public static function classify(mixed $expected, mixed $actual): ?string
    {
        $days = self::daysOff($expected, $actual);

        return match (true) {
            $days === null => null,
            $days < -self::toleranceDays() => self::EARLY,
            $days > self::toleranceDays() => self::LATE,
            default => self::ON_TIME,
        };
    }

    /** Actual minus expected, in whole days (negative = early), or null. */
    public static function daysOff(mixed $expected, mixed $actual): ?int
    {
        $e = self::date($expected);
        $a = self::date($actual);

        return $e && $a ? (int) $e->startOfDay()->diffInDays($a->startOfDay(), false) : null;
    }

    public static function needsReason(?string $timing): bool
    {
        return in_array($timing, [self::EARLY, self::LATE], true);
    }

    /** "Heavy rain / flooding", or the farmer's own words for "Other". */
    public static function reasonLabel(?string $reason, ?string $other = null, ?string $locale = null): ?string
    {
        if (! $reason) {
            return null;
        }
        if ($reason === self::OTHER) {
            return filled($other) ? trim((string) $other) : self::translatedReason(self::OTHER, $locale);
        }

        return self::translatedReason($reason, $locale);
    }

    /** "Early (12 days) – Heavy rain / flooding". */
    public static function summary(?string $timing, mixed $expected, mixed $actual, ?string $reason, ?string $other, ?string $locale = null): ?string
    {
        if (! $timing) {
            return null;
        }
        $days = self::daysOff($expected, $actual);
        $text = self::timingLabel($timing, $locale);
        if ($days !== null && $timing !== self::ON_TIME) {
            $text .= ' ('.abs($days).' '.(abs($days) === 1 ? self::word('day', $locale) : self::word('days', $locale)).')';
        }
        $why = self::reasonLabel($reason, $other, $locale);

        return $why ? $text.' – '.$why : $text;
    }

    public static function timingLabel(string $timing, ?string $locale = null): string
    {
        $key = 'farmer.harvest_timing.status.'.match ($timing) { self::EARLY => 'early', self::LATE => 'late', default => 'on_time' };

        return \Illuminate\Support\Facades\Lang::has($key, $locale) ? (string) __($key, [], $locale) : $timing;
    }

    /**
     * Validation for the reason fields. The reason is required when the harvest is early or late.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'harvest_timing_reason' => ['nullable', 'string', Rule::in(array_keys(self::reasons()))],
            'harvest_timing_reason_other' => ['nullable', 'string', 'max:255', 'required_if:harvest_timing_reason,'.self::OTHER],
        ];
    }

    /**
     * The columns to save for a harvest: timing, and the reason only when it is early or late. Throws when an early or
     * late harvest has no reason.
     *
     * @param  array<string, mixed>  $input  harvest_timing_reason, harvest_timing_reason_other
     * @return array{harvest_timing: ?string, harvest_timing_reason: ?string, harvest_timing_reason_other: ?string}
     */
    public static function attributes(mixed $expected, mixed $actual, array $input, string $errorKey = 'harvest_timing_reason', ?string $errorBag = null): array
    {
        $timing = self::classify($expected, $actual);
        $reason = $input['harvest_timing_reason'] ?? null;
        if (self::needsReason($timing) && blank($reason)) {
            $days = abs((int) self::daysOff($expected, $actual));
            $exception = ValidationException::withMessages([
                $errorKey => (string) __('farmer.harvest_timing.reason_required', [
                    'timing' => mb_strtolower(self::timingLabel((string) $timing)),
                    'days' => $days,
                    'expected' => self::date($expected)?->format('M j, Y'),
                ]),
            ]);
            throw $errorBag ? $exception->errorBag($errorBag) : $exception;
        }

        return [
            'harvest_timing' => $timing,
            'harvest_timing_reason' => self::needsReason($timing) ? $reason : null,
            'harvest_timing_reason_other' => self::needsReason($timing) && $reason === self::OTHER ? trim((string) ($input['harvest_timing_reason_other'] ?? '')) ?: null : null,
        ];
    }

    private static function translatedReason(string $reason, ?string $locale): string
    {
        $key = 'farmer.harvest_timing.reasons.'.$reason;

        return \Illuminate\Support\Facades\Lang::has($key, $locale) ? (string) __($key, [], $locale) : (string) (self::reasons()[$reason] ?? $reason);
    }

    private static function word(string $key, ?string $locale): string
    {
        $full = 'farmer.harvest_timing.'.$key;

        return \Illuminate\Support\Facades\Lang::has($full, $locale) ? (string) __($full, [], $locale) : $key;
    }

    private static function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy();
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return is_string($value) && $value !== '' ? rescue(fn () => Carbon::parse(substr($value, 0, 10)), null, false) : null;
    }
}
