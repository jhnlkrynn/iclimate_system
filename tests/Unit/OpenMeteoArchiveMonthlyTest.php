<?php

namespace Tests\Unit;

use App\Models\ClimateRecord;
use App\Services\Climate\OpenMeteoArchiveMonthly;
use App\Support\YieldModelClimateWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Owner decision 2026-11-20: the Open-Meteo historical archive fills only months no station or entered record covers. */
class OpenMeteoArchiveMonthlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(OpenMeteoArchiveMonthly::path());
        OpenMeteoArchiveMonthly::forget();
        YieldModelClimateWindow::forget();
    }

    protected function tearDown(): void
    {
        @unlink(OpenMeteoArchiveMonthly::path());
        OpenMeteoArchiveMonthly::forget();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, array{0: float, 1: float, 2: float, 3: float, 4: float}|null>  $days  date => [rain, tmax, tmin, rh, wind] */
    private function fakeArchive(array $days): void
    {
        $daily = ['time' => [], 'precipitation_sum' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'relative_humidity_2m_mean' => [], 'wind_speed_10m_mean' => []];
        foreach ($days as $date => $v) {
            $daily['time'][] = $date;
            foreach (['precipitation_sum', 'temperature_2m_max', 'temperature_2m_min', 'relative_humidity_2m_mean', 'wind_speed_10m_mean'] as $i => $f) {
                $daily[$f][] = $v[$i] ?? null;
            }
        }
        Http::fake(['archive-api.open-meteo.com/*' => Http::response(['daily' => $daily])]);
    }

    /** @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float}> */
    private function month(string $ym, float $rain, float $tmax, float $tmin, float $rh = 85.0, float $wind = 2.0): array
    {
        $out = [];
        $first = CarbonImmutable::parse($ym.'-01');
        for ($d = 0; $d < $first->daysInMonth; $d++) {
            $out[$first->addDays($d)->toDateString()] = [$rain, $tmax, $tmin, $rh, $wind];
        }

        return $out;
    }

    public function test_only_complete_past_months_are_kept_and_built_like_the_station_file(): void
    {
        CarbonImmutable::setTestNow('2026-11-20 10:00:00');
        $september = $this->month('2026-09', 10.0, 31.0, 25.0, 90.0, 1.5);
        $october = $this->month('2026-10', 4.0, 30.0, 24.0);
        $october['2026-10-31'][0] = null;   // one missing reading: the whole month is dropped
        $this->fakeArchive($september + $october + ['2026-11-01' => [1.0, 30.0, 24.0, 80.0, 3.0]]);

        $months = app(OpenMeteoArchiveMonthly::class)->fetch(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-11-01'));

        $this->assertSame(['2026-09'], array_keys($months), 'partial and unfinished months are not kept');
        $this->assertSame(300.0, $months['2026-09']['rainfall']);          // monthly SUM
        $this->assertSame(28.0, $months['2026-09']['temperature']);        // (mean TMAX + mean TMIN) / 2
        $this->assertSame(90.0, $months['2026-09']['humidity']);
        $this->assertSame(1.5, $months['2026-09']['wind_speed']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'latitude=14.0886') && str_contains($r->url(), 'end_date=2026-11-19'));
    }

    /** Archive months that read 0.5 degC cooler and 25 % wetter than the station, 2015-2025, plus the given 2026 months. */
    private function storeBiasedArchive(array $months2026): void
    {
        $months = [];
        foreach (YieldModelClimateWindow::history() as $key => $row) {
            $months[$key] = ['rainfall' => round($row->rainfall * 1.25, 4), 'temperature' => $row->temperature - 0.5, 'humidity' => $row->humidity + 5, 'wind_speed' => $row->wind_speed + 1];
        }
        app(OpenMeteoArchiveMonthly::class)->store($months + $months2026);
        OpenMeteoArchiveMonthly::forget();
        YieldModelClimateWindow::forget();
    }

    public function test_archive_fills_unobserved_lag_months_on_the_station_scale_and_is_reported(): void
    {
        $raw = [];
        foreach (['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'] as $i => $ym) {
            $raw[$ym] = ['rainfall' => 100.0 * ($i + 1), 'temperature' => 28.0, 'humidity' => 90.0, 'wind_speed' => 3.0];
        }
        $this->storeBiasedArchive($raw);

        $r = YieldModelClimateWindow::features(CarbonImmutable::parse('2026-11-01'), ['tmax' => 31.0, 'tmin' => 24.0]);

        $this->assertSame([], $r['missing_months']);
        $this->assertSame(['2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], $r['reanalysis_months']);
        $this->assertEqualsWithDelta(480.0, $r['features']['previous_rainfall'], 0.01, 'October 600 mm / 1.25');
        $this->assertEqualsWithDelta(28.5, $r['features']['temp_6m'], 0.01, '+0.5 degC');
        $october = YieldModelClimateWindow::history()['2026-10'];
        $this->assertEqualsWithDelta(85.0, $october->humidity, 0.01);
        $this->assertSame(600.0, $october->rainfall_archive, 'raw archive value kept');
        $this->assertStringStartsWith(OpenMeteoArchiveMonthly::SOURCE, $october->source);
    }

    public function test_a_month_without_enough_station_years_is_not_used_uncalibrated(): void
    {
        app(OpenMeteoArchiveMonthly::class)->store(['2026-10' => ['rainfall' => 300.0, 'temperature' => 28.0, 'humidity' => 90.0, 'wind_speed' => 3.0]]);
        YieldModelClimateWindow::forget();

        $this->assertArrayNotHasKey('2026-10', YieldModelClimateWindow::history());
        $this->assertContains('2026-10', YieldModelClimateWindow::features(CarbonImmutable::parse('2026-11-01'))['missing_months']);
    }

    public function test_station_and_entered_months_always_win_over_the_archive(): void
    {
        $stationOctober = YieldModelClimateWindow::history()['2025-10'];
        $this->storeBiasedArchive(['2026-10' => ['rainfall' => 2.0, 'temperature' => 27.0, 'humidity' => 90.0, 'wind_speed' => 3.0]]);
        ClimateRecord::query()->create(['record_date' => '2026-10-01', 'rainfall' => 222.0, 'temperature' => 27.9, 'humidity' => 84, 'wind_speed' => 2, 'season' => 'Dry', 'source' => 'PAGASA Ambulong (entered)']);
        YieldModelClimateWindow::forget();

        $history = YieldModelClimateWindow::history();
        $this->assertSame($stationOctober->rainfall, $history['2025-10']->rainfall, 'station month kept');
        $this->assertSame(222.0, (float) $history['2026-10']->rainfall, 'entered MAO record kept');
        $this->assertNotContains('2026-10', YieldModelClimateWindow::features(CarbonImmutable::parse('2026-11-01'))['reanalysis_months']);
    }

    public function test_command_reports_a_failed_request_without_touching_stored_months(): void
    {
        Http::fake(['archive-api.open-meteo.com/*' => Http::response('down', 503)]);

        $this->artisan('iclimate:fetch-climate-archive', ['--from' => '2026-05', '--to' => '2026-10'])->assertFailed();
        $this->assertFileDoesNotExist(OpenMeteoArchiveMonthly::path());
    }
}
