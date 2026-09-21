<?php

namespace Tests\Feature;

use App\Models\AdvisoryFeedback;
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

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertSee('Needs Your Attention')
            ->assertSee('Farmer Feedback Awaiting Reply')
            ->assertDontSee('Pending Advisory Reviews');
    }

    public function test_mao_dashboard_hides_attention_widget_when_nothing_pending(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('mao.dashboard'))
            ->assertOk()
            ->assertDontSee('Needs Your Attention');
    }
}
