<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\FarmerAnswerStyle;
use App\Services\AI\IntentDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

/**
 * Farmers get Climora's answer first (Answer → Reason → Action, a few short sentences); the full report
 * stays one question away ("Why?"). MAO/IT keep the full report.
 */
class ClimoraFarmerBriefTest extends TestCase
{
    use PreparesPredictions;
    use RefreshDatabase;

    /** Report-only wording that must not appear in a farmer's short answer. */
    private const REPORT_TERMS = ['Risk Level', 'Prediction Reliability', 'Reliability', 'score', 'Random Forest', 'Inputs used', 'Next steps:', 'decision rules', 'model', 'fallback', '/100'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparePredictions();
        $this->recordRecentClimate();

        Http::fake([
            '127.0.0.1:19999/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 84, 'explanation' => 'x'],
                'rice_yield_prediction' => ['predicted_yield' => 4.25, 'unit' => 'tons/hectare', 'explanation' => 'x'],
                'warnings' => [],
                'confidence_score' => 84,
                'response_time_ms' => 20,
            ]),
        ]);
    }

    private function ask(User $user, string $question): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->postJson(route('ai-chat.message'), ['question' => $question])->assertOk();
    }

    public function test_a_farmer_gets_a_short_answer_first_and_the_full_report_on_why(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $first = $this->ask($farmer, 'When should I plant rice?');
        $answer = (string) $first->json('chat.answer');

        foreach (self::REPORT_TERMS as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $answer, "The short answer should not include \"{$term}\".");
        }
        $lines = array_values(array_filter(explode("\n", $answer)));
        $this->assertLessThanOrEqual(3, count($lines), 'answer line, then the reason and action');
        $this->assertLessThanOrEqual(5, preg_match_all('/[.!?](\s|$)/', $answer), 'a few short sentences');
        $this->assertTrue((bool) $first->json('chat.memory.brief'));
        $details = (string) $first->json('chat.memory.details');
        $this->assertGreaterThan(strlen($answer), strlen($details), 'the full report is kept for a follow-up');

        $why = $this->ask($farmer, 'Why?');
        $this->assertSame($details, $why->json('chat.answer'));
        $this->assertFalse((bool) $why->json('chat.memory.brief'));
    }

    public function test_a_farmer_who_asks_why_in_the_question_gets_the_full_report(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->ask($farmer, 'Why should I plant rice this week? Explain the details.');

        $this->assertFalse((bool) $response->json('chat.memory.brief'));
        $this->assertNull($response->json('chat.memory.details'));
    }

    public function test_staff_keep_the_full_report(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->ask($mao, 'When should I plant rice?');

        $this->assertFalse((bool) $response->json('chat.memory.brief'));
        $this->assertNull($response->json('chat.memory.details'));
    }

    /** The planting answers follow the decision engine's own planting items; only the wording is new. */
    public function test_planting_wording_follows_the_engine_result(): void
    {
        $answer = fn (array $items, string $farmType = 'Rainfed') => strtok((string) FarmerAnswerStyle::brief(
            IntentDetectionService::PLANTING_RECOMMENDATION, ['planting' => ['items' => $items]], [], [], [], [], $farmType, false
        ), "\n");

        $this->assertSame('You can plant now.', $answer(['Ideal planting period.']));
        $this->assertSame('You can plant, but monitor the weather closely.', $answer(['Plant with caution and monitor field drainage.']));
        $this->assertSame('Wait before planting.', $answer(['Delay planting due to flood risk.']));
        $this->assertSame('Plant only if you have enough water available.', $answer(['Wait for sufficient rainfall before planting.']));
        $this->assertSame('Plant only if you have enough water available.', $answer(['Wait for sufficient rainfall before planting.'], 'Irrigated'));
        $this->assertSame('You can plant now, but keep checking the weather.', $answer(['Planting is possible with routine weather monitoring.']));
    }

    public function test_irrigation_wording_is_plain_and_unknown_engine_sentences_pass_through(): void
    {
        $irrigation = fn (string $recommendation) => (string) FarmerAnswerStyle::brief(
            IntentDetectionService::IRRIGATION_RECOMMENDATION, ['irrigation' => ['recommendation' => $recommendation]], [], [], [], [], 'Rainfed', false
        );

        $this->assertStringStartsWith('Give your field more water.', $irrigation('Increase irrigation or provide supplemental water support.'));
        $this->assertStringNotContainsString('supplemental', $irrigation('Increase irrigation or provide supplemental water support.'));
        $this->assertStringStartsWith('A new engine sentence.', $irrigation('A new engine sentence.'));
    }
}
