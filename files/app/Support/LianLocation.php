<?php

namespace App\Support;

/**
 * The ONE definition of where iClimate operates. config/iclimate.php and
 * config/services.php read from here; nothing else should hardcode these.
 *
 * Only the municipality-level reference point lives here. Other coordinates
 * are different things and stay separate on purpose:
 *   - barangay centroids  -> App\Support\LianBarangays (polygon centroids of the real GeoJSON)
 *   - farm boundaries     -> per-farm, stored in the database
 *   - Ambulong station    -> the climate dataset's observation site (not Lian; not a Lian point)
 *   - map bounds          -> FarmBoundaryController (a validation box, not a point)
 */
final class LianLocation
{
    public const MUNICIPALITY = 'Lian';

    public const PROVINCE = 'Batangas';

    public const COUNTRY = 'Philippines';

    public const TIMEZONE = 'Asia/Manila';

    /**
     * Municipal reference point (Lian poblacion area), used for municipality-wide
     * weather requests and default map centring.
     *
     * Source check: Wikipedia lists Lian, Batangas at 14°02′N 120°39′E (14.03, 120.65);
     * this value agrees to that precision. It is a reference point, not an official
     * PSA/NAMRIA survey point — confirm with the researchers if an official one is required.
     */
    public const LATITUDE = 14.033;

    public const LONGITUDE = 120.650;

    public static function label(): string
    {
        return self::MUNICIPALITY.', '.self::PROVINCE;
    }

    /** @return array{0: float, 1: float} [latitude, longitude] */
    public static function point(): array
    {
        return [self::LATITUDE, self::LONGITUDE];
    }
}
