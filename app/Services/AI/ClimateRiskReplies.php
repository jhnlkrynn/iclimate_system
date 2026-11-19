<?php

namespace App\Services\AI;

use App\Models\HeatmapArea;

/**
 * The one response contract for Climora's barangay climate-risk answers, shared
 * by AIOrchestratorService and RoleAssistantService so both paths classify a
 * rating the same way and say "no rating" with the same words in both languages.
 *
 *  - current: a calculated (non-placeholder) row computed within
 *    HeatmapArea::CURRENT_WINDOW_HOURS, and the live risk map does not report
 *    the barangay as No Data.
 *  - stale:   a calculated row exists but fails either condition above.
 *  - no_data: no calculated row at all. Placeholders never count, and no
 *    Low/Moderate/High level is ever invented for this status.
 */
final class ClimateRiskReplies
{
    public const CURRENT = 'current';

    public const STALE = 'stale';

    public const NO_DATA = 'no_data';

    /**
     * @param  HeatmapArea|null  $row  a row from HeatmapArea::computed() only
     * @param  array<string, mixed>|null  $liveDetail  BarangayRiskMapService::detailFor(), null when unavailable
     */
    public static function status(?HeatmapArea $row, ?array $liveDetail): string
    {
        return match (true) {
            $row === null => self::NO_DATA,
            ($liveDetail['risk_level'] ?? null) === 'No Data' => self::NO_DATA,
            $row->isCurrentlyComputed() => self::CURRENT,
            default => self::STALE,
        };
    }

    /** The no_data answer for one barangay, or for all of Lian when no barangay is in question. */
    public static function noData(string $language, bool $forBarangay = true): string
    {
        $tagalog = str_contains($language, 'Tagalog');

        if ($forBarangay) {
            return $tagalog
                ? 'Wala pang available na farm-risk rating para sa barangay na ito. Bantayan muna ang latest weather at advisories.'
                : 'No calculated farm-risk rating is available for this barangay yet. Check the latest weather and advisories.';
        }

        return $tagalog
            ? 'Wala pang available na farm-risk rating para sa mga barangay ngayon. Bantayan muna ang latest weather at advisories.'
            : 'No calculated farm-risk rating is available for any barangay right now. Check the latest weather and advisories.';
    }
}
