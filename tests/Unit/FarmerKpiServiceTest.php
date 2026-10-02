<?php

namespace Tests\Unit;

use App\Services\Analytics\FarmerKpiService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Each KPI on the Farmer dashboard against a hand calculation. "Missing" must show "—" and a reason, never 0.
 */
class FarmerKpiServiceTest extends TestCase
{
    private FarmerKpiService $kpi;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kpi = new FarmerKpiService;
        $this->today = CarbonImmutable::parse('2026-10-02 21:00', 'Asia/Manila');
    }

    /** @param  array<int, float|null>  $values  rain per day, starting at $from */
    private function rain(string $from, array $values): array
    {
        $daily = [];
        foreach ($values as $i => $mm) {
            if ($mm !== null) {
                $daily[CarbonImmutable::parse($from)->addDays($i)->toDateString()] = $mm;
            }
        }

        return $daily;
    }

    private function day(string $date, ?float $rain = null, ?float $chance = null, ?float $wind = null): array
    {
        return ['date' => CarbonImmutable::parse($date, 'Asia/Manila'), 'rain' => $rain, 'chance' => $chance, 'wind_kmh' => $wind];
    }

    // ------------------------------------------------------------------ rain in the last 7 days

    public function test_rain_last_7_days_adds_the_seven_full_days_before_today(): void
    {
        // Sep 25 .. Oct 1 = 2 + 0 + 0 + 10.5 + 3 + 0 + 1.5 = 17 mm. Today (Oct 2, still going) is left out even though it has rain.
        $daily = $this->rain('2026-09-25', [2.0, 0.0, 0.0, 10.5, 3.0, 0.0, 1.5, 40.0]);
        // The week before (Sep 18 .. Sep 24): 8.5 mm.
        $daily += $this->rain('2026-09-18', [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 2.5]);

        $r = $this->kpi->rainLast7Days($daily, $this->today);

        $this->assertSame(17.0, $r['value']);
        $this->assertSame('17', $r['display']);
        $this->assertSame('mm', $r['unit']);
        $this->assertNull($r['reason']);
        $this->assertSame(8.5, $r['previous']);
        $this->assertEqualsWithDelta(100.0, $r['change_percent'], 0.001);
        $this->assertSame('moderate', $r['level']);
    }

    public function test_a_dry_week_is_zero_mm_not_a_dash(): void
    {
        $r = $this->kpi->rainLast7Days($this->rain('2026-09-25', array_fill(0, 7, 0.0)), $this->today);

        $this->assertSame(0.0, $r['value']);
        $this->assertSame('0.0', $r['display']);          // measured as zero
        $this->assertSame('none', $r['level']);
    }

    public function test_one_missing_day_makes_the_week_unknown_never_a_smaller_total(): void
    {
        $r = $this->kpi->rainLast7Days($this->rain('2026-09-25', [2.0, 1.0, null, 4.0, 1.0, 0.0, 3.0]), $this->today);

        $this->assertNull($r['value']);
        $this->assertSame('—', $r['display']);
        $this->assertStringContainsString('6 of the last 7 days', $r['reason']);
    }

    public function test_no_history_at_all_is_a_dash_with_a_reason(): void
    {
        $r = $this->kpi->rainLast7Days([], $this->today);

        $this->assertNull($r['value']);
        $this->assertSame('—', $r['display']);
        $this->assertNotEmpty($r['reason']);
    }

    public function test_rain_rounding_is_one_decimal_under_10_and_whole_above(): void
    {
        $this->assertSame('5.9', FarmerKpiService::formatRain(5.94));
        $this->assertSame('9.9', FarmerKpiService::formatRain(9.94));
        $this->assertSame('12', FarmerKpiService::formatRain(12.4));
        $this->assertSame('13', FarmerKpiService::formatRain(12.5));
        $this->assertSame('28.5', FarmerKpiService::formatTemperature(28.46));
        $this->assertSame('120', FarmerKpiService::formatPercent(119.6));
    }

    // ------------------------------------------------------------------ rain expected in the next 7 days

    public function test_rain_next_7_days_sums_the_forecast_from_today_on(): void
    {
        $days = [
            $this->day('2026-10-01', 50.0),   // yesterday: not counted
            $this->day('2026-10-02', 5.9), $this->day('2026-10-03', 0.0), $this->day('2026-10-04', 12.0),
            $this->day('2026-10-05', 0.0), $this->day('2026-10-06', 3.1),
        ];

        $r = $this->kpi->rainNext7Days($days, $this->today);

        $this->assertEqualsWithDelta(21.0, $r['value'], 0.0001);
        $this->assertSame('21', $r['display']);
        $this->assertSame(5, $r['days']);                 // five days of weather coming, and the card says so
    }

    public function test_days_beyond_a_week_are_not_counted(): void
    {
        $days = [$this->day('2026-10-02', 1.0), $this->day('2026-10-09', 100.0)];

        $this->assertSame(1.0, $this->kpi->rainNext7Days($days, $this->today)['value']);
    }

    public function test_a_forecast_day_without_a_rain_amount_makes_it_unknown(): void
    {
        $r = $this->kpi->rainNext7Days([$this->day('2026-10-02', 2.0), $this->day('2026-10-03', null)], $this->today);

        $this->assertNull($r['value']);
        $this->assertSame('—', $r['display']);
    }

    public function test_no_forecast_is_a_dash(): void
    {
        $this->assertSame('—', $this->kpi->rainNext7Days([], $this->today)['display']);
    }

    // ------------------------------------------------------------------ rain compared with normal

    public function test_rain_compared_with_normal_uses_month_to_date_over_the_normal_for_the_same_days(): void
    {
        // Oct 11: ten full days (Oct 1-10). The normal for October is 310 mm over 31 days, so 100 mm for 10 days.
        $today = CarbonImmutable::parse('2026-10-11 08:00', 'Asia/Manila');

        $more = $this->kpi->rainComparedWithNormal($this->rain('2026-10-01', array_fill(0, 10, 13.0)), 310.0, $today);   // 130 mm
        $this->assertEqualsWithDelta(130.0, $more['value'], 0.0001);
        $this->assertSame('130', $more['display']);
        $this->assertSame('%', $more['unit']);
        $this->assertSame('more', $more['level']);
        $this->assertSame('More than usual', $more['label']);
        $this->assertEqualsWithDelta(100.0, $more['normal_to_date'], 0.0001);
        $this->assertEqualsWithDelta(130.0, $more['month_to_date'], 0.0001);

        $less = $this->kpi->rainComparedWithNormal($this->rain('2026-10-01', array_fill(0, 10, 7.0)), 310.0, $today);    // 70 mm
        $this->assertSame('less', $less['level']);
        $this->assertSame('Less than usual', $less['label']);
    }

    public function test_the_plus_or_minus_20_percent_band_is_normal(): void
    {
        $today = CarbonImmutable::parse('2026-10-11 08:00', 'Asia/Manila');

        foreach ([[8.0, 'normal'], [12.0, 'normal'], [7.9, 'less'], [12.1, 'more']] as [$perDay, $level]) {
            $r = $this->kpi->rainComparedWithNormal($this->rain('2026-10-01', array_fill(0, 10, $perDay)), 310.0, $today);
            $this->assertSame($level, $r['level'], "$perDay mm a day");
        }
    }

    public function test_the_band_is_read_from_the_config_file(): void
    {
        config(['farmer_dashboard.rain_normal_band_percent' => 50]);
        $today = CarbonImmutable::parse('2026-10-11 08:00', 'Asia/Manila');

        $r = $this->kpi->rainComparedWithNormal($this->rain('2026-10-01', array_fill(0, 10, 13.0)), 310.0, $today);   // 130%

        $this->assertSame('normal', $r['level']);
    }

    public function test_rain_compared_with_normal_is_a_dash_when_an_input_is_missing(): void
    {
        $today = CarbonImmutable::parse('2026-10-11 08:00', 'Asia/Manila');
        $full = $this->rain('2026-10-01', array_fill(0, 10, 5.0));

        $this->assertSame('—', $this->kpi->rainComparedWithNormal($full, null, $today)['display']);          // no long-term average
        $this->assertSame('—', $this->kpi->rainComparedWithNormal($full, 0.0, $today)['display']);
        $this->assertSame('—', $this->kpi->rainComparedWithNormal($this->rain('2026-10-01', [5.0, 5.0]), 310.0, $today)['display']);   // days missing
        $this->assertSame('—', $this->kpi->rainComparedWithNormal([], 310.0, CarbonImmutable::parse('2026-10-01 09:00', 'Asia/Manila'))['display']);   // no full day yet
    }

    // ------------------------------------------------------------------ heat

    public function test_pagasa_heat_levels(): void
    {
        $cases = [
            [26.4, 'normal'], [27.0, 'caution'], [32.4, 'caution'], [32.6, 'extreme_caution'], [33.0, 'extreme_caution'],
            [41.4, 'extreme_caution'], [41.5, 'danger'], [42.0, 'danger'], [51.4, 'danger'], [51.5, 'extreme_danger'], [60.0, 'extreme_danger'],
        ];
        foreach ($cases as [$heat, $level]) {
            $this->assertSame($level, $this->kpi->heatLevel($heat), "heat $heat");
        }
    }

    public function test_heat_index_matches_the_standard_formula(): void
    {
        $this->assertEqualsWithDelta(41.07, FarmerKpiService::heatIndex(32.2222, 70), 0.05);   // 90 °F at 70% feels like about 106 °F
        $this->assertEqualsWithDelta(32.83, FarmerKpiService::heatIndex(30.0, 60), 0.05);
        $this->assertSame(26.0, FarmerKpiService::heatIndex(26.0, 90));                         // under 27 °C the plain temperature is used
    }

    public function test_heat_today_prefers_the_providers_value_then_calculates_then_gives_up(): void
    {
        $fromProvider = $this->kpi->heatToday(36.2, 33.0, 60.0);
        $this->assertSame(36.2, $fromProvider['value']);
        $this->assertSame('provider', $fromProvider['basis']);
        $this->assertSame('extreme_caution', $fromProvider['level']);
        $this->assertSame('Extreme Caution', $fromProvider['label']);
        $this->assertSame('36.2', $fromProvider['display']);

        $calculated = $this->kpi->heatToday(null, 30.0, 60.0);
        $this->assertEqualsWithDelta(32.83, $calculated['value'], 0.05);
        $this->assertSame('calculated', $calculated['basis']);
        $this->assertSame('extreme_caution', $calculated['level']);       // 32.83 rounds to 33

        $unknown = $this->kpi->heatToday(null, 30.0, null);
        $this->assertNull($unknown['value']);
        $this->assertSame('—', $unknown['display']);
        $this->assertNotEmpty($unknown['reason']);
    }

    public function test_heat_levels_come_from_the_config_file(): void
    {
        config(['farmer_dashboard.heat_levels.caution' => 30]);

        $this->assertSame('normal', $this->kpi->heatLevel(29.0));
    }

    // ------------------------------------------------------------------ good work days

    public function test_good_work_days_need_under_40_percent_rain_chance_and_wind_under_15(): void
    {
        $days = [
            $this->day('2026-10-02', 0.0, 39.0, 14.9),    // good
            $this->day('2026-10-03', 0.0, 40.0, 5.0),     // chance is not under 40
            $this->day('2026-10-04', 0.0, 10.0, 15.0),    // wind is not under 15
            $this->day('2026-10-05', 0.0, 0.0, 0.0),      // good
            $this->day('2026-10-06', 0.0, null, 4.0),     // cannot be judged
            $this->day('2026-10-07', 30.0, 90.0, 30.0),
            $this->day('2026-10-01', 0.0, 0.0, 0.0),      // yesterday: not part of the week
        ];

        $r = $this->kpi->goodWorkDays($days, $this->today);

        $this->assertSame(2, $r['value']);
        $this->assertSame('2', $r['display']);
        $this->assertSame(['2026-10-02', '2026-10-05'], $r['dates']);
        $this->assertSame(5, $r['judged']);
    }

    public function test_zero_good_days_is_a_real_zero_but_no_judgeable_day_is_a_dash(): void
    {
        $bad = $this->kpi->goodWorkDays([$this->day('2026-10-02', 20.0, 95.0, 30.0)], $this->today);
        $this->assertSame(0, $bad['value']);
        $this->assertSame('0', $bad['display']);

        $unknown = $this->kpi->goodWorkDays([$this->day('2026-10-02', 1.0, null, null)], $this->today);
        $this->assertNull($unknown['value']);
        $this->assertSame('—', $unknown['display']);
        $this->assertNotEmpty($unknown['reason']);
    }

    public function test_the_good_day_limits_come_from_the_config_file(): void
    {
        config(['farmer_dashboard.good_work_day' => ['max_rain_chance_percent' => 60, 'max_wind_kmh' => 25]]);

        $this->assertSame(1, $this->kpi->goodWorkDays([$this->day('2026-10-02', 0.0, 50.0, 20.0)], $this->today)['value']);
    }

    // ------------------------------------------------------------------ crop age and stage

    public function test_crop_age_is_today_minus_the_planting_date(): void
    {
        $r = $this->kpi->cropAge(CarbonImmutable::parse('2026-08-18', 'Asia/Manila'), $this->today);

        $this->assertSame(45, $r['value']);
        $this->assertSame('45', $r['display']);
        $this->assertSame(115, $r['duration']);                      // the configured default season length
        $this->assertSame('growing', $r['stage']);
        $this->assertSame('Growing', $r['stage_label']);
        $this->assertCount(6, $r['stages']);
        $this->assertSame(['Land preparation', 'Planting', 'Growing', 'Flowering', 'Ripening', 'Harvest'], array_column($r['stages'], 'label'));
        $this->assertSame(['done', 'done', 'current', 'upcoming', 'upcoming', 'upcoming'], array_column($r['stages'], 'state'));
    }

    public function test_the_stage_follows_the_season_length(): void
    {
        $planting = CarbonImmutable::parse('2026-01-01', 'Asia/Manila');
        // 115 days: planting 0-6, growing 7-57, flowering 58-77, ripening 78-100, harvest 101+.
        $cases = [0 => 'planting', 6 => 'planting', 7 => 'growing', 57 => 'growing', 58 => 'flowering', 77 => 'flowering', 78 => 'ripening', 100 => 'ripening', 101 => 'harvest', 115 => 'harvest', 130 => 'harvest'];
        foreach ($cases as $day => $stage) {
            $r = $this->kpi->cropAge($planting, $planting->addDays($day)->setTime(12, 0));
            $this->assertSame($stage, $r['stage'], "day $day");
        }

        // A 100-day variety reaches harvest sooner: growing ends at day 50 (0.50 x 100).
        $this->assertSame('flowering', $this->kpi->cropAge($planting, $planting->addDays(50), 100)['stage']);
        $this->assertSame(100, $this->kpi->cropAge($planting, $planting->addDays(50), 100)['duration']);
    }

    public function test_a_future_planting_date_is_not_day_zero_and_no_date_is_a_dash(): void
    {
        $future = $this->kpi->cropAge(CarbonImmutable::parse('2026-10-12', 'Asia/Manila'), $this->today);
        $this->assertNull($future['value']);
        $this->assertSame('—', $future['display']);
        $this->assertStringContainsString('10 days', $future['reason']);
        $this->assertSame('land_prep', $future['stage']);

        $none = $this->kpi->cropAge(null, $this->today);
        $this->assertNull($none['value']);
        $this->assertSame('—', $none['display']);
    }

    // ------------------------------------------------------------------ expected harvest and alerts

    public function test_the_harvest_range_is_the_models_own_spread_and_never_made_up(): void
    {
        $this->assertSame(['low' => 3.8, 'high' => 4.6], $this->kpi->harvestRange(4.2, 0.4));
        $this->assertSame(['low' => 0.0, 'high' => 1.5], $this->kpi->harvestRange(0.5, 1.0));    // never below zero
        $this->assertNull($this->kpi->harvestRange(4.2, null));
        $this->assertNull($this->kpi->harvestRange(4.2, 0.0));
    }

    public function test_alerts_zero_is_a_real_zero(): void
    {
        $this->assertSame(0, $this->kpi->activeAlerts(0)['value']);
        $this->assertSame('0', $this->kpi->activeAlerts(0)['display']);
        $this->assertSame('3', $this->kpi->activeAlerts(3)['display']);
    }
}
