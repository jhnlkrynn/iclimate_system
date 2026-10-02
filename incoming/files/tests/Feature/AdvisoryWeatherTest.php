<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\AdvisoryWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Weather Behind This Advice" must show the forecast for THIS advisory's place
 * and validity window — not the current weather — and must never invent a value.
 */
class AdvisoryWeatherTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Manila';

    /** @var array<int, array{lat: float, lon: float}> */
    private array $requests = [];

    private bool $sourceDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        // "Now" is fixed so the forecast horizon and the past-day limit are known.
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', self::TZ));
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A deterministic stand-in for the source's response. Hourly values depend on
     * the hour of day and date, so different windows give different, predictable
     * answers:
     *   precipitation  1.5 mm in each hour 15:00-20:00 on Sep 29, otherwise 0
     *   probability    3 x hour of day          temperature 20 + hour of day
     *   wind           5 + hour of day (km/h)   gusts wind + 10
     * Daily values depend on the day index (Sep 27 = 0).
     *
     * @param  array<string, mixed>  $options  wind: false drops wind; until: last date of data
     */
    private function fakeSource(array $options = []): void
    {
        $until = Carbon::parse($options['until'] ?? '2026-10-13', self::TZ)->endOfDay();
        $start = Carbon::parse('2026-09-27 00:00', self::TZ);

        Http::fake(function (Request $request) use ($options, $start, $until) {
            if ($this->sourceDown) {
                return Http::response('down', 500);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->requests[] = ['lat' => (float) $query['latitude'], 'lon' => (float) $query['longitude']];

            $hourly = ['time' => [], 'temperature_2m' => [], 'precipitation' => [], 'precipitation_probability' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => []];
            $daily = ['time' => [], 'temperature_2m_max' => [], 'precipitation_sum' => [], 'precipitation_probability_max' => [], 'wind_speed_10m_max' => [], 'wind_gusts_10m_max' => []];

            for ($t = $start->copy(); $t->lte($until); $t->addHour()) {
                $hour = (int) $t->format('G');
                $hourly['time'][] = $t->format('Y-m-d\TH:i');
                $hourly['temperature_2m'][] = 20.0 + $hour;
                $hourly['precipitation'][] = ($t->format('Y-m-d') === '2026-09-29' && $hour >= 15 && $hour <= 20) ? 1.5 : 0.0;
                $hourly['precipitation_probability'][] = 3 * $hour;
                $hourly['wind_speed_10m'][] = ($options['wind'] ?? true) === false ? null : 5.0 + $hour;
                $hourly['wind_gusts_10m'][] = ($options['wind'] ?? true) === false ? null : 15.0 + $hour;
            }

            for ($d = $start->copy()->startOfDay(), $i = 0; $d->lte($until); $d->addDay(), $i++) {
                $daily['time'][] = $d->format('Y-m-d');
                $daily['temperature_2m_max'][] = 30.0 + $i;
                $daily['precipitation_sum'][] = 2.0 * $i;
                $daily['precipitation_probability_max'][] = 50 + $i;
                $daily['wind_speed_10m_max'][] = ($options['wind'] ?? true) === false ? null : 10.0 + $i;
                $daily['wind_gusts_10m_max'][] = ($options['wind'] ?? true) === false ? null : 20.0 + $i;
            }

            return Http::response(['hourly' => $hourly, 'daily' => $daily], 200);
        });
    }

    private function advisory(string $fromPht, string $untilPht, array $extra = []): PlantingAdvisory
    {
        return PlantingAdvisory::factory()->create(array_merge([
            'title' => 'Test advisory',
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'target_barangay' => null,
            'target_scope' => 'municipality',
            'source' => 'Open-Meteo + iClimate Rules',
            'valid_from' => Carbon::parse($fromPht, self::TZ)->utc(),
            'valid_until' => Carbon::parse($untilPht, self::TZ)->utc(),
            'metadata' => [],
        ], $extra));
    }

    /** The "Weather Behind This Advice" block only (the rest of the page may legitimately say N/A). */
    private function weatherSection(string $html): string
    {
        $start = strpos($html, 'data-advisory-weather');
        $this->assertNotFalse($start, 'weather section missing from the page');

        return substr($html, $start, strpos($html, '</details>', $start) - $start);
    }

    private function rows(array $result): array
    {
        return collect($result['rows'])->pluck('value', 'key')->all();
    }

    public function test_the_weather_follows_each_advisorys_own_time_window(): void
    {
        $this->fakeSource();
        $service = app(AdvisoryWeatherService::class);

        $afternoon = $service->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00'));
        $this->assertSame('available', $afternoon['status']);
        $this->assertSame('hourly', $afternoon['granularity']);
        $this->assertSame([
            'rainfall' => '9 mm',          // 6 hours x 1.5 mm
            'probability' => '60%',         // highest of 3 x (15..20)
            'temperature' => '40°C',        // 20 + 20
            'wind' => '25 km/h',            // 5 + 20
        ], $this->rows($afternoon));
        $this->assertStringContainsString('Sep 29, 3 PM – 9 PM', $afternoon['period']);

        $earlyMorning = $service->forAdvisory($this->advisory('2026-09-30 03:00', '2026-09-30 09:00'));
        $this->assertSame([
            'rainfall' => '0 mm',
            'probability' => '24%',         // 3 x 8
            'temperature' => '28°C',        // 20 + 8
            'wind' => '13 km/h',            // 5 + 8
        ], $this->rows($earlyMorning));
        $this->assertStringContainsString('Sep 30, 3 AM – 9 AM', $earlyMorning['period']);

        $this->assertNotSame($this->rows($afternoon), $this->rows($earlyMorning), 'different periods must not show the same weather');
    }

    public function test_the_window_is_read_in_philippine_time_not_utc(): void
    {
        $this->fakeSource();

        // Stored as UTC 07:00-13:00 == 3 PM-9 PM in Lian.
        $advisory = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');
        $this->assertSame('2026-09-29 07:00', $advisory->fresh()->valid_from->utc()->format('Y-m-d H:i'));

        $result = app(AdvisoryWeatherService::class)->forAdvisory($advisory->fresh());

        $this->assertSame('9 mm', $this->rows($result)['rainfall']);
        $this->assertStringContainsString('Philippine time', $result['period']);
    }

    public function test_a_barangay_advisory_uses_that_barangays_coordinates(): void
    {
        $this->fakeSource();
        $service = app(AdvisoryWeatherService::class);

        $municipal = $service->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00'));
        $barangay = $service->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00', ['target_barangay' => 'Binubusan', 'target_scope' => 'barangay']));

        $this->assertSame('Lian, Batangas', $municipal['location']);
        $this->assertSame('Binubusan, Lian, Batangas', $barangay['location']);
        $this->assertCount(2, $this->requests, 'a different place needs its own forecast request');
        $this->assertNotEquals($this->requests[0], $this->requests[1]);
    }

    public function test_a_multi_day_advisory_uses_whole_days_from_the_daily_forecast(): void
    {
        $this->fakeSource();

        // Sep 30 00:00 -> Oct 4 00:00 covers Sep 30, Oct 1, Oct 2, Oct 3 (day indexes 3..6).
        $result = app(AdvisoryWeatherService::class)->forAdvisory($this->advisory('2026-09-30 00:00', '2026-10-04 00:00'));

        $this->assertSame('daily', $result['granularity']);
        $this->assertSame([
            'rainfall' => '36 mm',          // 2 x (3+4+5+6)
            'probability' => '56%',
            'temperature' => '36°C',
            'wind' => '16 km/h',
        ], $this->rows($result));
        $this->assertStringContainsString('Sep 30 – Oct 3, 2026', $result['period']);
    }

    public function test_a_field_the_source_does_not_return_says_so_and_is_not_filled_in(): void
    {
        $this->fakeSource(['wind' => false]);

        $result = app(AdvisoryWeatherService::class)->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00'));

        $rows = $this->rows($result);
        $this->assertSame('available', $result['status']);
        $this->assertSame('40°C', $rows['temperature']);
        $this->assertNull($rows['wind'], 'a missing wind figure must stay null, never 0 or an estimate');
    }

    public function test_a_period_only_partly_covered_by_the_source_is_labelled_as_partial(): void
    {
        $this->fakeSource(['until' => '2026-09-30']);   // data ends at the end of Sep 30

        $result = app(AdvisoryWeatherService::class)->forAdvisory($this->advisory('2026-09-30 18:00', '2026-10-01 06:00'));

        $this->assertSame('available', $result['status']);
        $this->assertStringContainsString('Sep 30, 6 PM', $result['period']);
        $this->assertStringContainsString('only part of this advisory', implode(' ', $result['messages']));
    }

    public function test_an_unreachable_source_with_nothing_saved_says_unavailable_and_shows_no_numbers(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $result = app(AdvisoryWeatherService::class)->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00'));

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('could not be reached', implode(' ', $result['messages']));
    }

    public function test_a_stale_copy_is_used_only_when_the_source_fails_and_is_labelled(): void
    {
        $this->fakeSource();
        $service = app(AdvisoryWeatherService::class);
        $advisory = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');

        $fresh = $service->forAdvisory($advisory);
        $this->assertFalse($fresh['stale']);

        Carbon::setTestNow(Carbon::now()->addMinutes(45));                    // cache is stale now
        $this->sourceDown = true;

        $stale = $service->forAdvisory($advisory);

        $this->assertTrue($stale['stale']);
        $this->assertSame($this->rows($fresh), $this->rows($stale));
        $this->assertSame($fresh['updated'], $stale['updated'], 'the timestamp must still be when it was really retrieved');
        $this->assertStringContainsString('last forecast retrieved', implode(' ', $stale['messages']));
    }

    public function test_the_forecast_is_cached_and_refreshed_when_stale(): void
    {
        $this->fakeSource();
        $service = app(AdvisoryWeatherService::class);
        $advisory = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');

        $service->forAdvisory($advisory);
        $service->forAdvisory($advisory);
        $this->assertCount(1, $this->requests, 'repeat views inside the freshness window must not call the source again');

        Carbon::setTestNow(Carbon::now()->addMinutes(25));
        $service->forAdvisory($advisory);
        $this->assertCount(2, $this->requests, 'a stale forecast is refreshed automatically');
    }

    public function test_a_period_the_source_no_longer_covers_falls_back_to_the_forecast_saved_with_the_advisory(): void
    {
        $this->fakeSource();

        $old = $this->advisory('2026-08-14 12:00', '2026-09-13 12:00', ['metadata' => ['weather_basis' => [
            'expected_rainfall_mm' => 88.5, 'rain_probability_percent' => 98, 'maximum_temperature_c' => 24.7,
            'maximum_wind_speed_kmh' => 25.7, 'forecast_period' => '2026-08-14 to 2026-08-29',
        ]]]);
        $result = app(AdvisoryWeatherService::class)->forAdvisory($old);

        $this->assertSame('snapshot', $result['status']);
        $this->assertStringContainsString('saved when this advisory was generated', $result['kind']);
        $this->assertSame(['rainfall' => '88.5 mm', 'probability' => '98%', 'temperature' => '24.7°C', 'wind' => '25.7 km/h'], $this->rows($result));
    }

    public function test_a_past_period_with_nothing_saved_says_it_has_passed(): void
    {
        $this->fakeSource();

        $result = app(AdvisoryWeatherService::class)->forAdvisory($this->advisory('2026-07-29 17:00', '2026-07-29 23:00'));

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('already passed', implode(' ', $result['messages']));
    }

    public function test_pagasa_advisories_explain_where_the_numbers_come_from(): void
    {
        $this->fakeSource();
        $service = app(AdvisoryWeatherService::class);

        $pagasa = $service->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00', ['source' => 'PAGASA Online Advisory Page']));
        $rules = $service->forAdvisory($this->advisory('2026-09-29 15:00', '2026-09-29 21:00'));

        $this->assertStringContainsString('qualitative', implode(' ', $pagasa['messages']));
        $this->assertStringNotContainsString('qualitative', implode(' ', $rules['messages']));
        $this->assertSame($this->rows($pagasa), $this->rows($rules));
    }

    public function test_the_advisory_page_shows_the_window_weather_source_and_timestamps(): void
    {
        $this->fakeSource();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $afternoon = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');
        $morning = $this->advisory('2026-09-30 03:00', '2026-09-30 09:00');

        $first = $this->actingAs($mao)->get(route('planting-advisories.show', $afternoon))
            ->assertOk()
            ->assertSee('Weather Behind This Advice')
            ->getContent();
        $section = $this->weatherSection($first);
        foreach (['9 mm', '60%', '40°C', '25 km/h', 'Sep 29, 3 PM – 9 PM', 'Source:', 'Forecast issued:', 'Not provided by source', 'Last updated:', 'not observed weather'] as $expected) {
            $this->assertStringContainsString($expected, $section);
        }
        $this->assertStringNotContainsString('N/A', $section);

        $second = $this->weatherSection($this->actingAs($mao)->get(route('planting-advisories.show', $morning))->assertOk()->getContent());
        foreach (['24%', '28°C', '13 km/h', 'Sep 30, 3 AM – 9 AM'] as $expected) {
            $this->assertStringContainsString($expected, $second);
        }
        $this->assertStringNotContainsString('60%', $second);
    }

    public function test_the_advisory_page_shows_weather_data_unavailable_instead_of_placeholders(): void
    {
        $this->sourceDown = true;
        $this->fakeSource();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $advisory = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');

        $section = $this->weatherSection($this->actingAs($mao)->get(route('planting-advisories.show', $advisory))->assertOk()->getContent());

        $this->assertStringContainsString('Weather data unavailable', $section);
        $this->assertStringContainsString('could not be reached', $section);
        $this->assertStringNotContainsString('N/A', $section);
        $this->assertStringNotContainsString('Expected rainfall', $section);
    }

    public function test_the_advisory_page_shows_a_missing_field_as_not_provided_by_source(): void
    {
        $this->fakeSource(['wind' => false]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $advisory = $this->advisory('2026-09-29 15:00', '2026-09-29 21:00');

        $html = $this->actingAs($mao)->get(route('planting-advisories.show', $advisory))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/Strongest wind<\/dt>\s*<dd[^>]*>\s*<span class="text-muted">Not provided by source<\/span>/', $html);
        $this->assertStringContainsString('40°C', $html);
    }
}
