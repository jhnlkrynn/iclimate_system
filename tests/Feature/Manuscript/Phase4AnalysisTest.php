<?php

namespace Tests\Feature\Manuscript;

use App\Models\ClimateRecord;
use App\Models\User;
use App\Models\WeatherImpactAnalysis;
use App\Services\Climate\WeatherAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manuscript – Naicel (2026-10-08), Phase 4: Weather Analysis and Weather Impact Analysis. The pages read the project's
 * climate history (the Ambulong station record shipped with the app, 2015–2025) and the yield dataset (2020–2025).
 */
class Phase4AnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_mao_sees_charts_season_comparison_risk_signals_and_climate_vs_yield(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->get(route('analysis.weather'))->assertOk();
        $response->assertSee('waRainfallChart', false)->assertSee('waWindChart', false)
            ->assertSee('data-season-comparison', false)->assertSee('data-climate-indicators', false)
            ->assertSee('data-manuscript-tabs="analysis"', false);

        $this->assertSame(2020, $response->viewData('fromYear'));
        $this->assertGreaterThan(0, $response->viewData('count'));
        $this->assertSame(2020, (int) substr($response->viewData('chart')['labels'][0], -4));
    }

    public function test_filters_by_year_season_and_month(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $wet = $this->actingAs($mao)->get(route('analysis.weather', ['year' => 2023, 'season' => 'Wet']))->assertOk();
        $this->assertSame(6, $wet->viewData('count'));
        $june = $this->actingAs($mao)->get(route('analysis.weather', ['month' => 6]))->assertOk();
        $this->assertTrue(collect($june->viewData('chart')['labels'])->every(fn ($l) => str_starts_with($l, 'Jun')));
    }

    public function test_farmers_read_weather_analysis_in_filipino_without_staff_sections(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'preferred_language' => 'fil']);

        $this->actingAs($farmer)->get(route('analysis.weather'))->assertOk()
            ->assertSee('Pagsusuri ng Panahon')->assertSee('Tag-ulan laban sa tag-araw')
            ->assertDontSee('data-climate-indicators', false);
        $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertOk()->assertSee('href="'.route('analysis.weather').'"', false);
    }

    public function test_impact_analysis_ranks_variables_and_can_be_saved(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->get(route('analysis.impact'))->assertOk()->assertSee('data-impact-table', false);
        $result = $response->viewData('result');
        $this->assertGreaterThanOrEqual(WeatherAnalysisService::MIN_PAIRS, $result['pairs']);
        $this->assertNotNull($result['strongest']);
        $rs = collect($result['variables'])->pluck('r')->filter(fn ($r) => $r !== null)->map(fn ($r) => abs($r))->values();
        $this->assertSame($rs->sortDesc()->values()->all(), $rs->all());

        $this->actingAs($mao)->post(route('analysis.impact.store'), ['title' => 'Wet seasons', 'season' => 'Wet'])->assertRedirect();
        $saved = WeatherImpactAnalysis::query()->sole();
        $this->assertSame('Wet', $saved->filters['season']);
        $this->assertSame($mao->id, $saved->created_by);
        $this->actingAs($mao)->get(route('analysis.impact.show', $saved))->assertOk()->assertSee('Wet seasons')->assertSee('data-saved-analysis', false);
    }

    public function test_impact_levels_and_pearson(): void
    {
        $this->assertSame('High', WeatherAnalysisService::impactLevel(-0.62));
        $this->assertSame('Moderate', WeatherAnalysisService::impactLevel(0.31));
        $this->assertSame('Low', WeatherAnalysisService::impactLevel(0.1));
        $this->assertEqualsWithDelta(1.0, WeatherAnalysisService::pearson(collect([[1, 2], [2, 4], [3, 6]])), 1e-9);
    }

    public function test_access_follows_the_manuscript_matrix(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($it)->get(route('analysis.weather'))->assertStatus(403);
        $this->actingAs($farmer)->get(route('analysis.impact'))->assertStatus(403);
        $this->actingAs($farmer)->post(route('analysis.impact.store'), ['title' => 'x'])->assertStatus(403);
    }
}
