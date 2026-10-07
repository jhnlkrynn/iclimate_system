<?php

namespace Tests\Feature;

use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Advisories\PagasaBulletinParser;
use App\Support\FarmerAdvisoryView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Advisories cards show real, current, verifiable information: PAGASA's own agri-outlook table (not one long
 * paragraph), PAGASA kept separate from iClimate's own tips, statuses from the advisory's real severity, the values
 * iClimate's advice was calculated from, and plain "temporarily unavailable" states instead of stale or made-up data.
 */
class FarmerAdvisoryContentTest extends TestCase
{
    use RefreshDatabase;

    private const AGRI_URL = 'https://bagong.pagasa.dost.gov.ph/ten-day-regional-agri-weather';

    /** The structure of PAGASA's ten-day agri page (one region), as published. */
    private function agriPage(): string
    {
        $li = fn (array $items) => '<ul>'.implode('', array_map(fn ($i) => "<li>{$i}</li>", $items)).'</ul>';

        return '<html><body><div class="article-content"><p>TEN-DAY REGIONAL AGRI-WEATHER INFORMATION 1-10 OCTOBER 2026</p>'
            .'<div class="region-list region-details-6"><table><thead>'
            .'<tr><th>Region 4A CALABARZON</th><th>WEATHER CONDITIONS</th></tr>'
            .'<tr><th>Forecast Rainfall (mm): <br><p>10– 150</p></th><th>Relative Humidity (%): <br><p>50 – 100</p></th></tr>'
            .'<tr><th>No. of Rainy Days <br> (>1 mm): <p>3 – 7</p></th><th>Temperature (°C): <br><p>Upland: 20 – 33; Lowland: 24 – 35</p></th></tr>'
            .'</thead><tbody>'
            .'<tr><th><img src="x.jpg"></th><th>RICE</th><th>'.$li(['Land and Seedbed Preparation', 'Reproductive Stage', 'Ripening/Maturing Stage', 'Harvesting Stage']).'</th></tr>'
            .'<tr><th>CORN</th><th>'.$li(['Harvesting Stage']).'</th></tr>'
            .'<tr><th>FARM OPERATIONS</th><th>'.$li(['Fertilizer Application', 'Weed Management', 'Water Management', 'Postharvest']).'</th></tr>'
            .'</tbody></table></div></div></body></html>';
    }

