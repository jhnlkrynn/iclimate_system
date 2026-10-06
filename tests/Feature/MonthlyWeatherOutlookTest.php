<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\Weather\MonthlyWeatherCalendarService;
use App\Services\Weather\MonthlyWeatherOutlookService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyWeatherOutlookTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->csv) && is_file($this->csv)) {
            @unlink($this->csv);
        }
        parent::tearDown();
    }

    /** October in years 2015..(2015+$years-1): rainfall 200 mm, Tmax 32 / Tmin 24. */
    private function dataset(int $octoberYears): AmbulongHistoricalClimateDataset
    {
        $this->csv = tempnam(sys_get_temp_dir(), 'amb').'.csv';
        $lines = ['YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION'];
        for ($i = 0; $i < $octoberYears; $i++) {
            $lines[] = (2015 + $i).',10,200,32,24,80,3,90';
        }
        $lines[] = '2015,3,20,34,25,70,2,45';
        file_put_contents($this->csv, implode("\n", $lines)."\n");

        return new AmbulongHistoricalClimateDataset($this->csv);
    }

    private function model(array $overrides = []): array
    {
        return [
            'ready' => true,
            'source_name' => 'iClimate monthly Random Forest model',
            'generated_at' => '2026-09-30T04:00:00+00:00',
            'training_period' => ['from' => '2000-01', 'to' => '2025-12'],
            'confidence' => ['label' => 'Moderate confidence'],
            'predictions' => $overrides + [
                'rainfall' => 260.0, 'temperature' => 28.3, 'tmax' => 32.4, 'tmin' => 24.1,
                'humidity' => 81.0, 'wind_speed' => 3.2, 'wind_direction' => 90.0, 'season' => 'Dry',
            ],
        ];
    }

    private function outlook(int $years, ?array $model, ?array $weather = null, string $month = '2026-10-01'): array
    {
        $calendar = (new MonthlyWeatherCalendarService)->build(CarbonImmutable::parse($month), $weather, $model);

        return (new MonthlyWeatherOutlookService($this->dataset($years)))->build($calendar);
    }

    private function dailyWeather(array $series): array
    {
        return [
            'source_name' => 'Open-Meteo Forecast API',
            'fetched_at' => '2026-10-02T02:00:00+00:00',
            'daily_series' => $series,
        ];
    }

    public function test_model_block_shows_real_values_source_updated_time_and_labelled_outlook(): void
    {
        $out = $this->outlook(10, $this->model());
        $m = $out['model'];
        $values = collect($m['rows'])->pluck('value', 'label');

        $this->assertSame('monthly', $out['mode']);
        $this->assertNull($out['weekly']);
        $this->assertSame('iClimate Monthly Estimate', $m['type']);
        $this->assertSame('260.0', $values['Expected Rainfall']);
        $this->assertSame('28.3', $values['Expected Temperature']);
        $this->assertStringContainsString('E (90°)', $values['Expected Wind']);
        $this->assertSame('October 1–31, 2026 (the whole month, not a single day)', $m['period']);
        $this->assertFalse(collect($m['rows'])->firstWhere('label', 'Chance of Rain')['available']);
        $this->assertFalse(collect($m['rows'])->firstWhere('label', 'Main Weather')['available']);
        $this->assertStringContainsString('Above Normal rainfall', $values['Overall Weather Outlook']);
        $this->assertStringContainsString('iClimate monthly Random Forest model', $m['source']);
        $this->assertStringContainsString('2000-01 to 2025-12', $m['source']);
        $this->assertSame('Sep 30, 2026, 12:00 PM (Philippine time)', $m['updated']);
        $this->assertSame('October 1–31, 2026 (whole month)', $out['period']);
        $this->assertStringContainsString('not a daily forecast', $m['uncertainty']);

        $outlook = collect($m['outlook'])->pluck('value', 'label');
        $this->assertSame('Dry', $outlook['Season']);
        $this->assertSame('Above Normal', $outlook['Rainfall Outlook']);      // 260 / 200 = 130%
        $this->assertSame('Near Normal', $outlook['Temperature Outlook']);    // 28.3 vs 28.0
    }

    public function test_outlook_cards_are_computed_from_the_selected_month_and_real_history(): void
    {
        $cards = $this->outlook(10, $this->model())['model']['cards'];

        $this->assertSame('Dry', $cards['season']['active']);
        $this->assertSame('May–September', $cards['season']['wet']);
        $this->assertSame('October–April', $cards['season']['dry']);

        $this->assertTrue($cards['rainfall']['available']);
        $this->assertSame('Above Normal', $cards['rainfall']['status']);
        $this->assertSame('130%', $cards['rainfall']['value']);                       // 260 / 200
        $this->assertSame('of the historical October average rainfall', $cards['rainfall']['caption']);
        $this->assertSame(10, $cards['rainfall']['years']);
        $this->assertSame('2015–2024', $cards['rainfall']['span']);

        $this->assertSame('Near Normal', $cards['temperature']['status']);
        $this->assertSame('+0.3°C', $cards['temperature']['value']);                  // 28.3 vs 28.0
        $this->assertSame('compared with the historical October average temperature', $cards['temperature']['caption']);
    }

    public function test_negative_temperature_difference_and_below_normal_rain_are_reported_correctly(): void
    {
        $cards = $this->outlook(11, $this->model(['rainfall' => 162.0, 'temperature' => 27.8]))['model']['cards'];

        $this->assertSame('81%', $cards['rainfall']['value']);                        // 162 / 200
        $this->assertSame('Near Normal', $cards['rainfall']['status']);
        $this->assertSame('-0.2°C', $cards['temperature']['value']);
        $this->assertSame(11, $cards['temperature']['years']);
        $this->assertSame('2015–2025', $cards['temperature']['span']);

        $below = $this->outlook(11, $this->model(['rainfall' => 100.0]))['model']['cards'];
        $this->assertSame('Below Normal', $below['rainfall']['status']);
    }

    public function test_cards_follow_the_selected_month_and_are_marked_unavailable_without_enough_history(): void
    {
        // The test dataset only has one March record, so March has no fair historical comparison.
        $cards = $this->outlook(10, $this->model(['season' => 'Dry']), null, '2027-03-01')['model']['cards'];

        $this->assertFalse($cards['rainfall']['available']);
        $this->assertFalse($cards['temperature']['available']);
        $this->assertStringContainsString('March', $cards['rainfall']['reason']);
        $this->assertStringContainsString('at least 5', $cards['rainfall']['reason']);
        $this->assertSame('March', $cards['season']['month']);

        $wet = $this->outlook(10, $this->model(['season' => null]), null, '2026-07-01')['model']['cards'];
        $this->assertSame('Wet', $wet['season']['active']);                            // derived from the month when the model gives none
    }

    public function test_missing_model_rainfall_makes_only_that_card_unavailable(): void
    {
        $cards = $this->outlook(10, $this->model(['rainfall' => null]))['model']['cards'];

        $this->assertFalse($cards['rainfall']['available']);
        $this->assertTrue($cards['temperature']['available']);
    }
    public function test_monthly_estimate_wins_over_weekly_when_both_exist(): void
    {
        $out = $this->outlook(10, $this->model(), $this->dailyWeather(['labels' => ['2026-10-01'], 'rainfall' => [3.0]]));

        $this->assertSame('monthly', $out['mode']);
        $this->assertNull($out['weekly']);
    }

    public function test_nothing_reliable_means_no_forecast_and_no_invented_values(): void
    {
        $out = $this->outlook(10, null);

        $this->assertSame('none', $out['mode']);
        $this->assertNull($out['model']);
        $this->assertNull($out['weekly']);
    }

    public function test_outlook_class_is_omitted_when_history_is_too_short(): void
    {
        $out = $this->outlook(3, $this->model());

        $this->assertSame(['Season'], collect($out['model']['outlook'])->pluck('label')->all());
    }

    public function test_missing_model_fields_are_left_out_and_never_shown_as_zero(): void
    {
        $out = $this->outlook(10, $this->model(['humidity' => null, 'wind_speed' => null, 'wind_direction' => null]));
        $rows = collect($out['model']['rows'])->keyBy('label');

        $this->assertFalse($rows['Expected Humidity']['available']);
        $this->assertNull($rows['Expected Humidity']['value']);
        $this->assertFalse($rows['Expected Wind']['available']);
        $this->assertTrue($rows['Expected Rainfall']['available']);
    }

    public function test_weekly_fallback_uses_only_real_days_and_labels_partial_coverage(): void
    {
        $out = $this->outlook(10, null, $this->dailyWeather([
            'labels' => ['2026-10-01', '2026-10-02', '2026-09-30'],
            'conditions' => ['Light Rain', 'Light Rain', 'Overcast'],
            'rainfall' => [10.0, 5.5, 99.0],
            'precipitationProbability' => [40, 80, 100],
            'temperatureMax' => [31, 33, 40],
            'temperatureMin' => [25, 25, 20],
            'windSpeed' => [12, 20, 90],
        ]));

        $this->assertSame('weekly', $out['mode']);
        $weeks = collect($out['weekly']['weeks']);
        $this->assertCount(5, $weeks);

        $w1 = $weeks[0];
        $values = collect($w1['rows'])->pluck('value', 'label');
        $this->assertSame('October 1–7, 2026', $w1['period']);
        $this->assertSame('2 of 7 days', $w1['coverage']);
        $this->assertSame('15.5', $values['Expected Rainfall']);                 // Sep 30 (outside the month) excluded
        $this->assertSame('28.5', $values['Expected Temperature']);
        $this->assertSame('up to 80%', $values['Chance of Rain']);
        $this->assertSame('20.0', $values['Strongest Daily Wind']);
        $this->assertSame('Mostly Light Rain', $values['Weather Outlook']);
        $this->assertSame(['Light Rain' => 2], $w1['conditions']);
        $this->assertFalse(collect($w1['rows'])->firstWhere('label', 'Expected Humidity')['available']); // no humidity supplied

        foreach ($weeks->slice(1) as $empty) {                                   // weeks 2..5 have no data: nothing is invented
            $this->assertFalse($empty['has_data']);
            $this->assertSame([], $empty['rows']);
            $this->assertSame('0 of '.$empty['days_in_week'].' days', $empty['coverage']);
        }
        $this->assertSame('Open-Meteo weather forecast', $out['weekly']['source']);
        $this->assertSame('Oct 2, 2026, 10:00 AM (Philippine time)', $out['weekly']['updated']);
    }

    public function test_missing_values_are_never_turned_into_zero(): void
    {
        $out = $this->outlook(10, null, $this->dailyWeather([
            'labels' => ['2026-10-01', '2026-10-02'],
            'rainfall' => [null, null],
            'temperatureMax' => [30, 32],
            'temperatureMin' => [24, 26],
        ]));
        $rows = collect($out['weekly']['weeks'][0]['rows'])->keyBy('label');

        $this->assertFalse($rows['Expected Rainfall']['available']);
        $this->assertNull($rows['Expected Rainfall']['value']);
        $this->assertSame('28.0', $rows['Expected Temperature']['value']);
    }

    public function test_a_real_zero_rainfall_is_kept_as_zero(): void
    {
        $out = $this->outlook(10, null, $this->dailyWeather(['labels' => ['2026-10-01'], 'rainfall' => [0.0]]));
        $rows = collect($out['weekly']['weeks'][0]['rows'])->keyBy('label');

        $this->assertTrue($rows['Expected Rainfall']['available']);
        $this->assertSame('0.0', $rows['Expected Rainfall']['value']);
    }

    public static function monthLengths(): array
    {
        return [
            'October 2026 (31)' => ['2026-10', 31, 5, '29–31'],
            'February 2027 (28)' => ['2027-02', 28, 4, '22–28'],
            'February 2028 (29)' => ['2028-02', 29, 5, '29'],
            'April 2027 (30)' => ['2027-04', 30, 5, '29–30'],
            'December 2026 (31)' => ['2026-12', 31, 5, '29–31'],
        ];
    }

    #[DataProvider('monthLengths')]
    public function test_week_ranges_cover_every_date_exactly_once(string $month, int $days, int $weekCount, string $lastWeek): void
    {
        $ranges = MonthlyWeatherOutlookService::weekRanges(CarbonImmutable::createFromFormat('Y-m-d', $month.'-01'));
        $covered = [];
        foreach ($ranges as $r) {
            for ($d = $r['start']; $d->lte($r['end']); $d = $d->addDay()) {
                $covered[] = $d->toDateString();
            }
        }

        $this->assertCount($weekCount, $ranges);
        $this->assertCount($days, $covered);
        $this->assertSame($covered, array_values(array_unique($covered)));                  // no overlap
        $this->assertSame($month.'-01', $covered[0]);
        $this->assertSame($month.'-'.str_pad((string) $days, 2, '0', STR_PAD_LEFT), end($covered)); // no gap, ends on the last day
        $last = end($ranges);
        $this->assertSame($lastWeek, $last['start']->format('j').($last['start']->equalTo($last['end']) ? '' : '–'.$last['end']->format('j')));
    }

    public function test_historical_block_reports_average_and_observed_month_separately(): void
    {
        $out = $this->outlook(10, null, null, '2015-10-01');
        $h = $out['historical'];

        $this->assertSame('Past Weather Records', $h['type']);
        $this->assertSame('200.0', collect($h['observed_rows'])->pluck('value', 'label')['Recorded Rainfall']);
        $this->assertSame(9, $h['years']);                                  // the selected year is excluded from the average
        $this->assertSame('2016–2024', $h['span']);
    }

    public function test_page_switches_between_monthly_weekly_and_none_and_follows_the_month(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $this->mock(PredictionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('monthlyWeatherForecast')->andReturnUsing(fn (CarbonImmutable $m) => $m->month === 10
                ? $this->model()
                : ['ready' => false, 'message' => 'Not enough history.', 'predictions' => []]);
        });

        $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => '2026-10']))
            ->assertOk()
            ->assertSee('data-outlook-month="2026-10"', false)
            ->assertSee('data-outlook-mode="monthly"', false)
            ->assertSee('Monthly Weather Prediction')
            ->assertSee('Expected Rainfall')
            ->assertSee('Forecast Period')
            ->assertSee('Forecast Type')
            ->assertSee('Source')
            ->assertSee('Last Updated')
            ->assertSee('Not available for this period')
            ->assertSee('Seasonal &amp; Outlook Information', false)
            ->assertSee('Seasonal Pattern')
            ->assertSee('Rainfall Outlook')
            ->assertSee('Temperature Outlook')
            ->assertSee('Historical comparison')
            ->assertSee('PAGASA monthly outlooks are not shown in iClimate')
            ->assertDontSee('No forecast is available for this month yet.');
        // The page's styles live in resources/css/pages since 2026-10-06 (they used to be a <style> block in the view).
        $this->assertStringContainsString('color: #0d1f18', (string) file_get_contents(resource_path('css/pages/weather-predictions.css')));

        $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => '2026-11']))
            ->assertOk()
            ->assertSee('data-outlook-month="2026-11"', false)
            ->assertSee('data-outlook-mode="none"', false)
            ->assertSee('No forecast is available for this month yet.')
            ->assertDontSee('Expected Rainfall');
    }

    public function test_page_shows_weekly_predictions_when_only_daily_forecast_exists(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $date = CarbonImmutable::now('Asia/Manila')->addDay();
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => $date->format('Y-m-d').'T12:00', 'temperature_2m' => 29.0, 'relative_humidity_2m' => 80, 'precipitation' => 0, 'weather_code' => 53, 'wind_speed_10m' => 9, 'is_day' => 1],
            'daily' => [
                'time' => [$date->format('Y-m-d')], 'weather_code' => [53], 'temperature_2m_max' => [31], 'temperature_2m_min' => [25],
                'precipitation_sum' => [4], 'precipitation_probability_max' => [60], 'wind_speed_10m_max' => [10],
            ],
        ])]);
        $this->mock(PredictionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('monthlyWeatherForecast')->andReturn(['ready' => false, 'message' => 'n/a', 'predictions' => []]);
        });
        // Pages never call the provider; the scheduled refresh stores the forecast they show.
        $this->artisan('iclimate:refresh-weather-cache');

        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => $date->format('Y-m')]))
            ->assertOk()
            ->assertSee('data-outlook-mode="weekly"', false)
            ->assertSee('Weekly Weather Predictions')
            ->assertSee('Week 1')
            ->assertSee('No forecast yet')
            ->assertSee('Moderate Rain')            // drizzle code 53 shown with rain wording
            ->assertDontSee('Drizzle')
            ->assertDontSee('No forecast is available for this month yet.')
            ->getContent();

        $this->assertSame(count(MonthlyWeatherOutlookService::weekRanges($date->startOfMonth())), substr_count($html, 'data-week-start='));
    }

    public function test_unconfirmed_model_wind_is_shown_only_to_it(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $this->mock(PredictionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('monthlyWeatherForecast')->andReturnUsing(fn () => $this->model());
        });
        $page = fn (string $role) => $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('weather-predictions.index', ['calendar_month' => '2026-10']))->assertOk();

        // The model's wind has no confirmed unit: a number a farmer cannot safely read is not shown.
        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $page($role)->assertSee('Expected Rainfall')->assertDontSee('Expected Wind')->assertDontSee('unit not confirmed');
        }
        $page(User::ROLE_IT_EXPERT)->assertSee('Expected Wind')->assertSee('(unit not confirmed)');
    }
}
