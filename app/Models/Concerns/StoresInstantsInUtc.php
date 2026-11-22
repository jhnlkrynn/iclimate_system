<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * For datetime columns that callers fill with a Manila-zoned time (CarbonImmutable::now('Asia/Manila'), or an ISO
 * string ending in +08:00). Laravel saves a time's wall clock in the time's own zone, while every stored timestamp is
 * UTC (config app.timezone), so such a value was saved 8 hours ahead: all 6,498 yield_predictions.generated_at rows
 * on 2026-11-11, which made yield estimates look 8 hours fresher than they were.
 *
 * The listed columns are converted to the same instant in UTC before Laravel formats them. Only for full date-times:
 * a date-only column must not be converted (midnight in Manila is the previous day in UTC).
 *
 * Models list the columns in UTC_INSTANT_COLUMNS.
 */
trait StoresInstantsInUtc
{
    public function setAttribute($key, $value)
    {
        if (in_array($key, static::UTC_INSTANT_COLUMNS, true) && $value !== null && $value !== '') {
            $instant = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : (is_string($value) && preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value) ? rescue(fn () => Carbon::parse($value), null, fn (\Throwable $e): bool => \App\Support\SilentFailure::log($e, 'StoresInstantsInUtc::setAttribute')) : null);
            if ($instant !== null) {
                $value = $instant->copy()->setTimezone(config('app.timezone'));
            }
        }

        return parent::setAttribute($key, $value);
    }
}
