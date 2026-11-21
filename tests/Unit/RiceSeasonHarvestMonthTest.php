<?php

namespace Tests\Unit;

use App\Models\YieldPredictionRequest;
use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** 2026-11-20: every "this season" estimate is made for the season's harvest month, as a season request is. */
class RiceSeasonHarvestMonthTest extends TestCase
{
    public function test_each_month_maps_to_its_seasons_harvest_month(): void
    {
        $cases = [
            '2026-05-10' => '2026-09', '2026-09-30' => '2026-09', '2026-10-01' => '2026-10',   // harvest passed: the current month   // Wet May-Oct: September
            '2026-11-20' => '2027-03', '2026-12-31' => '2027-03', '2027-01-15' => '2027-03',   // Dry Nov-Apr: March of the end year
            '2027-03-01' => '2027-03', '2027-04-30' => '2027-04',
        ];
        foreach ($cases as $date => $harvest) {
            $this->assertSame($harvest, RiceSeason::harvestMonth(CarbonImmutable::parse($date))->format('Y-m'), $date);
        }
    }

    public function test_it_matches_a_season_request_while_the_harvest_is_ahead(): void
    {
        foreach (['2026-05-01' => 'Wet', '2026-08-01' => 'Wet', '2026-11-01' => 'Dry', '2027-02-01' => 'Dry', '2027-03-01' => 'Dry'] as $date => $season) {
            $today = CarbonImmutable::parse($date);
            $this->assertSame(YieldPredictionRequest::targetMonthFor($season, $today)->format('Y-m'), RiceSeason::harvestMonth($today)->format('Y-m'), $date);
        }
    }
}
