<?php

namespace Tests\Feature;

use App\Jobs\RunModelManagement;
use App\Models\FarmerSurveyResponse;
use App\Models\MlModel;
use App\Models\ModelFeedbackDecision;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Feedback\ModelFeedbackService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Farmer Survey & Model Feedback – Naicel (2026-10-09): Farmer → Survey → IT Expert → Model Evaluation → Maintain or
 * Retrain → Updated Insights. The system recommends from the answers; only the IT Expert decides, and a retraining
 * never replaces the model in use.
 */
class FarmerSurveyFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00', 'Asia/Manila'));
    }

    private function farmer(string $barangay = 'Prenza', array $more = []): User
    {
        return User::factory()->farmer()->create($more + ['barangay' => $barangay, 'status' => User::STATUS_ACTIVE]);
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function advisory(): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(['title' => 'Clear the drainage canals', 'content' => 'Heavy rain is expected.', 'message' => 'Heavy rain is expected.', 'type' => 'Planting',
            'advisory_type' => 'planting', 'severity' => 'low', 'target_scope' => 'municipality', 'status' => 'published', 'valid_from' => now()->subDay(), 'valid_until' => now()->addWeek(),
            'posted_by' => User::factory()->create(['role' => User::ROLE_MAO])->id]);
    }

    private function prediction(User $farmer): YieldPredictionRequest
    {
        return YieldPredictionRequest::query()->create(['user_id' => $farmer->id, 'scope' => YieldPredictionRequest::SCOPE_FARM, 'barangay' => 'Prenza', 'season' => 'Wet',
            'farm_type' => 'Irrigated', 'area_hectares' => 1.5, 'target_month' => '2027-09-01', 'status' => YieldPredictionRequest::DONE, 'predicted_yield' => 4.2, 'completed_at' => now()]);
    }

    /** Saves answers straight to the table: [useful, accurate, helpful] each, in one category. */
    private function answers(array $scores, string $category = FarmerSurveyResponse::CATEGORY_PREDICTIONS, string $barangay = 'Prenza'): void
    {
        foreach ($scores as [$useful, $accurate, $helpful]) {
            FarmerSurveyResponse::query()->create(['user_id' => $this->farmer($barangay)->id, 'subject' => FarmerSurveyResponse::SUBJECT_PREDICTION, 'category' => $category,
                'barangay' => $barangay, 'useful' => $useful, 'accurate' => $accurate, 'helpful' => $helpful, 'answered_on' => now('Asia/Manila')->toDateString()]);
        }
    }

    // ------------------------------------------------------------ 1. the farmer's survey

    public function test_a_farmer_answers_the_survey_and_it_is_saved_with_the_date_farmer_and_related_item(): void
    {
        $farmer = $this->farmer();
        $advisory = $this->advisory();
        $prediction = $this->prediction($farmer);

        $this->actingAs($farmer)->get(route('farmer.survey.show', ['advisory' => $advisory->id]))->assertOk()
            ->assertSee('About the advisory: Clear the drainage canals')->assertSee('Was it useful for your farm work?');
        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'advisory', 'planting_advisory_id' => $advisory->id, 'useful' => 5, 'accurate' => 4, 'helpful' => 5, 'comment' => ' It helped me drain the field in time. '])
            ->assertRedirect(route('farmer.survey.show'))->assertSessionHas('success');
        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'prediction', 'yield_prediction_request_id' => $prediction->id, 'useful' => 2, 'accurate' => 1, 'helpful' => 2])->assertRedirect();

        $saved = FarmerSurveyResponse::query()->orderBy('id')->get();
        $this->assertCount(2, $saved);
        $this->assertSame([$farmer->id, 'advisory', 'advisories', $advisory->id, null, 'Prenza', 'It helped me drain the field in time.', '2026-10-09'],
            [$saved[0]->user_id, $saved[0]->subject, $saved[0]->category, $saved[0]->planting_advisory_id, $saved[0]->yield_prediction_request_id, $saved[0]->barangay, $saved[0]->comment, $saved[0]->answered_on->toDateString()]);
        $this->assertSame('positive', $saved[0]->sentiment());
        $this->assertSame(['prediction', 'predictions', $prediction->id, 'negative'], [$saved[1]->subject, $saved[1]->category, $saved[1]->yield_prediction_request_id, $saved[1]->sentiment()]);
        $this->assertNotEmpty($saved[1]->model_version);

        // Answering again the same day about the same item corrects the answer instead of adding another.
        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'prediction', 'yield_prediction_request_id' => $prediction->id, 'useful' => 4, 'accurate' => 4, 'helpful' => 4])->assertRedirect();
        $this->assertSame(2, FarmerSurveyResponse::query()->count());
        $this->assertSame(4, $saved[1]->fresh()->accurate);
    }

    public function test_answers_are_checked_and_only_farmers_may_answer(): void
    {
        $farmer = $this->farmer();
        $other = $this->prediction($this->farmer('Cumba'));

        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'weather', 'useful' => 6, 'accurate' => 0, 'helpful' => 3])->assertSessionHasErrors(['useful', 'accurate']);
        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'weather', 'useful' => 3, 'accurate' => 3])->assertSessionHasErrors('helpful');
        // Another farmer's prediction cannot be answered about.
        $this->actingAs($farmer)->post(route('farmer.survey.store'), ['subject' => 'prediction', 'yield_prediction_request_id' => $other->id, 'useful' => 3, 'accurate' => 3, 'helpful' => 3])->assertSessionHasErrors('yield_prediction_request_id');
        $this->assertSame(0, FarmerSurveyResponse::query()->count());

        foreach ([User::ROLE_MAO] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('farmer.survey.show'))->assertForbidden();
        }
        $this->get('/logout');
    }

    public function test_the_survey_is_in_filipino_for_a_farmer_who_reads_filipino_and_is_linked_from_home(): void
    {
        $farmer = $this->farmer('Prenza', ['preferred_language' => 'fil']);

        $this->actingAs($farmer)->get(route('farmer.survey.show'))->assertOk()->assertSee('Nakatulong ba ito sa gawain mo sa bukid?')->assertSee('Ipadala ang sagot ko');
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('href="'.route('farmer.survey.show').'"', false);

        $keys = fn (string $locale): array => array_keys(Arr::dot((require lang_path($locale.'/farmer.php'))['survey']));
        $this->assertSame($keys('en'), $keys('fil'));
    }

    // ------------------------------------------------------------ 2. IT: counts, percentages, categories, review







    // ------------------------------------------------------------ 3. maintain or retrain

    public function test_the_recommendation_follows_the_feedback_about_the_model(): void
    {
        $status = fn (): string => app(ModelFeedbackService::class)->modelStatus()['recommended'];

        $this->answers([[5, 5, 5], [1, 1, 1]]);
        $this->assertSame('not_enough', $status(), 'a handful of answers decides nothing');

        // Weather and advisory answers do not speak about the model.
        $this->answers([[1, 1, 1], [1, 1, 1], [1, 1, 1], [1, 1, 1]], FarmerSurveyResponse::CATEGORY_WEATHER);
        $this->assertSame('not_enough', $status());

        $this->answers([[5, 4, 5], [4, 4, 4], [5, 5, 4]]);                      // 4 positive, 1 negative of 5
        $this->assertSame('maintain', $status());

        $this->answers([[1, 2, 1], [2, 1, 2], [1, 1, 1]], FarmerSurveyResponse::CATEGORY_MODEL);   // now 4 negative of 8 = 50%
        $this->assertSame('needs_retraining', $status());

        // Answers older than the window no longer count.
        $this->travelTo(now()->addDays(ModelFeedbackService::WINDOW_DAYS + 1));
        $this->assertSame('not_enough', $status());
    }

    public function test_low_accuracy_answers_alone_ask_for_retraining(): void
    {
        // Useful and helpful, but farmers say it was not correct: accurate averages 2.0.
        $this->answers([[4, 2, 4], [4, 2, 4], [4, 2, 4], [4, 2, 4], [4, 2, 4]]);

        $model = app(ModelFeedbackService::class)->modelStatus();
        $this->assertSame([0, 'needs_retraining'], [$model['tally']['negative'], $model['recommended']]);
    }



    // ------------------------------------------------------------ 4. live insights


}
