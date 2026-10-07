<?php

namespace App\Support;

use App\Models\Farm;

/**
 * A farm's size in the farmer's words (lang farms.size): a known size in hectares, a size measured from the farm map, an
 * estimate that always says "Estimated", or "Not added yet". An estimated range is never written as a measured number.
 */
final class FarmSize
{
    /** @return array{text: string, note: ?string, method: string} */
    public static function describe(Farm $farm): array
    {
        $method = in_array($farm->farm_size_method, Farm::SIZE_METHODS, true) ? $farm->farm_size_method : Farm::SIZE_UNKNOWN;
        $known = $farm->knownHectares();

        return match (true) {
            $known !== null && $method === Farm::SIZE_MAPPED => ['text' => self::hectares($known), 'note' => __('farms.size.measured'), 'method' => $method],
            $known !== null => ['text' => self::hectares($known), 'note' => null, 'method' => Farm::SIZE_EXACT],
            $farm->estimateRange() !== null => ['text' => __('farms.size.estimated_value', ['range' => self::rangeLabel((string) $farm->farm_size_estimate_range)]), 'note' => null, 'method' => Farm::SIZE_ESTIMATED],
            default => ['text' => __('farms.size.not_added'), 'note' => __('farms.size.add_later'), 'method' => $method === Farm::SIZE_MAPPED ? Farm::SIZE_MAPPED : Farm::SIZE_UNKNOWN],
        };
    }

    public static function hectares(float $value): string
    {
        return __('farms.size.hectares', ['value' => rtrim(rtrim(number_format($value, 2), '0'), '.') ?: '0']);
    }

    /** "0.5–1 ha" for an estimate range code. */
    public static function rangeLabel(string $code): string
    {
        return (string) __('farms.size.ranges_short.'.$code);
    }
}
