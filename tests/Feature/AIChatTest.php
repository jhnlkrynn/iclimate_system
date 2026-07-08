<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\KnowledgeBase;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_ai_farming_assistant_response(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => [
                    'predicted_weather' => 'Rain',
                    'confidence' => 84,
                    'explanation' => 'Rainfall and humidity indicate rain.',
                ],
                'rice_yield_prediction' => [
                    'predicted_yield' => 4.25,
                    'unit' => 'tons/hectare',
                    'explanation' => 'Inputs are favorable.',
                ],
                'warnings' => [],
                'confidence_score' => 84,
                'response_time_ms' => 20,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Should I plant rice this week?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Planting Recommendation')
            ->assertJsonPath('chat.weather_prediction.predicted_weather', 'Rain');

        $this->assertDatabaseHas('a_i_chats', [
            'user_id' => $user->id,
            'question' => 'Should I plant rice this week?',
            'intent' => 'Planting Recommendation',
        ]);

        $this->assertSame(1, AIChat::query()->count());
    }

    public function test_ai_assistant_extracts_farmer_scenario_from_natural_language(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Dry', 'confidence' => 80],
                'rice_yield_prediction' => ['predicted_yield' => 2.4, 'unit' => 'tons/hectare'],
                'warnings' => [['title' => 'Drought', 'reason' => 'Low rainfall']],
                'confidence_score' => 80,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Rainfed farm, dry season, rainfall is 60mm, humidity 50%. Should I irrigate?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Irrigation Recommendation')
            ->assertJsonPath('chat.prediction_result.input_features.rainfall', 60)
            ->assertJsonPath('chat.prediction_result.input_features.humidity', 50)
            ->assertJsonPath('chat.prediction_result.input_features.farm_type', 'Rainfed')
            ->assertJsonPath('chat.prediction_result.input_features.season', 'Dry');
    }

    public function test_ai_assistant_uses_previous_intent_for_follow_up_question(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 81],
                'rice_yield_prediction' => ['predicted_yield' => 4.1, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 81,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        AIChat::query()->create([
            'user_id' => $user->id,
            'question' => 'Should I plant next week?',
            'answer' => 'Planting guidance.',
            'intent' => 'planting',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'What about that?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Planting Recommendation')
            ->assertJsonPath('chat.prediction_result.conversation_memory.last_intent', 'planting');
    }

    public function test_ai_assistant_calibrates_yield_with_historical_production_records(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 85],
                'rice_yield_prediction' => ['predicted_yield' => 2.0, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 85,
            ]),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => 'Balibago',
        ]);

        foreach ([5.0, 5.2, 5.4, 5.1, 5.3] as $index => $yield) {
            RiceProduction::query()->create([
                'barangay' => 'Balibago',
                'season' => 'Wet',
                'irrigation_type' => 'Rainfed',
                'yield_per_hectare' => $yield,
                'area_hectares' => 1,
                'total_production' => $yield,
                'year' => 2020 + $index,
            ]);
        }

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Predict rice yield for wet season.',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.rice_yield_prediction.raw_predicted_yield', 2)
            ->assertJsonPath('chat.rice_yield_prediction.calibrated', true)
            ->assertJsonPath('chat.prediction_result.calibration.applied', true)
            ->assertJsonPath('chat.prediction_result.quality.label', 'High reliability');

        $this->assertGreaterThan(
            2.0,
            $response->json('chat.rice_yield_prediction.predicted_yield')
        );
    }

    public function test_ai_assistant_lowers_reliability_for_unrealistic_inputs(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Dry', 'confidence' => 90],
                'rice_yield_prediction' => ['predicted_yield' => 3.8, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 90,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Rainfall is 800mm, temperature 45c, humidity 20%. Predict yield.',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.prediction_result.quality.label', 'Low reliability');

        $this->assertContains(
            'Rainfall is outside the normal model planning range.',
            $response->json('chat.prediction_result.quality.issues')
        );
    }

    public function test_ai_assistant_answers_system_questions_from_knowledge_base_without_prediction(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        KnowledgeBase::query()->create([
            'question' => 'How do I reset password?',
            'answer' => 'Use the forgot password link on the login page, enter your email, and follow the reset instructions.',
            'category' => 'System Help',
            'keywords' => ['reset', 'password', 'login'],
            'source_type' => 'Knowledge Base',
            'source_name' => 'iClimate Help',
            'verified' => true,
            'times_used' => 0,
            'confidence' => 88,
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'How do I reset password?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'User Profile')
            ->assertJsonPath('chat.source_type', 'Knowledge Base')
            ->assertJsonPath('chat.weather_prediction', null)
            ->assertJsonPath('chat.rice_yield_prediction', null);

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
    }

    public function test_ai_assistant_rejects_unsupported_system_question_without_external_ai(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'paano kaba tatalino?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.source_type', 'System Scope');

        $this->assertStringNotContainsString(
            "Answer:\nAnswer:",
            $response->json('chat.answer')
        );

        Http::assertNothingSent();
    }

    public function test_ai_assistant_explains_prediction_method_without_running_prediction(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'how do you predict weather?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'System Help')
            ->assertJsonPath('chat.source_type', 'Knowledge Base')
            ->assertJsonPath('chat.weather_prediction', null)
            ->assertJsonPath('chat.rice_yield_prediction', null);

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
    }

    public function test_ai_assistant_answers_tagalog_system_questions_in_tagalog(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Paano mo pinipredict ang weather?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'System Help')
            ->assertJsonPath('chat.language', 'Tagalog')
            ->assertJsonPath('chat.source_type', 'Knowledge Base');

        $answer = $response->json('chat.answer');
        $this->assertStringContainsString('Hinuhulaan ng iClimate', $answer);
        $this->assertStringContainsString('Hindi ako gumagawa ng sariling prediction values', $answer);

        Http::assertNothingSent();
    }

    public function test_ai_assistant_answers_tagalog_prediction_questions_in_tagalog(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 84],
                'rice_yield_prediction' => ['predicted_yield' => 4.25, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 84,
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Dapat ba akong magtanim ng palay ngayong linggo kung may ulan?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Planting Recommendation')
            ->assertJsonPath('chat.language', 'Tagalog');

        $answer = $response->json('chat.answer');
        $this->assertStringContainsString('Para sa pagtatanim', $answer);
        $this->assertStringContainsString('Mga input na ginamit', $answer);
        $this->assertStringContainsString('Rekomendasyon:', $answer);
        $this->assertStringContainsString('Susunod na hakbang:', $answer);
        $this->assertStringContainsString('Babala:', $answer);
    }

    public function test_ai_assistant_general_answer_does_not_show_report_template(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'paano kaba tatalino?',
        ]);

        $response->assertOk();

        $answer = $response->json('chat.answer');
        $this->assertStringNotContainsString('Explanation:', $answer);
        $this->assertStringNotContainsString('Recommendation:', $answer);
        $this->assertStringNotContainsString('Warning:', $answer);
        $this->assertStringNotContainsString('Source:', $answer);
        $this->assertStringNotContainsString('Confidence:', $answer);
    }

    public function test_ai_assistant_rejects_out_of_system_questions(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => '5+3?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.source_type', 'System Scope');

        $this->assertStringContainsString('iClimate', $response->json('chat.answer'));

        Http::assertNothingSent();
    }
}
