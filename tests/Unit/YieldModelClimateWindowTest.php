<?php

namespace Tests\Unit;

use App\Models\ClimateRecord;
use App\Models\MonthlyWeatherPrediction;
use App\Support\YieldModelClimateWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verification 2026-11-18: the model's climate inputs follow the target month instead of "the latest six on file". */
class YieldModelClimateWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        YieldModelClimateWindow::forget();
    }

    private function station(string $month): object
    {
        return YieldModelClimateWindow::history()[$month];
    }

    public function test_windows_are_anchored_on_the_target_month_and_never_read_it_or_later(): void
    {
        $a = YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-15'), ['tmax' => 31.0, 'tmin' => 24.0, 'rainfall' => 300.0, 'temperature' => 28.0]);
        $b = YieldModelClimateWindow::features(CarbonImmutable::parse('2025-03-15'), ['tmax' => 32.0, 'tmin' => 23.5]);

        $this->assertSame(['2024-07', '2024-06', '2024-05', '2024-04', '2024-03', '2024-02'], $a['months_used']);
        $this->assertSame([], $a['missing_months']);
        $this->assertSame(round($this->station('2024-07')->rainfall, 2), $a['features']['previous_rainfall'], 'previous = T-1');
        $this->assertNotSame($a['features'], $b['features'], 'different target months give different inputs');
        $this->assertNotContains('2024-08', $a['months_used'], 'the target month is not a lag');

        $sixMean = collect(['2024-07', '2024-06', '2024-05', '2024-04', '2024-03', '2024-02'])->avg(fn ($m) => $this->station($m)->rainfall);
        $this->assertEqualsWithDelta($sixMean, $a['features']['rainfall_6m'], 0.01, 'a six-month mean, as trained');
        $this->assertSame(7.0, $a['features']['temp_range'], 'TMAX - TMIN of the target month');

        // Whole Wet season May-Sep: observed before T, the target month's own weather for T, observed Sep (already past).
        $this->assertSame(['2024-05', '2024-06', '2024-07', '2024-08', '2024-09'], $a['seasonal_months']);
        $this->assertSame([], $a['seasonal_forecast_months']);
        $total = collect(['2024-05', '2024-06', '2024-07', '2024-09'])->sum(fn ($m) => $this->station($m)->rainfall) + 300.0;
        $this->assertEqualsWithDelta($total, $a['features']['seasonal_rainfall'], 0.01, 'a whole-season total, as trained');
    }

    public function test_a_dry_season_spans_the_year_end(): void
    {
        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2025-01-10'), ['rainfall' => 40.0, 'temperature' => 26.5]);
        $this->assertSame(['2024-10', '2024-11', '2024-12', '2025-01', '2025-02', '2025-03', '2025-04'], $r['seasonal_months']);
        $this->assertNotNull($r['features']['seasonal_rainfall']);
    }

    public function test_remaining_season_months_use_the_stored_forecast_and_are_flagged(): void
    {
        foreach (['2026-05' => [150, 28.9], '2026-06' => [220, 28.5], '2026-07' => [300, 28.1], '2026-08' => [330, 28.0]] as $ym => [$rain, $t]) {
            ClimateRecord::query()->create(['record_date' => $ym.'-01', 'rainfall' => $rain, 'temperature' => $t, 'humidity' => 82, 'wind_speed' => 2.5, 'season' => 'Wet', 'source' => 'PAGASA Ambulong (entered)']);
        }
        MonthlyWeatherPrediction::query()->create(['prediction_year' => 2026, 'prediction_month' => 9, 'rainfall' => 234.0, 'temperature_average' => 27.65, 'weather_model' => 'test', 'status' => 'ready', 'generated_at' => now()]);
        MonthlyWeatherPrediction::query()->create(['prediction_year' => 2026, 'prediction_month' => 9, 'weather_model' => 'unready', 'status' => 'unready', 'rainfall' => 1.0, 'temperature_average' => 1.0, 'generated_at' => now()->addMinute()]);
        YieldModelClimateWindow::forget();

        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2026-08-15'), ['rainfall' => 330.0, 'temperature' => 28.0]);
        $this->assertSame(['2026-09'], $r['seasonal_forecast_months'], 'the forecast month is reported');
        $this->assertSame(1234.0, $r['features']['seasonal_rainfall']);   // 150+220+300+330+234

        MonthlyWeatherPrediction::query()->delete();
        $none = YieldModelClimateWindow::features(CarbonImmutable::parse('2026-08-15'), ['rainfall' => 330.0, 'temperature' => 28.0]);
        $this->assertNull($none['features']['seasonal_rainfall'], 'no forecast: missing, not a partial total');
        $this->assertSame(['2026-09'], $none['seasonal_missing_months']);
    }

    public function test_months_that_were_not_observed_stay_missing_instead_of_being_replaced(): void
    {
        // The station record ends in 2025-12; a target in 2026-11 has no observed lag months.
        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2026-11-01'), ['tmax' => 31.0, 'tmin' => 24.0]);

        $this->assertSame(['2026-10', '2026-09', '2026-08', '2026-07', '2026-06', '2026-05'], $r['missing_months']);
        foreach (['previous_rainfall', 'previous_temp', 'rainfall_6m', 'temp_3m', 'temp_6m', 'seasonal_rainfall', 'seasonal_temp'] as $k) {
            $this->assertNull($r['features'][$k], $k.' is missing, not a value from another month');
        }
        $this->assertNull(YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-01'))['features']['temp_range'], 'no tmax/tmin: missing');
    }

    public function test_the_target_month_and_later_months_never_change_the_lag_inputs(): void
    {
        $lagKeys = ['previous_rainfall', 'previous_temp', 'rainfall_6m', 'temp_3m', 'temp_6m'];
        $before = array_intersect_key(YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-15'))['features'], array_flip($lagKeys));

        // Overwrite the target month and the month after it with extreme entered values.
        foreach (['2024-08-01', '2024-09-01'] as $date) {
            ClimateRecord::query()->create(['record_date' => $date, 'rainfall' => 2500.0, 'temperature' => 40.0, 'humidity' => 99, 'wind_speed' => 9, 'season' => 'Wet', 'source' => 'leakage probe']);
        }
        YieldModelClimateWindow::forget();
        $after = YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-15'));

        $this->assertSame($before, array_intersect_key($after['features'], array_flip($lagKeys)), 'no lag reads T or later');
        $this->assertNotContains('2024-08', $after['months_used']);
        $this->assertNotContains('2024-09', $after['months_used']);
    }

    public function test_an_observed_later_month_is_used_instead_of_its_forecast(): void
    {
        MonthlyWeatherPrediction::query()->create(['prediction_year' => 2024, 'prediction_month' => 9, 'rainfall' => 1.0, 'temperature_average' => 1.0, 'weather_model' => 'test', 'status' => 'ready', 'generated_at' => now()]);

        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-15'), ['rainfall' => 300.0, 'temperature' => 28.0]);
        $this->assertSame([], $r['seasonal_forecast_months'], 'September 2024 was observed: the forecast is not used');
        $this->assertGreaterThan(100, $r['features']['seasonal_rainfall']);
        $this->assertNull(YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-15'))['features']['seasonal_rainfall'], 'no target-month weather: missing');
    }

    public function test_an_entered_climate_record_replaces_only_the_month_it_covers(): void
    {
        ClimateRecord::query()->create(['record_date' => '2026-10-01', 'rainfall' => 210.0, 'temperature' => 28.4, 'humidity' => 82, 'wind_speed' => 2.5, 'season' => 'Dry', 'source' => 'PAGASA Ambulong (entered)']);
        YieldModelClimateWindow::forget();

        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2026-11-01'), ['tmax' => 31.0, 'tmin' => 24.0]);
        $this->assertSame(210.0, $r['features']['previous_rainfall']);
        $this->assertSame(['2026-09', '2026-08', '2026-07', '2026-06', '2026-05'], $r['missing_months'], 'other months are still missing');
        $this->assertNull($r['features']['rainfall_6m']);

        $older = YieldModelClimateWindow::features(CarbonImmutable::parse('2024-08-01'), ['tmax' => 31.0, 'tmin' => 24.0]);
        $this->assertSame([], $older['missing_months'], 'station months around it are untouched');
    }

    public function test_lag_months_not_over_yet_use_the_stored_forecast_but_past_months_never_do(): void
    {
        CarbonImmutable::setTestNow('2026-11-20 10:00:00');
        foreach (['2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-04'] as $ym) {
            MonthlyWeatherPrediction::query()->create(['prediction_year' => (int) substr($ym, 0, 4), 'prediction_month' => (int) substr($ym, 5, 2), 'rainfall' => 100.0,
                'temperature_average' => 27.0, 'humidity' => 80.0, 'wind_speed' => 2.0, 'weather_model' => 'test', 'status' => 'ready', 'generated_at' => now()]);
        }
        YieldModelClimateWindow::forget();

        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2027-03-01'), ['tmax' => 32.0, 'tmin' => 24.0, 'rainfall' => 50.0, 'temperature' => 28.0]);
        $this->assertSame(['2026-10', '2026-09'], $r['missing_months'], 'September and October are over: their forecasts are never used');
        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02'], $r['lag_forecast_months']);
        $this->assertSame(100.0, $r['features']['previous_rainfall']);
        $this->assertSame(80.0, $r['features']['previous_humidity']);
        $this->assertNull($r['features']['rainfall_6m']);

        foreach (['2026-09', '2026-10'] as $ym) {
            ClimateRecord::query()->create(['record_date' => $ym.'-01', 'rainfall' => 200.0, 'temperature' => 28.0, 'humidity' => 85, 'wind_speed' => 2, 'season' => 'Wet', 'source' => 'PAGASA Ambulong (entered)']);
        }
        YieldModelClimateWindow::forget();
        $full = YieldModelClimateWindow::features(CarbonImmutable::parse('2027-03-01'), ['tmax' => 32.0, 'tmin' => 24.0, 'rainfall' => 50.0, 'temperature' => 28.0]);
        $this->assertSame([], $full['missing_months']);
        $this->assertEqualsWithDelta(133.33, $full['features']['rainfall_6m'], 0.01);   // (200 + 200 + 4 x 100) / 6
        $this->assertSame([], $full['seasonal_missing_months']);
        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02', '2027-04'], $full['seasonal_forecast_months']);
        CarbonImmutable::setTestNow();
    }
}
