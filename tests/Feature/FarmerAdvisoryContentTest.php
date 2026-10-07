<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\PagasaAdvisoryService;
use App\Services\Advisories\PagasaBulletinParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Backend coverage for PAGASA bulletin parsing and persistence. Legacy farmer-card assertions were removed with PR #40's old advisory partials. */
class FarmerAdvisoryContentTest extends TestCase
{
    use RefreshDatabase;

    private const AGRI_URL = 'https://bagong.pagasa.dost.gov.ph/ten-day-regional-agri-weather';

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

    public function test_the_agri_outlook_table_is_read_from_pagasas_own_cells(): void
    {
        $bulletins = app(PagasaBulletinParser::class)->parse($this->agriPage(), ['url' => self::AGRI_URL, 'horizon' => 'online']);

        $this->assertCount(1, $bulletins);
        $this->assertSame('CALABARZON Agricultural Weather Outlook', $bulletins[0]['title']);
        $this->assertNull($bulletins[0]['published']);
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

        app(PagasaAdvisoryService::class)->fetchAndStore(true);
        $advisory = PlantingAdvisory::query()->fromPagasaOnline()->sole();
        $advisory->update(['metadata' => collect($advisory->metadata)->except('pagasa_details')->all()]);
        app(PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertSame(1, PlantingAdvisory::query()->fromPagasaOnline()->count());
        $this->assertSame(['Fertilizer Application', 'Weed Management', 'Water Management', 'Postharvest'], $advisory->fresh()->metadata['pagasa_details']['farm_operations']);
    }
}
