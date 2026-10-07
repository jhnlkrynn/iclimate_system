<?php

namespace App\Support;

final class CoordinateNormalizer
{
    private function __construct() {}

    /**
     * Use one stable decimal representation for provider URLs and cache keys.
     * Six decimal places preserve sub-metre distinctions between the shipped
     * barangay centroids while making values such as 120.650 and 120.65 equal.
     */
    public static function format(float|string $coordinate): string
    {
        return number_format((float) $coordinate, 6, '.', '');
    }

    public static function pair(float|string $latitude, float|string $longitude): string
    {
        return self::format($latitude).','.self::format($longitude);
    }
}
