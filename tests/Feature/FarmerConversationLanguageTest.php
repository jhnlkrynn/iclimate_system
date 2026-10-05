<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\AIChat;
use App\Models\ClimateRecord;
use App\Models\User;
use App\Services\AI\IntentDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

class FarmerConversationLanguageTest extends TestCase
{
    use PreparesPredictions;
    use RefreshDatabase;

    private function farmer(array $attributes = []): User
    {
        return User::factory()->create(['role' => User::ROLE_FARMER, ...$attributes]);
    }

    private function ask(User $farmer, string $question, ?string $language = null): array
    {
        $payload = ['question' => $question, 'save_conversation' => true];

        if ($language !== null) {
            $payload['language'] = $language;
        }

        return $this->actingAs($farmer)
            ->postJson(route('ai-chat.message'), $payload)
            ->assertOk()
            ->json('chat');
    }

    public function test_farmer_can_choose_english_or_tagalog_and_preference_persists_without_clearing_chat(): void
    {
        $farmer = $this->farmer();
        $chat = $this->ask($farmer, 'Who are you?', 'en');

        $this->assertDatabaseHas('a_i_chats', ['id' => $chat['id'], 'question' => 'Who are you?']);
        $this->assertSame('en', $farmer->fresh()->ai_language);

        $this->actingAs($farmer)
            ->patchJson(route('ai-chat.language'), ['language' => 'tl'])
            ->assertOk()
            ->assertJsonPath('language', 'tl');

        $this->assertSame('tl', $farmer->fresh()->ai_language);
        $this->assertDatabaseHas('a_i_chats', ['id' => $chat['id']]);
        $this->assertSame(1, AIChat::query()->count());
    }

    public function test_manual_language_overrides_english_or_tagalog_detection_and_restores_on_reopen(): void
    {
        $farmer = $this->farmer(['ai_language' => 'tl']);

        $tagalog = $this->ask($farmer, 'Who are you?');
        $this->assertSame('Tagalog', $tagalog['language']);

        $this->actingAs($farmer)
            ->patchJson(route('ai-chat.language'), ['language' => 'en'])
            ->assertOk();

        $english = $this->ask($farmer, 'Sino ka?');
        $this->assertSame('English', $english['language']);
        $this->assertSame('en', $farmer->fresh()->ai_language);
    }

    public function test_taglish_question_uses_the_selected_conversation_language(): void
    {
        $detector = app(IntentDetectionService::class);

        $this->assertSame('Tagalog', $detector->detectLanguage('Should I magtanim ngayon?', [], 'Tagalog'));
        $this->assertSame('English', $detector->detectLanguage('Pwede ba magtanim ngayon?', [], 'English'));
    }

    public function test_invalid_language_save_does_not_change_the_authoritative_preference_or_create_chat_error(): void
    {
        $farmer = $this->farmer(['ai_language' => 'en']);

        $this->actingAs($farmer)
            ->patchJson(route('ai-chat.language'), ['language' => 'Tagalog'])
            ->assertStatus(422);

        $this->assertSame('en', $farmer->fresh()->ai_language);
        $this->assertSame(0, AIChat::query()->count());
    }

    public function test_language_save_failure_is_not_rendered_as_a_chat_message(): void
    {
        $widget = file_get_contents(resource_path('views/components/ai-chat-widget.blade.php'));

        $this->assertStringContainsString('value="tl"', $widget);
        $this->assertStringContainsString('iclimate_ai_language', $widget);
        $this->assertStringContainsString('icAiLanguageNotice', $widget);
        $this->assertStringNotContainsString("addMessage('assistant', languageText('Hindi na-save ang wika", $widget);
    }

    public function test_ai_language_column_exists_and_greetings_stay_out_of_prediction_logic(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'ai_language'));

        $farmer = $this->farmer(['ai_language' => 'tl']);
        $tagalog = $this->ask($farmer, 'hoy');

        $this->assertSame('greeting', $tagalog['intent']);
        $this->assertSame('Hello! Ano ang maitutulong ko sa iyo ngayon?', $tagalog['answer']);
        $this->assertNull($tagalog['prediction_result']);

        $farmer->forceFill(['ai_language' => 'en'])->save();
        $english = $this->ask($farmer, 'hello');

        $this->assertSame('greeting', $english['intent']);
        $this->assertSame('Hello! How can I help you today?', $english['answer']);
        $this->assertNull($english['prediction_result']);
    }

    public function test_switching_language_keeps_the_same_underlying_prediction_data(): void
    {
        $this->preparePredictions();
        $this->recentClimate();
        Http::fake([
            '127.0.0.1:19999/predict' => Http::response([
                'weather_prediction' => ['predicted_weather' => 'Rain', 'confidence' => 84],
                'rice_yield_prediction' => ['predicted_yield' => 4.25, 'unit' => 'tons/hectare'],
                'warnings' => [],
                'confidence_score' => 84,
            ]),
        ]);

        $farmer = $this->farmer(['ai_language' => 'en']);
        $english = $this->ask($farmer, 'Should I plant rice this week?');

        $farmer->forceFill(['ai_language' => 'tl'])->save();
        $tagalog = $this->ask($farmer, 'Should I plant rice this week?');

        foreach (['input_features', 'rice_yield_prediction', 'decision_support', 'api_error', 'target_date'] as $key) {
            $this->assertSame(
                $english['prediction_result'][$key] ?? null,
                $tagalog['prediction_result'][$key] ?? null,
                "underlying prediction field {$key} changed with the language",
            );
        }
        $this->assertSame($english['planting_recommendation'], $tagalog['planting_recommendation']);
        $this->assertNotSame($english['language'], $tagalog['language']);
    }

    public function test_refreshing_error_follows_the_selected_language(): void
    {
        Queue::fake();
        $farmer = $this->farmer(['ai_language' => 'tl']);

        $tagalog = $this->ask($farmer, 'Predict my rice yield for 2 hectares.');
        $this->assertSame('Tagalog', $tagalog['language']);
        $this->assertStringContainsString('Ini-refresh pa', $tagalog['answer']);

        $farmer->forceFill(['ai_language' => 'en'])->save();
        $english = $this->ask($farmer, 'Predict my rice yield for 2 hectares.');
        $this->assertSame('English', $english['language']);
        $this->assertStringContainsString('being refreshed', $english['answer']);
        Queue::assertPushed(GenerateMonthlyWeatherForecast::class);
    }

    private function recentClimate(): void
    {
        foreach (range(1, 6) as $monthsAgo) {
            ClimateRecord::factory()->create([
                'record_date' => now()->subMonths($monthsAgo)->startOfMonth()->toDateString(),
                'rainfall' => 150 + $monthsAgo * 10,
                'temperature' => 27 + $monthsAgo * 0.2,
                'humidity' => 80,
                'wind_speed' => 10,
                'season' => ClimateRecord::SEASON_WET,
            ]);
        }
    }
}
