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
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE]);
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

        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
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

    public function test_the_it_page_counts_positive_and_negative_answers_per_category(): void
    {
        $this->answers([[5, 5, 5], [4, 4, 4], [5, 4, 4], [1, 2, 1]], FarmerSurveyResponse::CATEGORY_PREDICTIONS);
        $this->answers([[1, 1, 2], [3, 3, 3]], FarmerSurveyResponse::CATEGORY_WEATHER);

        $summary = app(ModelFeedbackService::class)->summary();

        $this->assertSame([6, 3, 2, 50, 33], array_values(Arr::only($summary['all'], ['total', 'positive', 'negative', 'positive_percent', 'negative_percent'])));
        $this->assertSame([4, 3, 1, 75, 25], array_values(Arr::only($summary['categories']['predictions'], ['total', 'positive', 'negative', 'positive_percent', 'negative_percent'])));
        $this->assertSame([2, 0, 1, 0, 50], array_values(Arr::only($summary['categories']['weather_data'], ['total', 'positive', 'negative', 'positive_percent', 'negative_percent'])));
        $this->assertSame(0, $summary['categories']['advisories']['total']);

        $html = $this->actingAs($this->it())->get(route('admin.feedback.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-manuscript-tabs="settings"', $html);
        $this->assertSame(1, preg_match('#<tr data-feedback-category="predictions"[^>]*>(.*?)</tr>#s', $html, $m));
        $this->assertStringContainsString('3 (75%) 1 (25%)', (string) preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<td data-total>.*?</td>#s', '', $m[1]))));
        $this->assertStringContainsString('6 waiting for review', $html);
    }

    public function test_it_moves_an_answer_to_another_category_and_marks_it_reviewed(): void
    {
        $it = $this->it();
        $this->answers([[1, 1, 1]], FarmerSurveyResponse::CATEGORY_PREDICTIONS);
        $response = FarmerSurveyResponse::query()->firstOrFail();

        $this->actingAs($it)->put(route('admin.feedback.update', $response), ['category' => 'weather_data', 'reviewed' => '1'])->assertRedirect();

        $response->refresh();
        $this->assertSame(['weather_data', $it->id], [$response->category, $response->reviewed_by]);
        $this->assertNotNull($response->reviewed_at);
        $this->assertDatabaseHas('system_logs', ['action' => 'Reviewed Farmer Feedback']);
        // The filters find it; an unknown category is refused.
        $this->actingAs($it)->get(route('admin.feedback.index', ['category' => 'weather_data', 'answer' => 'negative']))->assertOk()->assertSee('data-feedback-row="'.$response->id.'"', false);
        $this->actingAs($it)->get(route('admin.feedback.index', ['review' => 'waiting']))->assertOk()->assertDontSee('data-feedback-row="'.$response->id.'"', false);
        $this->actingAs($it)->put(route('admin.feedback.update', $response), ['category' => 'nonsense'])->assertSessionHasErrors('category');
    }

    public function test_only_the_it_expert_opens_model_feedback(): void
    {
        foreach ([$this->farmer(), User::factory()->create(['role' => User::ROLE_MAO])] as $user) {
            $this->actingAs($user)->get(route('admin.feedback.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.feedback.insights'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.feedback.decide'), ['decision' => 'needs_retraining', 'reviewed_feedback' => '1'])->assertForbidden();
        }
        $this->assertSame(0, ModelFeedbackDecision::query()->count());
    }

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

    public function test_nothing_is_retrained_without_the_it_experts_decision_and_the_model_in_use_is_never_replaced(): void
    {
        $active = MlModel::query()->create(['purpose' => MlModel::PURPOSE_RICE_YIELD, 'name' => 'Rice yield', 'model_type' => 'Random Forest', 'version' => 'v1',
            'target_variable' => 'AVERAGE_YIELD', 'file_path' => 'storage/models/x.pkl', 'status' => MlModel::STATUS_ACTIVE, 'is_active' => true, 'trained_at' => now()->subMonth()]);
        $this->answers([[1, 1, 1], [1, 2, 1], [2, 1, 1], [1, 1, 2], [5, 5, 5]]);
        $it = $this->it();

        // The page shows "Needs Retraining" as a recommendation only; opening it starts nothing.
        $this->actingAs($it)->get(route('admin.feedback.index'))->assertOk()->assertSee('data-model-recommended="needs_retraining"', false)->assertSee('recommended only; no IT decision yet');
        Queue::assertNotPushed(RunModelManagement::class);

        // A decision needs the IT Expert's confirmation that the feedback was read.
        $this->actingAs($it)->post(route('admin.feedback.decide'), ['decision' => 'needs_retraining', 'start_retraining' => '1', 'algorithm' => 'rf'])->assertSessionHasErrors('reviewed_feedback');
        Queue::assertNotPushed(RunModelManagement::class);

        // "Maintain" with the retraining box ticked starts no retraining.
        $this->actingAs($it)->post(route('admin.feedback.decide'), ['decision' => 'maintain', 'start_retraining' => '1', 'algorithm' => 'rf', 'reviewed_feedback' => '1'])->assertRedirect(route('admin.feedback.index'));
        Queue::assertNotPushed(RunModelManagement::class);

        $this->actingAs($it)->post(route('admin.feedback.decide'), ['decision' => 'needs_retraining', 'start_retraining' => '1', 'algorithm' => 'rf', 'reviewed_feedback' => '1', 'note' => 'Farmers in Prenza say the numbers are too high.'])
            ->assertRedirect(route('admin.feedback.index'));
        Queue::assertPushed(RunModelManagement::class, fn (RunModelManagement $job): bool => $job->action === 'retrain' && $job->algorithm === 'rf' && $job->userId === $it->id);

        $decision = ModelFeedbackDecision::query()->latest('id')->firstOrFail();
        $this->assertSame(['needs_retraining', 'needs_retraining', 'v1', 5, 1, 4, 'rf', $it->id], [$decision->decision, $decision->recommended, $decision->model_version, $decision->responses, $decision->positive, $decision->negative, $decision->retrain_algorithm, $decision->decided_by]);
        $this->assertDatabaseHas('system_logs', ['action' => 'Model Feedback Decision']);
        // The model in use is untouched.
        $this->assertTrue($active->fresh()->is_active);
        $this->assertSame(1, MlModel::query()->where('is_active', true)->count());

        $html = $this->actingAs($it)->get(route('admin.feedback.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-model-status="needs_retraining"', $html);
        $this->assertStringContainsString('Farmers in Prenza say the numbers are too high.', $html);
    }

    // ------------------------------------------------------------ 4. live insights

    public function test_insights_change_as_soon_as_a_new_answer_or_decision_is_saved(): void
    {
        $it = $this->it();
        $read = fn (): array => $this->actingAs($it)->getJson(route('admin.feedback.insights'))->assertOk()->json();

        $empty = $read();
        $this->assertSame(['model', 'satisfaction', 'weather', 'attention', 'recommendation', 'results'], array_column($empty['items'], 'key'));
        $this->assertStringContainsString('No farmer has answered about the predictions yet.', $empty['items'][0]['text']);
        $this->assertSame('Model: Not enough feedback yet', $empty['items'][4]['title']);

        $this->answers([[5, 5, 5], [5, 4, 4], [4, 4, 5], [4, 5, 4], [5, 5, 4]]);
        $good = $read();
        $this->assertNotSame($empty['signature'], $good['signature']);
        $this->assertStringContainsString('5 positive and 0 negative of 5 answers', $good['items'][0]['text']);
        $this->assertStringContainsString('100% of 5 answers are positive', $good['items'][1]['text']);
        $this->assertSame(['Model: Maintain Current Model', 'good'], [$good['items'][4]['title'], $good['items'][4]['tone']]);
        $this->assertStringContainsString('5 answers are waiting for review.', $good['items'][3]['text']);
        $this->assertStringContainsString('data-insight="recommendation"', $good['html']);

        // Negative answers from one barangay: it is named under "areas needing attention" and the model is up for review.
        $this->answers([[1, 1, 1], [1, 1, 2], [2, 1, 1], [1, 2, 1], [1, 1, 1]], FarmerSurveyResponse::CATEGORY_PREDICTIONS, 'Cumba');
        $bad = $read();
        $this->assertNotSame($good['signature'], $bad['signature']);
        $this->assertStringContainsString('Negative answers from Cumba (5).', $bad['items'][3]['text']);
        $this->assertSame(['Model: Needs Retraining', 'danger', 'needs_retraining'], [$bad['items'][4]['title'], $bad['items'][4]['tone'], $bad['model']['recommended']]);
        $this->assertStringContainsString('Waiting for the IT Expert to review before any retraining.', $bad['items'][4]['text']);

        $this->actingAs($it)->post(route('admin.feedback.decide'), ['decision' => 'maintain', 'reviewed_feedback' => '1'])->assertRedirect();
        $decided = $read();
        $this->assertStringContainsString('IT decision on Oct 9, 2026: Maintain Current Model.', $decided['items'][4]['text']);
        $this->assertSame('maintain', $decided['model']['status']);

        // A prediction request shows up too.
        $this->prediction($this->farmer());
        $this->assertStringContainsString('1 yield prediction requested in the last 7 days', $read()['items'][5]['text']);
    }
}
