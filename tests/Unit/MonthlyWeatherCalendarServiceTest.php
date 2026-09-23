<?php

namespace Tests\Unit;

use App\Services\Weather\MonthlyWeatherCalendarService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MonthlyWeatherCalendarServiceTest extends TestCase
{
    public function test_it_builds_correct_month_boundaries_and_weekday_headers(): void
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(
            CarbonImmutable::create(2027, 1, 15),
            null,
        );
        $days = collect($calendar['weeks'])->flatten(1);
        $inMonth = $days->where('in_month', true)->values();

        $this->assertSame(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], $calendar['weekdays']);
        $this->assertSame('January 2027', $calendar['label']);
        $this->assertSame('2026-12', $calendar['previous_month']);
        $this->assertSame('2027-02', $calendar['next_month']);
        $this->assertSame(31, $inMonth->count());
        $this->assertSame('2027-01-01', $inMonth->first()['date']);
        $this->assertSame('2027-01-31', $inMonth->last()['date']);
        $this->assertNull($inMonth->first()['forecast']);
        $this->assertNull($inMonth->first()['risk']);
    }

    public function test_it_uses_only_returned_open_meteo_values_for_daily_risk(): void
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(
            CarbonImmutable::create(2026, 9, 1),
            [
                'source_name' => 'Open-Meteo Forecast API',
                'daily_series' => [
                    'labels' => ['2026-09-13'],
                    'conditions' => ['Heavy Rain'],
                    'rainfall' => [28.4],
                    'precipitationProbability' => [85],
                    'temperature' => [28.5],
                    'temperatureMax' => [30],
                    'temperatureMin' => [27],
                    'humidity' => [88],
                    'windSpeed' => [22],
                ],
            ],
        );
        $days = collect($calendar['weeks'])->flatten(1)->keyBy('date');

        $this->assertSame(1, $calendar['forecast_count']);
        $this->assertSame('Heavy Rain', $days['2026-09-13']['forecast']['condition']);
        $this->assertSame(28.4, $days['2026-09-13']['forecast']['rainfall']);
        $this->assertSame('High', $days['2026-09-13']['risk']['level']);
        $this->assertNull($days['2026-09-14']['forecast']);
        $this->assertNull($days['2026-09-14']['risk']);
        $this->assertSame('api', $days['2026-09-13']['forecast']['source_type']);
    }

    /**
     * Regression coverage for the 2026-09 "full month calendar" work:
     * MonthlyWeatherRandomForest only produces one aggregate per variable
     * for the whole month, never per-day values, so it must surface as its
     * own separate 'monthly_prediction' summary — never merged into, or
     * mistaken for, an individual day's 'forecast' field.
     */
    public function test_monthly_prediction_summary_is_kept_separate_from_daily_forecasts(): void
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(
            CarbonImmutable::create(2026, 9, 1),
            null,
            [
                'ready' => true,
                'source_name' => 'iClimate monthly Random Forest model',
                'predictions' => [
                    'rainfall' => 210.4,
                    'temperature' => 27.9,
                    'humidity' => 81.2,
                    'wind_speed' => 12.6,
                    'season' => 'Wet',
                ],
                'confidence' => ['label' => 'Moderate'],
            ],
        );

        $this->assertNotNull($calendar['monthly_prediction']);
        $this->assertSame(210.4, $calendar['monthly_prediction']['rainfall_mm']);
        $this->assertSame('Wet', $calendar['monthly_prediction']['season']);
        $this->assertSame('iClimate monthly Random Forest model', $calendar['monthly_prediction']['source_name']);

        // No day cell was ever touched by this whole-month figure.
        $days = collect($calendar['weeks'])->flatten(1);
        $this->assertTrue($days->every(fn (array $day) => $day['forecast'] === null));
    }

    public function test_monthly_prediction_is_null_when_the_model_is_not_ready(): void
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(
            CarbonImmutable::create(2026, 9, 1),
            null,
            ['ready' => false, 'predictions' => []],
        );

        $this->assertNull($calendar['monthly_prediction']);
    }

    public function test_daily_forecast_carries_its_icon_alongside_condition(): void
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(
            CarbonImmutable::create(2026, 9, 1),
            [
                'source_name' => 'Open-Meteo Forecast API',
                'daily_series' => [
                    'labels' => ['2026-09-13'],
                    'conditions' => ['Thunderstorm'],
                    'icons' => ['/images/weather/thunderstorm.svg'],
                    'rainfall' => [40.0],
                ],
            ],
        );
        $days = collect($calendar['weeks'])->flatten(1)->keyBy('date');

        $this->assertSame('/images/weather/thunderstorm.svg', $days['2026-09-13']['forecast']['icon']);
    }
}