    private function advisory(array $attributes): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(array_merge([
            'title' => 'Advisory', 'content' => 'Message.', 'message' => 'Message.', 'summary' => 'Message.', 'type' => 'Climate', 'advisory_type' => 'climate',
            'severity' => 'information', 'priority' => 25, 'target_scope' => 'municipality', 'source' => 'Open-Meteo + iClimate Rules',
            'generated_automatically' => true, 'status' => 'published', 'valid_from' => now()->subHour(), 'valid_until' => now()->addDay(),
            'published_at' => now()->subHour(), 'posted_by' => User::factory()->maoPersonnel()->create()->id,
            'metadata' => ['source_type' => 'iclimate', 'scope_type' => 'municipality'],
        ], $attributes));
    }

    private function pagasa(string $title, string $text, string $scopeType, string $scopeName, array $metadata = []): PlantingAdvisory
    {
        return $this->advisory(['title' => $title, 'content' => $text, 'message' => $text, 'summary' => $text,
            'source' => PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY, 'source_url' => self::AGRI_URL, 'recommended_action' => 'Check field moisture and the latest forecast before irrigating, fertilizing, or spraying.',
            'published_at' => null,
            'metadata' => array_merge(['is_official' => true, 'official_source' => true, 'source_type' => 'pagasa', 'scope_type' => $scopeType, 'scope_name' => $scopeName], $metadata)]);
    }

    private function farmerPage(?string $barangay = 'San Diego'): string
    {
        $farmer = User::factory()->farmer()->create(['barangay' => $barangay]);

        return (string) $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->getContent();
    }

    public function test_the_agri_outlook_table_is_read_from_pagasas_own_cells(): void
    {
        $bulletins = app(PagasaBulletinParser::class)->parse($this->agriPage(), ['url' => self::AGRI_URL, 'horizon' => 'online']);

        $this->assertCount(1, $bulletins);
        $this->assertSame('CALABARZON Agricultural Weather Outlook', $bulletins[0]['title']);
        $this->assertNull($bulletins[0]['published'], 'PAGASA gives no issue time on this page; none is invented.');
        $this->assertSame([
            'weather' => ['rainfall_mm' => '10– 150', 'relative_humidity_percent' => '50 – 100', 'rainy_days' => '3 – 7', 'temperature_c' => 'Upland: 20 – 33; Lowland: 24 – 35'],
            'crops' => ['Rice' => ['Land and Seedbed Preparation', 'Reproductive Stage', 'Ripening/Maturing Stage', 'Harvesting Stage'], 'Corn' => ['Harvesting Stage']],
            'farm_operations' => ['Fertilizer Application', 'Weed Management', 'Water Management', 'Postharvest'],
        ], $bulletins[0]['details']);
    }

    public function test_a_refetch_adds_the_table_to_an_advisory_saved_without_it(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 10, 5, 2, 0, 0, 'UTC'));
        config(['services.pagasa.regional_forecast_urls' => [self::AGRI_URL], 'services.pagasa.weekly_outlook_url' => null]);
        Http::fake(['*' => Http::response($this->agriPage())]);
        $service = app(PagasaAdvisoryService::class);

        $service->fetchAndStore(true);
        $advisory = PlantingAdvisory::query()->fromPagasaOnline()->sole();
        $advisory->update(['metadata' => collect($advisory->metadata)->except('pagasa_details')->all()]);

        $service->fetchAndStore(true);

        $this->assertSame(1, PlantingAdvisory::query()->fromPagasaOnline()->count(), 'The same bulletin is not stored twice.');
        $this->assertSame(['Fertilizer Application', 'Weed Management', 'Water Management', 'Postharvest'], $advisory->fresh()->metadata['pagasa_details']['farm_operations']);
    }

    public function test_the_calabarzon_card_shows_the_outlook_as_short_sections(): void
    {
        $this->pagasa('CALABARZON Agricultural Weather Outlook', 'Region 4A CALABARZON WEATHER CONDITIONS ...', 'regional', 'CALABARZON (includes Lian, Batangas)', [
            'pagasa_details' => app(PagasaBulletinParser::class)->parse($this->agriPage(), ['url' => self::AGRI_URL, 'horizon' => 'online'])[0]['details'],
        ]);
        $html = $this->farmerPage();

        foreach (['10–150 mm', '50–100%', '3–7 days', 'Lowland 24–35°C · Upland 20–33°C', 'Rice activities', 'Ripening/Maturing Stage',
            'Recommended farm operations (PAGASA)', 'Water Management', 'This PAGASA advisory includes Lian, Batangas. PAGASA scope: CALABARZON region.',
            'Published: Not provided by source'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        // PAGASA's own operations replace iClimate's generic tip on this card.
        $this->assertStringNotContainsString('iClimate tip', $html);
    }

    public function test_an_older_calabarzon_row_still_shows_its_forecast_values(): void
    {
        $this->pagasa('CALABARZON Agricultural Weather Outlook',
            'Region 4A CALABARZON WEATHER CONDITIONS Forecast Rainfall (mm): 10– 150 Relative Humidity (%): 50 – 100 No. of Rainy Days (>1 mm): 3 – 7 Temperature (°C): Upland: 20 – 33; Lowland: 24 – 35 RICE Land and Seedbed Preparation FARM OPERATIONS Water Management',
            'regional', 'CALABARZON (includes Lian, Batangas)');

        $view = new FarmerAdvisoryView(PlantingAdvisory::query()->sole());
        $this->assertSame(['rainfall_mm' => '10– 150', 'relative_humidity_percent' => '50 – 100', 'rainy_days' => '3 – 7', 'temperature_c' => 'Upland: 20 – 33; Lowland: 24 – 35'], $view->pagasaDetails()['weather']);
        $this->assertStringContainsString('10–150 mm', $this->farmerPage());
    }

    public function test_pagasa_and_iclimate_stay_separate_and_statuses_come_from_severity(): void
    {
        $this->pagasa('Batangas Provincial Weather Forecast', 'Partly cloudy to cloudy skies with isolated rainshowers or thunderstorms', 'province', 'Lian, Batangas · Province-wide advisory');
        $this->pagasa('PAGASA Weekly Weather Outlook', 'THE REST OF THE COUNTRY WILL GENERALLY HAVE PARTLY CLOUDY SKIES.', 'regional', 'National outlook (includes Lian, Batangas)');
        $this->advisory(['title' => '24-Hour Consider Postponing Irrigation', 'recommended_action' => 'Delay irrigation when field conditions allow because significant rainfall is expected.',
            'metadata' => ['source_type' => 'iclimate', 'scope_type' => 'municipality', 'weather_basis' => ['expected_rainfall_mm' => 21, 'rain_probability_percent' => 97, 'maximum_temperature_c' => 30.5, 'maximum_wind_speed_kmh' => 12.5]]]);
        $this->advisory(['title' => 'Heavy Rainfall Warning', 'severity' => 'high', 'priority' => 80]);
        $html = $this->farmerPage();

        // The PAGASA forecast's suggestion is labelled as iClimate's; PAGASA's text is shown as written (normal case).
        $this->assertStringContainsString('What farmers can do · iClimate tip', $html);
        $this->assertStringContainsString('The rest of the country will generally have partly cloudy skies.', $html);
        $this->assertStringContainsString('National outlook — supporting information, not specific to Lian.', $html);
        $this->assertStringContainsString('Philippines (national outlook)', $html, 'A national outlook is never placed in Lian.');
        // Advice is never shown as a Low risk; real warnings keep their level.
        $this->assertStringContainsString('<span>Advice</span>', $html);
        $this->assertStringContainsString('<span>Information</span>', $html);
        $this->assertStringContainsString('<span>High</span>', $html);
        $this->assertStringNotContainsString('<span>Low</span>', $html);
        // The values iClimate's advice was calculated from.
        foreach (['21 mm rain', '97% chance', '30.5°C', '12.5 km/h wind'] as $value) {
            $this->assertStringContainsString($value, $html, $value);
        }
        $this->assertStringContainsString('iClimate Local Farming Guidance', $html);
    }

    public function test_the_featured_barangay_card_uses_the_barangays_own_readings(): void
    {
        $render = fn (?array $weather) => view('advisories._barangay-weather-card', [
            'selectedBarangay' => 'San Diego', 'barangayAdvisory' => null, 'barangayWeather' => $weather,
        ])->render();
        $weather = ['status' => 'fresh', 'stale' => false, 'condition' => 'Light rain', 'temperature_c' => 27.4, 'rainfall_24h_mm' => 11, 'rain_probability_percent' => 97,
            'humidity_percent' => 88, 'wind_speed_kmh' => 9.5, 'observed_at' => '2026-10-06T08:00:00+08:00', 'fetched_at' => '2026-10-06T00:05:00+00:00'];

        $fresh = $render($weather);
        foreach (['27.4°C', 'Rainfall 11 mm', '97% rain chance', '88% humidity', 'Wind 9.5 km/h', 'Light rain', 'Latest weather', 'Updated: Oct 6, 2026 · 8:00 AM', 'Checked: 8:05 AM'] as $text) {
            $this->assertStringContainsString(e($text), $fresh, $text);
        }

        $stale = $render(['stale' => true, 'status' => 'stale'] + $weather);
        $this->assertStringContainsString('Update delayed', $stale);
        $this->assertStringContainsString('not current conditions', $stale);

        $this->assertStringContainsString('Latest data temporarily unavailable', $render(null));
        $this->assertStringContainsString('Latest data temporarily unavailable', $render(['status' => 'unavailable']));
    }

    public function test_the_lian_forecast_line_shows_todays_forecast_and_never_an_outdated_one_as_current(): void
    {
        $record = ExternalWeatherData::query()->create(['source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.03, 'longitude' => 120.65,
            'forecast_date' => \App\Support\BusinessClock::today()->toDateString(), 'temperature_max' => 30.5, 'humidity' => 76, 'rainfall_mm' => 21,
            'precipitation_probability' => 97, 'wind_speed' => 8.3, 'fetched_at' => now()->subMinutes(10)]);

        $html = $this->farmerPage();
        // Each value as a tile: the value, then what it is.
        foreach (['Today in Lian', '<strong>21 mm</strong><small>Rainfall</small>', '<strong>97%</strong><small>Rain chance</small>', '<strong>30.5°C</strong><small>Highest temp.</small>',
            '<strong>76%</strong><small>Humidity</small>', '<strong>8.3 km/h</strong><small>Wind</small>', 'Open-Meteo forecast'] as $text) {
            $this->assertStringContainsString(str_starts_with($text, '<') ? $text : e($text), $html, $text);
        }

        $record->update(['fetched_at' => now()->subHours(12)]);
        $old = $this->farmerPage('Matabungkay');
        $this->assertStringContainsString('Latest data temporarily unavailable: no current Lian forecast to calculate from.', $old);
        $this->assertStringNotContainsString('Today in Lian', $old);
    }

    public function test_only_the_newest_version_of_a_pagasa_bulletin_is_shown(): void
    {
        // PAGASA changed the outlook's text within the same period without a new issue time: two stored rows.
        $old = $this->pagasa('CALABARZON Agricultural Weather Outlook', 'Older text.', 'regional', 'CALABARZON (includes Lian, Batangas)', ['bulletin_family' => 'family-1',
            'pagasa_details' => ['weather' => ['rainfall_mm' => '5 – 50'], 'crops' => ['Rice' => ['Harvesting Stage']], 'farm_operations' => []]]);
        $old->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->pagasa('CALABARZON Agricultural Weather Outlook', 'Newer text.', 'regional', 'CALABARZON (includes Lian, Batangas)', ['bulletin_family' => 'family-1',
            'pagasa_details' => ['weather' => ['rainfall_mm' => '10 – 150'], 'crops' => ['Rice' => ['Vegetative Stage']], 'farm_operations' => []]]);
        $html = $this->farmerPage();

        $this->assertSame(1, substr_count($html, '<h3 class="fad-title">CALABARZON Agricultural Weather Outlook</h3>'));
        $this->assertStringContainsString('10–150 mm', $html);
        $this->assertStringNotContainsString('5–50 mm', $html);
    }

    public function test_a_farmer_without_a_barangay_gets_a_clean_update_profile_state(): void
    {
        $html = $this->farmerPage(null);

        $this->assertStringContainsString('Add your barangay to your profile to see advice for your barangay first.', $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringNotContainsString('data-barangay-advisory', $html, 'No barangay is guessed.');
    }
}
