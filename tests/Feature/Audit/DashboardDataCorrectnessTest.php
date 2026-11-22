<?php

namespace Tests\Feature\Audit;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\TyphoonSafetyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit 2026-11-22: the numbers on the farmer dashboard (tiles and summary panel) against what is saved in the
 * database and what the model returned, computed independently here: farm size, planting and harvest dates, days left,
 * crop progress, expected yield per hectare and total tons = t/ha x farm size.
 */
class DashboardDataCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['prediction.rice_yield.gate' => 'off']);
        Http::fake(['*' => Http::response([], 500)]);
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn(null));
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(['success' => false]));
    }

    /** The visible text of one tile, or of one panel section, without tags. */
    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)))));
    }

    private function tile(string $page, string $key): string
    {
        $this->assertSame(1, preg_match('#<button type="button" class="fh-kpi" data-fh-kpi="'.$key.'"[^>]*>(.*?)</button>#s', $page, $m), "tile {$key}");

        return $this->text($m[1]);
    }

    private function panel(string $page, string $section): string
    {
        $this->assertSame(1, preg_match('#<template data-panel-template="'.$section.'">(.*?)</template>#s', $page, $m), "panel {$section}");

        return $this->text($m[1]);
    }

    public function test_dashboard_numbers_match_the_database_and_the_model_output(): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Prenza', 'farm_type' => 'Rainfed']);
        Farm::query()->where('user_id', $farmer->id)->delete();         // only the farm this test defines
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Audit Field', 'barangay' => 'Prenza',
            'farm_size_hectares' => 2.35, 'farm_size_method' => Farm::SIZE_MAPPED, 'farm_type' => 'Rainfed', 'is_primary' => true]);
        $planted = $today->subDays(40);
        $harvest = $today->addDays(70);
        FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'farm_id' => $farm->id, 'planting_date' => $planted->toDateString(),
            'scheduled_harvest_date' => $harvest->toDateString(), 'season' => 'Wet', 'farm_type' => 'Rainfed', 'barangay' => 'Prenza', 'farm_area_hectares' => 2.35]);

        // The model's answer, stored as the background warm-up stores it.
        $modelYield = 3.87;
        Cache::flush();
        Cache::forever(FarmerDashboardService::predictionCacheKey($farmer->id, $farm->id), [
            'success' => true, 'yield_tons_per_hectare' => $modelYield, 'predicted_yield' => $modelYield,
            'target_month' => \App\Support\RiceSeason::harvestMonth($today)->format('Y-m'), 'ran_at' => $today->toIso8601String(),
        ]);

        $page = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();

        // Farm size: the saved hectares, how they were measured, and the farm type.
        $this->assertStringContainsString('Farm size 2.35 ha Rainfed', $this->tile($page, 'farm'));
        $farmPanel = $this->panel($page, 'farm-size');
        foreach (['Audit Field', 'Prenza', '2.35 ha', 'Rainfed', 'Measured from your farm map'] as $fact) {
            $this->assertStringContainsString($fact, $farmPanel);
        }

        // Days to harvest: computed here from the saved dates.
        $daysLeft = (int) $today->diffInDays($harvest);
        $progress = (int) round(40 / 110 * 100);
        $this->assertStringContainsString("Days to harvest {$daysLeft} days", $this->tile($page, 'crop'));
        $cropPanel = $this->panel($page, 'days-to-harvest');
        $this->assertStringContainsString('Planting date '.$planted->format('M j, Y'), $cropPanel);
        $this->assertStringContainsString('Expected harvest date '.$harvest->format('M j, Y'), $cropPanel);
        $this->assertStringContainsString("Days left {$daysLeft} days", $cropPanel);
        $this->assertStringContainsString("{$progress}% of the growing time has passed", $cropPanel);

        // Expected harvest: the model's t/ha times the saved farm size, rounded as the tile shows it.
        $tons = number_format($modelYield * 2.35, 2);                    // 9.09
        $this->assertStringContainsString("Expected harvest this season {$tons} tons", $this->tile($page, 'expected'));
        $expectedPanel = $this->panel($page, 'expected-harvest');
        $this->assertStringContainsString("3.87 t/ha × 2.35 ha = {$tons} tons", $expectedPanel);

        // The yield card shows the same per-hectare number the tile multiplied.
        $this->assertMatchesRegularExpression('#data-fh-harvest[^>]*>.*?3\.87\s*<small>t/ha</small>#s', $page);
    }

    public function test_a_farm_with_only_an_estimated_size_never_gets_a_made_up_total(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        Farm::query()->where('user_id', $farmer->id)->delete();
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Rough Field', 'barangay' => 'Prenza',
            'farm_size_hectares' => null, 'farm_size_method' => Farm::SIZE_ESTIMATED, 'farm_size_estimate_range' => '1_2', 'farm_type' => 'Rainfed', 'is_primary' => true]);
        Cache::flush();
        Cache::forever(FarmerDashboardService::predictionCacheKey($farmer->id, $farm->id), [
            'success' => true, 'yield_tons_per_hectare' => 3.87, 'predicted_yield' => 3.87, 'farm_size_basis' => Farm::SIZE_ESTIMATED,
            'farm_size_range' => '1_2', 'target_month' => \App\Support\RiceSeason::harvestMonth(now('Asia/Manila'))->format('Y-m'), 'ran_at' => now()->toIso8601String(),
        ]);

        $page = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-fh-kpi="expected"', $page);
        $this->assertStringContainsString('Estimated', $this->tile($page, 'farm'));
    }
}
