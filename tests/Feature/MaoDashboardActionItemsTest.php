<?php

namespace Tests\Feature;

use App\Models\AdvisoryFeedback;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaoDashboardActionItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mao_dashboard_renders_and_highlights_pending_action_items(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $publishedAdvisory = PlantingAdvisory::query()->create([
            'title' => 'Published Advisory',
            'content' => 'Message',
            'message' => 'Message',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Summary',
            'severity' => 'information',
            'target_scope' => 'municipality',
            'source' => 'iClimate Python Analytics',
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(6),
            'posted_by' => $mao->id,
        ]);
        AdvisoryFeedback::factory()->create([
            'planting_advisory_id' => $publishedAdvisory->id,
            'is_helpful' => false,
            'mao_response' => null,
        ]);

        HeatmapArea::factory()->create(['barangay' => 'Matabungkay', 'risk_level' => 'Severe']);

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertSee('Needs Your Attention')
            ->assertSee('Farmer Feedback Awaiting Reply')
            ->assertSee('High/Severe Risk Barangays')
            ->assertDontSee('Pending Advisory Reviews');
    }

    public function test_mao_dashboard_hides_attention_widget_when_nothing_pending(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        // Migrations pre-seed two barangays (Malaruhatan, Matabungkay) as High risk by
        // default; clear them so this "nothing pending" scenario is genuinely empty.
        HeatmapArea::query()->delete();

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('Needs Your Attention');
    }
}
