<?php

namespace Tests\Feature;

use App\Models\AdvisoryFeedback;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisory\AdvisoryVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdvisoryFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_can_submit_and_update_feedback_on_an_advisory(): void
    {
        $farmer = User::factory()->farmer()->create();
        $advisory = PlantingAdvisory::query()->create($this->advisoryPayload());

        $this->actingAs($farmer)->post(route('planting-advisories.feedback', $advisory), [
            'is_helpful' => '1',
            'comment' => 'Worked well for my field.',
        ])->assertRedirect();

        $this->assertDatabaseHas('advisory_feedback', [
            'planting_advisory_id' => $advisory->id,
            'user_id' => $farmer->id,
            'is_helpful' => 1,
            'comment' => 'Worked well for my field.',
        ]);

        // Submitting again updates the same row rather than creating a duplicate.
        $this->actingAs($farmer)->post(route('planting-advisories.feedback', $advisory), [
            'is_helpful' => '0',
            'comment' => 'Actually it rained more than expected.',
        ])->assertRedirect();

        $this->assertSame(1, AdvisoryFeedback::query()->where('planting_advisory_id', $advisory->id)->count());
        $this->assertDatabaseHas('advisory_feedback', [
            'planting_advisory_id' => $advisory->id,
            'user_id' => $farmer->id,
            'is_helpful' => 0,
        ]);
    }

    public function test_mao_can_view_and_respond_to_feedback_which_notifies_the_farmer(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create();
        $advisory = PlantingAdvisory::query()->create($this->advisoryPayload());
        $feedback = AdvisoryFeedback::query()->create([
            'planting_advisory_id' => $advisory->id,
            'user_id' => $farmer->id,
            'is_helpful' => false,
            'comment' => 'This did not match actual conditions.',
        ]);

        $this->actingAs($farmer)
            ->post(route('advisory-feedback.respond', $feedback), ['mao_response' => 'Thanks for flagging this.'])
            ->assertForbidden();

        $this->actingAs($mao)
            ->post(route('advisory-feedback.respond', $feedback), ['mao_response' => 'Thanks for flagging this, we will review the data.'])
            ->assertRedirect();

        $this->assertDatabaseHas('advisory_feedback', [
            'id' => $feedback->id,
            'mao_response' => 'Thanks for flagging this, we will review the data.',
            'mao_responded_by' => $mao->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $farmer->id,
            'type' => 'Advisory',
        ]);

        $this->actingAs($mao)
            ->get(route('planting-advisories.show', $advisory))
            ->assertOk()
            ->assertSee('Farmer Feedback (1)')
            ->assertSee('This did not match actual conditions.')
            ->assertSee('Needs review');
    }

    public function test_verification_service_badges_advisories_by_feedback_ratio(): void
    {
        $advisory = PlantingAdvisory::query()->create($this->advisoryPayload());
        $service = app(AdvisoryVerificationService::class);

        $this->assertSame('Unverified', $service->score($advisory->fresh(['feedback']))['badge']);

        foreach (range(1, 3) as $i) {
            AdvisoryFeedback::factory()->create([
                'planting_advisory_id' => $advisory->id,
                'is_helpful' => true,
            ]);
        }

        $this->assertSame('Community-verified', $service->score($advisory->fresh(['feedback']))['badge']);

        AdvisoryFeedback::query()->where('planting_advisory_id', $advisory->id)->update(['is_helpful' => false]);

        $this->assertSame('Needs review', $service->score($advisory->fresh(['feedback']))['badge']);
    }

    public function test_advisory_details_remain_available_if_feedback_migration_is_pending(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $advisory = PlantingAdvisory::query()->create($this->advisoryPayload());

        Schema::drop('advisory_feedback');

        $this->actingAs($mao)
            ->get(route('planting-advisories.show', $advisory))
            ->assertOk()
            ->assertSee('Advisory feedback is temporarily unavailable')
            ->assertSee($advisory->title);
    }

    private function advisoryPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Advisory',
            'content' => 'Message',
            'message' => 'Message',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Summary',
            'severity' => 'information',
            'target_scope' => 'municipality',
            'source' => 'Open-Meteo + iClimate Rules',
            'status' => 'published',
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addDay(),
            'posted_by' => User::factory()->maoPersonnel()->create()->id,
        ], $overrides);
    }
}
