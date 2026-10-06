<?php

namespace App\Support;

use App\Models\Report;

/**
 * Plain-language "what is this report for / when is it useful" copy per
 * report type, shown above the generated results so MAO staff know how to
 * read and use the report rather than just seeing raw rows and a count.
 */
class ReportTypeCatalog
{
    /** @return array{purpose: string, good_for: string} */
    public static function describe(string $typeKey): array
    {
        return self::ENTRIES[$typeKey] ?? [
            'purpose' => 'Shows the filtered rows currently stored for '.(Report::TYPES[$typeKey] ?? 'this report type').'.',
            'good_for' => 'Reviewing the underlying records for this filter.',
        ];
    }

    private const ENTRIES = [
        'climate_records' => [
            'purpose' => 'Lists the historical monthly weather from PAGASA Ambulong Station (Tanauan, Batangas), which iClimate uses as the weather reference for Lian, and the climate records entered by staff, for the selected date range and season. The two are summarized separately.',
            'good_for' => 'Checking recent weather trends, verifying a specific reading, or preparing the evidence behind a planting or irrigation advisory.',
        ],
        'rice_production' => [
            'purpose' => 'Lists recorded rice yield, area, and total production per barangay, season, and year.',
            'good_for' => 'Comparing barangay or season performance, spotting a declining yield trend, or supporting funding and assistance requests.',
        ],
        'farmer_registration' => [
            'purpose' => 'Lists registered farmer profiles matching the selected date range and barangay.',
            'good_for' => 'Tracking registration growth, verifying coverage in a barangay, or preparing a farmer headcount for a program.',
        ],
        'advisory' => [
            'purpose' => 'Lists planting, irrigation, and climate advisories issued to farmers, with their status and target barangay.',
            'good_for' => 'Auditing what guidance was sent out and when, or checking advisory coverage for a barangay.',
        ],
        'community_feed' => [
            'purpose' => 'Lists community feed posts and announcements published in the selected date range.',
            'good_for' => 'Reviewing what was communicated to farmers, or compiling an outreach/engagement record.',
        ],
    ];
}
