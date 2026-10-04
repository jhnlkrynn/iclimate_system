<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ComputesRiskRatings;
use Tests\TestCase;

class MaoDashboardSimpleWordingTest extends TestCase
{
    use ComputesRiskRatings;
    use RefreshDatabase;

    private function maoHtml(): string
    {
        $this->computedRiskRating();   // the risk card and map show once a barangay has a computed rating
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();

        return preg_replace(['#<script\b.*?</script>#is', '#<style\b.*?</style>#is'], '', $html);
    }

    public function test_mao_dashboard_has_no_technical_terms(): void
    {
        $html = $this->maoHtml();

        foreach (['Random Forest', 'RandomForest', 'Python', 'Open-Meteo', 'Prediction confidence', 'insufficient input', 'Agricultural Risk Classification', 'Risk score'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $html, "Technical term leaked to MAO: {$term}");
        }
    }

    public function test_risk_sections_keep_planning_guide_note_and_no_data_legend(): void
    {
        $html = $this->maoHtml();

        $this->assertStringContainsString('planning guide', $html);
        $this->assertStringContainsString('id="barangayRiskMap"', $html);
        $this->assertStringContainsString('No Data', $html);
        $this->assertStringContainsString('not Low risk', $html);
    }

    public function test_feedback_section_shows_minimum_report_message_on_the_operations_page_only(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $operations = $this->actingAs($mao)->get(route('mao.operations'))->assertOk()->getContent();

        $this->assertStringContainsString('Not enough farmer reports yet', $operations);
        $this->assertStringNotContainsString('Not enough farmer reports yet', $this->maoHtml());     // the overview hides an incomplete dataset
    }

    public function test_the_operations_page_has_no_technical_terms_either(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $html = preg_replace(['#<script\b.*?</script>#is', '#<style\b.*?</style>#is'], '', $this->actingAs($mao)->get(route('mao.operations'))->assertOk()->getContent());

        foreach (['Random Forest', 'RandomForest', 'Python', 'Open-Meteo', 'Prediction confidence', 'insufficient input', 'Agricultural Risk Classification', 'Risk score'] as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $html, "Technical term leaked to MAO: {$term}");
        }
    }

    public function test_dashboard_is_forbidden_for_farmer(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->get(route('mao.dashboard'))->assertForbidden();
    }
}
