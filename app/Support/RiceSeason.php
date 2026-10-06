<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The one rice-season calendar of iClimate (decided 2026-10-06): Wet season May to October, Dry season November to
 * April. Pages, dashboards, reports, the calendar, risk advice and the monthly weather model all use it.
 *
 * One exception, on purpose: the deployed rice-yield model was trained on a dataset whose "Season" column used
 * May to September as wet. Its inputs must be encoded the way it was trained, so they use forTrainedYieldModel()
 * until the model is retrained with this calendar (then drop that method and use forMonth()).
 */
final class RiceSeason
{
    public const WET = 'Wet';

    public const DRY = 'Dry';

    /** Wet season months, May to October. */
    public const WET_MONTHS = [5, 6, 7, 8, 9, 10];

    /** The wet months of the deployed rice-yield model's training data (May to September). */
    private const TRAINED_YIELD_MODEL_WET_MONTHS = [5, 6, 7, 8, 9];

    private function __construct() {}

    public static function forMonth(int $month): string
    {
        return in_array($month, self::WET_MONTHS, true) ? self::WET : self::DRY;
    }

    public static function forDate(CarbonInterface $date): string
    {
        return self::forMonth((int) $date->month);
    }

    public static function current(): string
    {
        return self::forMonth(BusinessClock::month());
    }

    /**
     * The season that contains $date, with its first and last day: Wet = May 1 to Oct 31; Dry = Nov 1 to Apr 30
     * (crossing the new year).
     *
     * @return array{season: string, start: \Carbon\CarbonImmutable, end: \Carbon\CarbonImmutable}
     */
    public static function window(CarbonInterface $date): array
    {
        $date = \Carbon\CarbonImmutable::instance($date);
        if (self::forMonth((int) $date->month) === self::WET) {
            return ['season' => self::WET, 'start' => $date->setDate($date->year, 5, 1)->startOfDay(), 'end' => $date->setDate($date->year, 10, 31)->endOfDay()];
        }
        $startYear = $date->month >= 11 ? $date->year : $date->year - 1;

        return ['season' => self::DRY, 'start' => $date->setDate($startYear, 11, 1)->startOfDay(), 'end' => $date->setDate($startYear + 1, 4, 30)->endOfDay()];
    }

    /** Input encoding for the deployed rice-yield model only (see the class note). */
    public static function forTrainedYieldModel(int $month): string
    {
        return in_array($month, self::TRAINED_YIELD_MODEL_WET_MONTHS, true) ? self::WET : self::DRY;
    }
}
