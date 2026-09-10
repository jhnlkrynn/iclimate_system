<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\ChatbotResponse;
use App\Models\User;
use Database\Seeders\ChatbotResponseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_answers_from_managed_keyword_response(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        ChatbotResponse::query()->create([
            'category' => 'Dashboard Help',
            'question' => 'How do I use the dashboard?',
            'answer' => 'Use the dashboard to review weather, advisories, records, and profile shortcuts.',
            'language' => 'English',
            'keywords' => ['dashboard', 'home overview'],
            'synonyms' => ['dashbord'],
            'visible_roles' => [User::ROLE_FARMER],
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Can you explain my dashboard?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.source_type', 'Chatbot Knowledge Base');

        $this->assertStringContainsString('Dashboard Help', $response->json('chat.answer'));
        $this->assertStringContainsString('review weather', $response->json('chat.answer'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
    }

    public function test_chatbot_respects_role_visibility(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        ChatbotResponse::query()->create([
            'category' => 'System Logs',
            'question' => 'Where are system logs?',
            'answer' => 'IT Admins can open System Logs from the sidebar.',
            'language' => 'English',
            'keywords' => ['system logs', 'errors'],
            'synonyms' => ['logs'],
            'visible_roles' => [User::ROLE_IT_EXPERT],
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'Where are system logs?',
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('IT Admins can open System Logs', $response->json('chat.answer'));
    }

    public function test_it_admin_can_manage_chatbot_responses(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($admin)->post(route('chatbot-responses.store'), [
            'category' => 'Reports',
            'question' => 'How do I export reports?',
            'answer' => 'Open Reports, apply filters, then use export.',
            'language' => 'English',
            'keywords' => 'reports, export, csv',
            'synonyms' => 'ulat, print report',
            'visible_roles' => [User::ROLE_MAO, User::ROLE_IT_EXPERT],
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ])->assertRedirect(route('chatbot-responses.index'));

        $this->assertDatabaseHas('chatbot_responses', [
            'category' => 'Reports',
            'question' => 'How do I export reports?',
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ]);

        $record = ChatbotResponse::query()->where('question', 'How do I export reports?')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('chatbot-responses.deactivate', $record))
            ->assertRedirect();

        $this->assertSame(ChatbotResponse::STATUS_INACTIVE, $record->refresh()->status);
    }

    public function test_farmer_cannot_manage_chatbot_responses(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)
            ->get(route('chatbot-responses.index'))
            ->assertForbidden();
    }

    public function test_chatbot_can_show_latest_saved_prediction_result(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        AIChat::query()->create([
            'user_id' => $user->id,
            'question' => 'Predict rice yield for 2 hectares.',
            'answer' => 'Your predicted rice yield is 4.20 tons/hectare.',
            'intent' => 'rice_yield_prediction',
            'source_type' => 'Machine Learning',
            'source_name' => 'iClimate trained model',
            'rice_yield_prediction' => [
                'predicted_yield' => 4.2,
                'estimated_total_production_tons' => 8.4,
                'source_name' => 'iClimate trained model',
            ],
            'explanation' => 'Displayed from a saved prediction.',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Show my latest rice yield prediction result.',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.source_type', 'Prediction Records')
            ->assertJsonPath('chat.rice_yield_prediction.predicted_yield', 4.2);

        $this->assertStringContainsString('Predicted yield: 4.20 tons/hectare', $response->json('chat.answer'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
    }

    public function test_chatbot_prefers_same_language_answer_and_accepts_typo(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        ChatbotResponse::query()->create([
            'category' => 'Dashboard Help',
            'question' => 'How do I use the dashboard?',
            'answer' => 'Use the dashboard from the sidebar.',
            'language' => 'English',
            'keywords' => ['dashboard'],
            'synonyms' => ['dashbord'],
            'visible_roles' => [],
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ]);

        ChatbotResponse::query()->create([
            'category' => 'Dashboard Help',
            'question' => 'Paano gamitin ang dashboard?',
            'answer' => 'Sa dashboard makikita ang mabilis na buod ng tools para sa role mo.',
            'language' => 'Filipino',
            'keywords' => ['dashboard', 'paano gamitin'],
            'synonyms' => ['dashbord', 'saan pipindot'],
            'visible_roles' => [],
            'status' => ChatbotResponse::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'Paano gamitin ang dashbord?',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.language', 'Tagalog')
            ->assertJsonPath('chat.source_type', 'Chatbot Knowledge Base');

        $this->assertStringContainsString('Sa dashboard', $response->json('chat.answer'));
    }

    public function test_general_filipino_planting_question_returns_practical_guide_before_predictions(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();
        $this->seed(ChatbotResponseSeeder::class);

        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
            'question' => 'paano magtanim ng palay',
            'save_conversation' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.language', 'Tagalog')
            ->assertJsonPath('chat.source_type', 'Chatbot Knowledge Base');

        $answer = $response->json('chat.answer');

        $this->assertStringContainsString('Sagot:', $answer);
        $this->assertStringContainsString('Para magtanim ng palay', $answer);
        $this->assertStringContainsString('Mga Hakbang:', $answer);
        $this->assertStringContainsString('iClimate Advisory:', $answer);
        $this->assertStringNotContainsString('Rainfall:', $answer);
        $this->assertStringNotContainsString('Temperature:', $answer);
        $this->assertStringNotContainsString('Temp:', $answer);
        $this->assertStringNotContainsString('tons/hectare', $answer);
        $this->assertStringNotContainsString('57.21', $answer);
        $this->assertSame(1, substr_count($answer, 'Sagot:'));
        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), '127.0.0.1:5001/predict'));
    }

    public function test_seeded_chatbot_topics_answer_common_iclimate_questions(): void
    {
        config(['services.groq.enabled' => false]);
        Http::fake();
        $this->seed(ChatbotResponseSeeder::class);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        AIChat::query()->create([
            'user_id' => $farmer->id,
            'question' => 'Predict rice yield for 2 hectares.',
            'answer' => 'Your predicted rice yield is 4.20 tons/hectare.',
            'intent' => 'rice_yield_prediction',
            'source_type' => 'Machine Learning',
            'source_name' => 'iClimate trained model',
            'rice_yield_prediction' => ['predicted_yield' => 4.2, 'estimated_total_production_tons' => 8.4],
            'explanation' => 'Saved prediction for smoke testing.',
        ]);

        $checks = [
            [$farmer, 'How do I use the dashboard?', 'dashboard'],
            [$farmer, 'Paano gamitin ang dashboard?', 'dashboard'],
            [$farmer, 'Saan makikita ang forecast ng ulan?', 'Weather Prediction'],
            [$farmer, 'Ano ang ibig sabihin ng heat map?', 'heat map'],
            [$farmer, 'Where can I view weather prediction?', 'Weather'],
            [$farmer, 'Show my latest rice yield prediction result.', 'Predicted yield: 4.20 tons/hectare'],
            [$farmer, 'What does the heat map mean?', 'Heat Map'],
            [$farmer, 'Where can I view advisories?', 'Advisories'],
            [$mao, 'What is a farmer profile?', 'farmer profile'],
            [$mao, 'What are climate records?', 'Climate records'],
            [$mao, 'How do reports work?', 'Reports'],
            [$farmer, 'How do I login and navigate?', 'Log in'],
        ];

        foreach ($checks as [$user, $question, $expectedText]) {
            $response = $this->actingAs($user)->postJson(route('ai-chat.message'), [
                'question' => $question,
                'save_conversation' => false,
            ]);

            $response->assertOk();
            $this->assertStringContainsString($expectedText, $response->json('chat.answer'), $question);
        }
    }
}
