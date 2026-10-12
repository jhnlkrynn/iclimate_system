<?php

namespace App\Support;

/**
 * Panel revision 1.2: every yield is tons per hectare (t/ha), two decimals. A harvest entered as total tons, kilograms or
 * sacks is converted with config/yield.php:
 *
 *     yield (t/ha) = total harvest in tons ÷ area harvested in hectares
 *     1 sack = config('yield.sack_kg') kg (50 by default); 1 ton = 1,000 kg
 */
final class YieldUnit
{
    public const T_PER_HA = 't/ha';

    public const TONS = 'tons';

    public const KG = 'kg';

    public const SACKS = 'sacks';

    public static function label(): string
    {
        return (string) config('yield.unit', self::T_PER_HA);
    }

    public static function decimals(): int
    {
        return (int) config('yield.decimals', 2);
    }

    public static function sackKg(): float
    {
        return (float) config('yield.sack_kg', 50);
    }

    /** @return array<string, string> unit => label, for an input's unit selector */
    public static function inputUnits(): array
    {
        return (array) config('yield.input_units', [self::T_PER_HA => 'Tons per hectare (t/ha)']);
    }

    /** A total harvest in tons, from an amount in tons, kilograms or sacks; null for an unknown unit or amount. */
    public static function toTons(mixed $amount, string $unit): ?float
    {
        if (! is_numeric($amount) || (float) $amount < 0) {
            return null;
        }
        $amount = (float) $amount;

        return match ($unit) {
            self::TONS => $amount,
            self::KG => $amount / 1000,
            self::SACKS => $amount * self::sackKg() / 1000,
            default => null,
        };
    }

    /**
     * t/ha from an amount in any input unit. A t/ha amount is returned as it is; a total (tons, kg, sacks) is divided by
     * the hectares harvested. Null when it cannot be worked out (no area, zero area, unknown unit).
     */
    public static function toTonsPerHectare(mixed $amount, string $unit, mixed $hectares = null): ?float
    {
        if ($unit === self::T_PER_HA) {
            return is_numeric($amount) ? self::round((float) $amount) : null;
        }
        $tons = self::toTons($amount, $unit);
        if ($tons === null || ! is_numeric($hectares) || (float) $hectares <= 0) {
            return null;
        }

        return self::round($tons / (float) $hectares);
    }

    public static function round(?float $value): ?float
    {
        return $value === null ? null : round($value, self::decimals());
    }

    /** "4.25 t/ha", or the fallback when there is no value. */
    public static function format(mixed $value, string $fallback = '—'): string
    {
        return is_numeric($value) ? number_format((float) $value, self::decimals()).' '.self::label() : $fallback;
    }
}
