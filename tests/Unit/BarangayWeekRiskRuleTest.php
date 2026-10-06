<?php

namespace Tests\Unit;

use App\Services\Risk\BarangayWeekRiskService;
use PHPUnit\Framework\TestCase;

/** The Heat Map's weekly rule (BarangayWeekRiskService::rate): the worst of heavy rain, strong wind, heat and no rain. */
class BarangayWeekRiskRuleTest extends TestCase
{
    /** @param  array<int, array<string, float>>  $overrides  [day index => values] */
    private function days(int $count, array $overrides = []): array
    {
        return array_map(fn (int $i) => ($overrides[$i] ?? []) + ['date' => sprintf('2026-10-%02d', $i + 1), 'rainfall' => 4.0, 'wind_speed_max' => 12.0, 'temperature_max' => 31.0], range(0, $count - 1));
    }

    public function test_a_calm_week_is_low(): void
    {
        $r = BarangayWeekRiskService::rate($this->days(7));

        $this->assertSame(['low', 'calm', []], [$r['level'], $r['reason'], $r['reasons']]);
        $this->assertSame([7, 28.0, 4.0, 12.0, 31.0], [$r['days_covered'], $r['rain_total_mm'], $r['wettest_day_mm'], $r['wind_max_kmh'], $r['temp_max_c']]);
    }

    public function test_heavy_rain_days(): void
    {
        $one = BarangayWeekRiskService::rate($this->days(7, [2 => ['rainfall' => 25.0]]));
        $this->assertSame(['moderate', 'heavy_rain', ['2026-10-03']], [$one['level'], $one['reason'], $one['reasons'][0]['dates']]);

        $this->assertSame('moderate', BarangayWeekRiskService::rate($this->days(7, [1 => ['rainfall' => 30.0], 4 => ['rainfall' => 49.9]]))['level']);
        $this->assertSame('high', BarangayWeekRiskService::rate($this->days(7, [1 => ['rainfall' => 25.0], 2 => ['rainfall' => 25.0], 3 => ['rainfall' => 25.0]]))['level']);
        $this->assertSame('high', BarangayWeekRiskService::rate($this->days(7, [5 => ['rainfall' => 50.0]]))['level']);
        $this->assertSame('low', BarangayWeekRiskService::rate($this->days(7, [5 => ['rainfall' => 24.9]]))['level']);
    }

    public function test_strong_wind_and_heat(): void
    {
        $this->assertSame(['moderate', 'strong_wind'], array_values(array_intersect_key(BarangayWeekRiskService::rate($this->days(7, [0 => ['wind_speed_max' => 35.0]])), ['level' => 1, 'reason' => 1])));
        $this->assertSame('high', BarangayWeekRiskService::rate($this->days(7, [0 => ['wind_speed_max' => 62.0]]))['level']);
        $this->assertSame('low', BarangayWeekRiskService::rate($this->days(7, [0 => ['wind_speed_max' => 34.9]]))['level']);

        $hot = BarangayWeekRiskService::rate($this->days(7, [3 => ['temperature_max' => 35.0]]));
        $this->assertSame(['moderate', 'heat', 1], [$hot['level'], $hot['reason'], $hot['hot_days']]);
        $this->assertSame('high', BarangayWeekRiskService::rate($this->days(7, [1 => ['temperature_max' => 36.0], 2 => ['temperature_max' => 35.5], 3 => ['temperature_max' => 37.0]]))['level']);
    }

    public function test_no_rain_matters_only_for_rainfed_barangays_with_enough_days(): void
    {
        $dry = array_map(fn (array $d) => ['rainfall' => 0.2] + $d, $this->days(7));

        $this->assertSame(['moderate', 'dry'], [BarangayWeekRiskService::rate($dry, 'Rainfed')['level'], BarangayWeekRiskService::rate($dry, 'Rainfed')['reason']]);
        $this->assertSame('low', BarangayWeekRiskService::rate($dry, 'Irrigated')['level']);
        $this->assertSame('low', BarangayWeekRiskService::rate(array_slice($dry, 0, 4), 'Rainfed')['level']);
    }

    public function test_the_worst_hazard_leads_and_the_others_are_kept(): void
    {
        $r = BarangayWeekRiskService::rate($this->days(7, [1 => ['rainfall' => 30.0], 4 => ['wind_speed_max' => 80.0], 5 => ['temperature_max' => 35.0]]));

        $this->assertSame(['high', 'strong_wind'], [$r['level'], $r['reason']]);
        $this->assertSame(['strong_wind', 'heavy_rain', 'heat'], array_column($r['reasons'], 'key'));
    }

    public function test_no_days_or_no_numbers_is_no_data_never_low(): void
    {
        $this->assertSame('nodata', BarangayWeekRiskService::rate([])['level']);
        $this->assertSame('nodata', BarangayWeekRiskService::rate([['date' => '2026-10-01', 'rainfall' => null, 'wind_speed_max' => null, 'temperature_max' => null]])['level']);
    }
}
