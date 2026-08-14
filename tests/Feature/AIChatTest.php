<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\ClimateRecord;
use App\Models\KnowledgeBase;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\AI\IntentDetectionService;
use App\Services\Prediction\PredictionDateValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
            ->assertJsonPath('chat.weather_prediction.predicted_weather', 'Dry');

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

    public function test_ai_assistant_uses_random_forest_model_for_missing_weather_inputs(): void
    {
        Http::fake([
            '127.0.0.1:5001/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 83],
                'rice_yield_prediction' => ['predicted_yield' => 4.4, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 83,
            ]),
        ]);

        foreach (range(1, 6) as $month) {
            ClimateRecord::query()->create([
                'record_date' => sprintf('2026-%02d-15', $month),
                'rainfall' => 80 + ($month * 8),
                'temperature' => 27 + ($month * 0.2),
                'humidity' => 70 + $month,
                'wind_speed' => 8 + ($month * 0.3),
                'season' => $month >= 5 ? ClimateRecord::SEASON_WET : ClimateRecord::SEASON_DRY,
                'source' => 'PAGASA',
            ]);
        }

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Should I plant rice this week?',
        ]);

        $response->assertOk();

        $this->assertIsNumeric($response->json('chat.prediction_result.input_features.rainfall'));
        $this->assertIsNumeric($response->json('chat.prediction_result.input_features.temp_avg'));
        $this->assertIsNumeric($response->json('chat.prediction_result.input_features.humidity'));
        $this->assertIsNumeric($response->json('chat.prediction_result.input_features.wind_speed'));

        $this->assertStringContainsString(
            'Random Forest model',
            implode(' ', $response->json('chat.prediction_result.input_features.source_notes'))
        );
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

    public function test_ai_assistant_uses_raw_model_yield_without_calibration(): void
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
            ->assertJsonPath('chat.rice_yield_prediction.fallback', false);

        $this->assertEquals(2.0, $response->json('chat.rice_yield_prediction.predicted_yield'));
        $this->assertArrayNotHasKey('calibrated', $response->json('chat.rice_yield_prediction'));
        $this->assertArrayNotHasKey('raw_predicted_yield', $response->json('chat.rice_yield_prediction'));
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

    public function test_ai_assistant_always_returns_fallback_yield_when_model_api_is_unreachable(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Connection refused');
        });

        $user = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => 'Matabungkay',
        ]);

        foreach ([4.1, 4.3, 4.2] as $index => $yield) {
            RiceProduction::query()->create([
                'barangay' => 'Matabungkay',
                'season' => 'Dry',
                'irrigation_type' => 'Irrigated',
                'yield_per_hectare' => $yield,
                'area_hectares' => 1,
                'total_production' => $yield,
                'year' => 2023 + $index,
            ]);
        }

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'What is my predicted rice yield? Dry season, irrigated farm, rainfall is 121mm, temperature is 31.1c, humidity is 87.9%, wind is 39.8',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Rice Yield Prediction')
            ->assertJsonPath('chat.rice_yield_prediction.fallback', true)
            ->assertJsonPath('chat.rice_yield_prediction.unit', 'tons/hectare');

        $predictedYield = $response->json('chat.rice_yield_prediction.predicted_yield');

        $this->assertIsNumeric($predictedYield);
        $this->assertGreaterThan(0, $predictedYield);
        $this->assertStringNotContainsString('not available', $response->json('chat.answer'));
        $this->assertStringContainsString('tons/hectare', $response->json('chat.answer'));
    }

    public function test_ai_assistant_uses_groq_to_explain_prediction_when_trained_model_is_unavailable(): void
    {
        config([
            'services.groq.enabled' => true,
            'services.groq.key' => 'test-groq-key',
            'services.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake(function ($request) {
            if (str_contains((string) $request->url(), '127.0.0.1:5001/predict')) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'The trained Python model is unavailable, so I used iClimate fallback values. Your rainfed field is dry, so delay planting unless supplemental water is available.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 180,
                    'completion_tokens' => 28,
                    'total_tokens' => 208,
                ],
            ]);
        });

        $user = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => 'Matabungkay',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Should I plant this week? Rainfed farm, dry season, rainfall is 20mm, temperature is 30c, humidity is 80%, wind is 10',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.source_type', 'Machine Learning + Generative AI')
            ->assertJsonPath('chat.prediction_result.generative_ai.provider', 'Groq')
            ->assertJsonPath('chat.rice_yield_prediction.fallback', true);

        $this->assertStringContainsString('trained Python model is unavailable', $response->json('chat.answer'));
        $this->assertStringContainsString('delay planting', $response->json('chat.answer'));
        $this->assertIsNumeric($response->json('chat.rice_yield_prediction.predicted_yield'));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-groq-key')
            && $request->url() === 'https://api.groq.com/openai/v1/chat/completions');
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
        config(['services.groq.enabled' => false]);

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

    public function test_ai_assistant_explains_weather_data_sources_without_running_prediction(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'how do you gather weather predictions',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'System Help')
            ->assertJsonPath('chat.source_type', 'Knowledge Base')
            ->assertJsonPath('chat.weather_prediction', null)
            ->assertJsonPath('chat.rice_yield_prediction', null);

        $answer = $response->json('chat.answer');
        $this->assertStringContainsString('Open-Meteo', $answer);
        $this->assertStringContainsString('Predict.py', $answer);
        $this->assertStringNotContainsString('Predicted Weather:', $answer);

        Http::assertNothingSent();
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

    public function test_ai_assistant_answers_sino_ka_in_tagalog(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'sino ka?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'System Help')
            ->assertJsonPath('chat.language', 'Tagalog')
            ->assertJsonPath('chat.source_type', 'Knowledge Base');

        $answer = $response->json('chat.answer');
        $this->assertStringContainsString('Ako si Climora AI', $answer);
        $this->assertStringNotContainsString('English', $answer);

        Http::assertNothingSent();
    }

    public function test_ai_assistant_honors_explicit_tagalog_request(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'tagalog',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.language', 'Tagalog');

        $answer = $response->json('chat.answer');
        $this->assertStringNotContainsString('set to respond in English', $answer);
        $this->assertStringNotContainsString('Unfortunately', $answer);

        Http::assertNothingSent();
    }

    public function test_ai_assistant_keeps_tagalog_preference_for_next_question(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'tagalog',
        ])->assertOk();

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'who are you?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.language', 'Tagalog');

        $answer = $response->json('chat.answer');
        $this->assertStringContainsString('Ako si Climora AI', $answer);
        $this->assertStringNotContainsString('I am Climora AI', $answer);

        Http::assertNothingSent();
    }

    public function test_ai_language_detector_follows_user_language_and_preferences(): void
    {
        $detector = app(IntentDetectionService::class);

        $this->assertSame('Tagalog', $detector->detectLanguage('sino ka?'));
        $this->assertSame('Tagalog', $detector->detectLanguage('tagalog'));
        $this->assertSame('English', $detector->detectLanguage('Who are you?'));
        $this->assertSame('Cebuano/Bisaya', $detector->detectLanguage('bisaya'));
        $this->assertSame('Cebuano/Bisaya', $detector->detectLanguage('Who are you?', [
            'recent_questions' => ['bisaya'],
        ]));
        $this->assertSame('Japanese', $detector->detectLanguage('あなたは誰ですか'));
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

    public function test_ai_assistant_uses_groq_for_supported_questions_without_saved_answer(): void
    {
        config([
            'services.groq.enabled' => true,
            'services.groq.key' => 'test-groq-key',
            'services.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Use soil-test results first, then split nitrogen applications around active tillering and panicle initiation.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 18,
                    'total_tokens' => 118,
                ],
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'How should I plan fertilizer for rice this season?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'Farming Advisory')
            ->assertJsonPath('chat.source_type', 'Generative AI')
            ->assertJsonPath('chat.source_name', 'Groq llama-3.3-70b-versatile')
            ->assertJsonPath('chat.prediction_result.generative_ai.provider', 'Groq');

        $this->assertStringContainsString('soil-test', $response->json('chat.answer'));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-groq-key')
            && $request->url() === 'https://api.groq.com/openai/v1/chat/completions');
    }

    public function test_ai_assistant_uses_groq_for_harmless_general_knowledge_when_enabled(): void
    {
        config([
            'services.groq.enabled' => true,
            'services.groq.key' => 'test-groq-key',
            'services.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Science is the organized study of the natural world through observation, questions, experiments, and evidence.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 90,
                    'completion_tokens' => 20,
                    'total_tokens' => 110,
                ],
            ]),
        ]);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'what is science?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.intent', 'General Knowledge')
            ->assertJsonPath('chat.source_type', 'Generative AI')
            ->assertJsonPath('chat.prediction_result.generative_ai.provider', 'Groq');

        $this->assertStringContainsString('organized study', $response->json('chat.answer'));
    }

    public function test_ai_assistant_rejects_out_of_system_questions(): void
    {
        config(['services.groq.enabled' => false]);

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

    public function test_ai_assistant_rejects_prediction_for_a_past_month(): void
    {
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $pastMonth = now()->subMonths(2)->format('F Y');

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => "Predict rice yield for {$pastMonth}.",
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.answer', PredictionDateValidator::ERROR_MESSAGE)
            ->assertJsonPath('chat.weather_prediction', null)
            ->assertJsonPath('chat.rice_yield_prediction', null)
            ->assertJsonPath('chat.prediction_result.validation_error', true);

        Http::assertNothingSent();
    }
}
