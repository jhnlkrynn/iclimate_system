<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsabilitySurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsabilitySurveyTest extends TestCase
{
    use RefreshDatabase;

    private function validAnswers(): array
    {
        $answers = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $answers["item_{$number}"] = $item['positive'] ? 5 : 1;
        }

        return $answers;
    }

    public function test_farmer_can_view_and_submit_the_survey(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get(route('usability-survey.create'))
            ->assertOk()
            ->assertSee('System Evaluation');

        $this->actingAs($farmer)
            ->post(route('usability-survey.store'), [...$this->validAnswers(), 'comments' => 'Very helpful for planning.'])
            ->assertRedirect(route('usability-survey.create'));

        $this->assertDatabaseHas('usability_survey_responses', [
            'user_id' => $farmer->id,
            'role' => User::ROLE_FARMER,
            'comments' => 'Very helpful for planning.',
        ]);

        $response = UsabilitySurveyResponse::query()->where('user_id', $farmer->id)->first();
        $this->assertSame(100.0, $response->score());
    }

    public function test_resubmitting_updates_the_existing_response_instead_of_duplicating(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->post(route('usability-survey.store'), $this->validAnswers());
        $this->assertSame(1, UsabilitySurveyResponse::query()->count());

        $worstAnswers = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $worstAnswers["item_{$number}"] = $item['positive'] ? 1 : 5;
        }

        $this->actingAs($farmer)->post(route('usability-survey.store'), $worstAnswers);

        $this->assertSame(1, UsabilitySurveyResponse::query()->count(), 'Resubmitting must update, not create a second row.');
        $this->assertSame(0.0, UsabilitySurveyResponse::query()->where('user_id', $farmer->id)->first()->score());
    }

    public function test_submission_requires_every_item_to_be_answered(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $incomplete = $this->validAnswers();
        unset($incomplete['item_7']);

        $this->actingAs($farmer)
            ->post(route('usability-survey.store'), $incomplete)
            ->assertSessionHasErrors(['item_7']);

        $this->assertDatabaseCount('usability_survey_responses', 0);
    }

    public function test_farmer_cannot_view_aggregate_results(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get(route('usability-survey.results'))
            ->assertForbidden();
    }

    public function test_mao_and_it_expert_can_view_aggregate_results_with_correct_statistics(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $itExpert = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        // One perfect (100) and one worst-case (0) response — mean must be 50.
        $bestUser = User::factory()->create(['role' => User::ROLE_FARMER]);
        $worstUser = User::factory()->create(['role' => User::ROLE_FARMER]);

        $best = [];
        $worst = [];
        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $best["item_{$number}"] = $item['positive'] ? 5 : 1;
            $worst["item_{$number}"] = $item['positive'] ? 1 : 5;
        }

        UsabilitySurveyResponse::query()->create(['user_id' => $bestUser->id, 'role' => User::ROLE_FARMER, ...$best]);
        UsabilitySurveyResponse::query()->create(['user_id' => $worstUser->id, 'role' => User::ROLE_FARMER, ...$worst]);

        $this->actingAs($mao)
            ->get(route('usability-survey.results'))
            ->assertOk()
            ->assertSee('50.0')
            ->assertSee('2');

        $this->actingAs($itExpert)
            ->get(route('usability-survey.results'))
            ->assertOk();
    }

    public function test_csv_export_contains_all_items_and_computed_score(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        UsabilitySurveyResponse::query()->create(['user_id' => $farmer->id, 'role' => User::ROLE_FARMER, ...$this->validAnswers()]);

        $response = $this->actingAs($mao)->get(route('usability-survey.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('sus_score', $response->getContent());
        $this->assertStringContainsString('item_10', $response->getContent());
        $this->assertStringContainsString('100', $response->getContent());
    }
}
