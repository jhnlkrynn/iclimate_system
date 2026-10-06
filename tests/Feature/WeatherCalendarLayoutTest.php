<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Prediction\PredictionOrchestrator;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherCalendarLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        Config::set('services.weather_api.enabled', true);
        $this->mock(PredictionOrchestrator::class, function ($mock) {
            $mock->shouldReceive('monthlyWeatherForecast')->andReturn(['ready' => false, 'message' => 'n/a', 'predictions' => []]);
        });
    }

    private function forecastFor(string $date, float $rain, int $humidity): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => ['time' => $date.'T12:00', 'temperature_2m' => 29.0, 'relative_humidity_2m' => 80, 'precipitation' => 0, 'weather_code' => 61, 'wind_speed_10m' => 9, 'is_day' => 1],
            'daily' => [
                'time' => [$date], 'weather_code' => [61], 'temperature_2m_max' => [31], 'temperature_2m_min' => [25],
                'precipitation_sum' => [$rain], 'precipitation_probability_max' => [60], 'wind_speed_10m_max' => [10],
                'relative_humidity_2m_mean' => [$humidity],
            ],
        ])]);
        // Pages never call the provider; the scheduled refresh stores the forecast the calendar shows.
        $this->artisan('iclimate:refresh-weather-cache');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($dom);
    }

    public static function months(): array
    {
        return [
            'September 2026' => ['2026-09', 'September 2026'],
            'October 2026' => ['2026-10', 'October 2026'],
            'November 2026' => ['2026-11', 'November 2026'],
            'December 2026' => ['2026-12', 'December 2026'],
            'January 2027' => ['2027-01', 'January 2027'],
            'February 2027' => ['2027-02', 'February 2027'],
            'February 2028 (leap year)' => ['2028-02', 'February 2028'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('months')]
    public function test_header_dates_and_columns_are_correct(string $month, string $label): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => $month]))->assertOk()->getContent();
        $x = $this->xpath($html);

        // Header comes from the selected month, and sits above the grid.
        $header = trim($x->query('//h3[contains(@class,"wp-calendar-month")]')->item(0)->textContent);
        $this->assertSame($label, $header);
        $this->assertLessThan(strpos($html, 'class="wp-calendar-grid"'), strpos($html, 'class="wp-calendar-month'));

        $first = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');
        $heads = array_map(fn ($n) => trim($n->textContent), iterator_to_array($x->query('//div[contains(@class,"wp-calendar-weekday")]')));
        $this->assertSame(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], $heads);

        $tiles = iterator_to_array($x->query('//article[contains(@class,"wp-calendar-day")]'));
        $this->assertSame(0, count($tiles) % 7);

        $cursor = $first->startOfWeek(CarbonImmutable::SUNDAY);
        $inMonth = 0;
        foreach ($tiles as $i => $tile) {
            $this->assertSame($cursor->format('l, F j, Y'), explode(', forecast', $tile->getAttribute('aria-label'))[0]);
            $this->assertSame($i % 7, (int) $cursor->dayOfWeek, 'Date is not in its Sun–Sat column');
            $num = trim($x->query('.//span[contains(@class,"wp-calendar-daynum")]', $tile)->item(0)->textContent);
            $this->assertStringEndsWith((string) $cursor->day, $num);
            $isOutside = str_contains($tile->getAttribute('class'), 'outside-month');
            $this->assertSame($cursor->month !== $first->month, $isOutside);
            $inMonth += $isOutside ? 0 : 1;
            $cursor = $cursor->addDay();
        }
        $this->assertSame($first->daysInMonth, $inMonth);

        // The monthly section describes exactly this month and its real length.
        $this->assertSame($month, $x->query('//section[@data-monthly-outlook]')->item(0)->getAttribute('data-outlook-month'));
        $this->assertStringContainsString('Prediction · '.$label, preg_replace('/\s+/', ' ', html_entity_decode($x->query('//section[@data-monthly-outlook]//h3')->item(0)->textContent)));

        // Nothing forecast (the API is down): tiles stay clean.
        $this->assertStringNotContainsString('No forecast data', $html);
        $this->assertSame(0, $x->query('//article[contains(@class,"has-forecast")]')->length);
    }

    public function test_forecast_is_attached_to_its_own_date_and_other_tiles_stay_blank(): void
    {
        $date = CarbonImmutable::now('Asia/Manila')->addDay()->format('Y-m-d');
        $this->forecastFor($date, 7.5, 82);
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => substr($date, 0, 7)]))->assertOk()->getContent();
        $x = $this->xpath($html);

        $withData = $x->query('//article[contains(@class,"has-forecast") and not(contains(@class,"outside-month"))]');
        $this->assertSame(1, $withData->length);
        $tile = $withData->item(0);
        $this->assertStringContainsString(CarbonImmutable::parse($date)->format('l, F j, Y'), $tile->getAttribute('aria-label'));
        $this->assertStringContainsString('7.5 mm', $tile->textContent);
        // The daily forecast carries no humidity, so no RH line is invented.
        $this->assertStringNotContainsString('RH:', $tile->textContent);

        foreach ($x->query('//article[contains(@class,"no-forecast")]') as $blank) {
            $this->assertSame('', trim(preg_replace('/\s+/', ' ', preg_replace('/^(Sun|Mon|Tue|Wed|Thu|Fri|Sat)\s*\d+\s*/', '', trim($blank->textContent)))));
        }
    }

    public function test_severity_legend_is_readable_and_sits_just_above_the_calendar(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => '2026-10']))->assertOk()->getContent();

        $legend = strpos($html, 'class="wp-risk-legend"');
        $this->assertNotFalse($legend);
        // Order: prediction panel -> month bar -> legend -> weekday header / grid.
        $this->assertGreaterThan(strpos($html, 'data-monthly-outlook'), $legend);
        $this->assertGreaterThan(strpos($html, 'class="wp-calendar-bar"'), $legend);
        $this->assertLessThan(strpos($html, 'class="wp-calendar-grid"'), $legend);

        $x = $this->xpath($html);
        $items = array_map(fn ($n) => trim($n->textContent), iterator_to_array($x->query('//div[contains(@class,"wp-risk-legend")]/span')));
        $this->assertSame(['Low', 'Moderate', 'High / Severe'], $items);

        // Dark, semibold, 16px text; the family comes from the page-wide font system (checked in the typography test).
        // The page's styles live in resources/css/pages since 2026-10-06 (they used to be a <style> block in the view).
        $css = (string) file_get_contents(resource_path('css/pages/weather-predictions.css'));
        $this->assertMatchesRegularExpression('/\.wp-risk-legend-item\s*\{[^}]*font-size:\s*1rem[^}]*color:\s*#0d1f18/', $css);
        $this->assertStringNotContainsString("wp-risk-legend-item { display: inline-flex; align-items: center; gap: .55rem; font-family: 'DM Serif", $css);
        foreach (['#52b788', '#ffd166', '#d85b45'] as $swatch) {
            $this->assertStringContainsString('background:'.$swatch, $html.$css);
        }
    }
    public function test_page_uses_one_font_family_and_readable_type_scale(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => '2026-10']))->assertOk()->getContent();

        // The page's styles live in resources/css/pages since 2026-10-06 (they used to be a <style> block in the view).
        $css = (string) file_get_contents(resource_path('css/pages/weather-predictions.css'));
        $this->assertMatchesRegularExpression('/<link[^>]+weather-predictions[^">]*\.css/', $html);
        $start = strpos($css, 'Weather Forecast typography system');
        $this->assertNotFalse($start);
        $system = substr($css, $start);

        // One family for everything on the page (headings, labels, values, buttons, calendar).
        $this->assertStringContainsString("--wp-font: 'Inter'", $system);
        $this->assertStringContainsString('.wp-page, .wp-page * { font-family: var(--wp-font); }', $system);
        $this->assertStringNotContainsString('DM Serif', $system);
        $this->assertStringNotContainsString('DM Mono', $system);

        // No text smaller than 13px in the scale, and no faint colours in the palette.
        preg_match_all('/--wp-fs-[a-z0-9-]+:\s*([\d.]+)rem/', $system, $sizes);
        $this->assertNotEmpty($sizes[1]);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.8125, (float) $rem);
        }
        foreach (['--wp-ink: #0d1f18', '--wp-ink-2: #26382f', '--wp-ink-3: #3d5247'] as $colour) {
            $this->assertStringContainsString($colour, $system);
        }
        $this->assertStringNotContainsString('opacity', $system);
        $this->assertStringNotContainsString('rgba(255,255,255', $system);
    }
    public function test_navigation_links_and_today_button_follow_the_selected_month(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $html = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => '2026-12']))->assertOk()->getContent();

        $this->assertStringContainsString('calendar_month=2026-11', $html);
        $this->assertStringContainsString('calendar_month=2027-01', $html);
        $this->assertStringContainsString('class="wp-calendar-today"', $html);

        $current = CarbonImmutable::now('Asia/Manila')->format('Y-m');
        $now = $this->actingAs($user)->get(route('weather-predictions.index', ['calendar_month' => $current]))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="wp-calendar-today"', $now);
    }
}
