<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Business "now" and "today" for Lian, Batangas (Asia/Manila).
 *
 * Storage stays UTC (config app.timezone = UTC; some columns are timestamp
 * WITHOUT time zone, so the app timezone must not change). Anything that asks
 * "what day / month / season is it?" must use this class instead of now() /
 * today(), otherwise between 12:00 AM and 8:00 AM Manila time the app is still
 * on the previous (UTC) day.
 *
 * When a business boundary is used in a database comparison, use the *Utc()
 * variants: Laravel binds Carbon values as plain wall-clock strings, so a
 * Manila-zoned value would be read as UTC and be off by 8 hours.
 */
final class BusinessClock
{
    public static function timezone(): string
    {
        return LianLocation::TIMEZONE;
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** Midnight at the start of the current Manila day (Manila-zoned). */
    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    /** The same instant as today(), in UTC — for comparing against stored timestamps. */
    public static function todayUtc(): Carbon
    {
        return self::today()->utc();
    }

    /** Start of the Manila day $daysAgo days back, in UTC (rolling "last N days" aligned to Manila days). */
    public static function startOfDayUtc(int $daysAgo = 0): Carbon
    {
        return self::today()->subDays($daysAgo)->utc();
    }

    /**
     * SQL expression for the Manila calendar date of a stored timestamp column, for
     * "per day" grouping. Plain DATE(col) would group by the UTC date, so activity
     * between 12:00 AM and 8:00 AM Manila would land on the previous day.
     * Only for timestamptz columns (the audit/report tables); $column is a trusted identifier.
     */
    public static function sqlDate(string $column): string
    {
        return match (\Illuminate\Support\Facades\DB::connection()->getDriverName()) {
            'pgsql' => "DATE({$column} AT TIME ZONE '".self::timezone()."')",
            'sqlite' => "DATE({$column}, '+8 hours')",
            default => "DATE({$column})",
        };
    }

    public static function year(): int
    {
        return (int) self::now()->year;
    }

    public static function month(): int
    {
        return (int) self::now()->month;
    }
}
