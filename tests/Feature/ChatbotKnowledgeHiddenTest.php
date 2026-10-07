<?php

namespace Tests\Feature;

use App\Models\AiBlockedTerm;
use App\Models\AiFaq;
use App\Models\AiKnowledgeTopic;
use App\Models\AiSynonym;
use App\Models\AiTerm;
use App\Models\User;
use App\Services\AI\ModerationService;
use Database\Seeders\AiModerationSeeder;
use Database\Seeders\AiVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Chatbot Knowledge tab is hidden from the interface (CHATBOT_KNOWLEDGE_UI=false, the default): no menu lists it
 * and its pages send every role to their dashboard. Only the interface is switched off — the Climora chatbot still
 * uses all the knowledge (answers, synonyms, Filipino terms, blocked words). CHATBOT_KNOWLEDGE_UI=true brings the tab
 * back for IT.
 */
class ChatbotKnowledgeHiddenTest extends TestCase
{
    use RefreshDatabase;

    private const KB = 'Approved Knowledge Base';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // nothing here may reach a real service
        $this->seed(AiVocabularySeeder::class);
        $this->seed(AiModerationSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    /** @return array<string, mixed> the "chat" object of the chatbot's JSON reply */
    private function ask(string $question): array
    {
        return $this->actingAs($this->user(User::ROLE_FARMER))
            ->postJson(route('ai-chat.message'), ['question' => $question, 'save_conversation' => false])
            ->assertOk()
            ->json('chat');
    }

    public function test_the_flag_is_off_by_default(): void
    {
        $this->assertFalse(config('iclimate.chatbot_knowledge_ui'));
        $this->assertStringContainsString("env('CHATBOT_KNOWLEDGE_UI', false)", (string) file_get_contents(config_path('iclimate.php')));
    }

    public function test_the_tab_is_hidden_for_every_role(): void
    {
        $pages = [
            User::ROLE_FARMER => ['farmer.dashboard', 'planting-advisories.index', 'messages.index'],
            User::ROLE_MAO => ['mao.dashboard', 'planting-advisories.index', 'messages.index', 'community-feed.index', 'reports.index'],
            User::ROLE_IT_EXPERT => ['admin.dashboard', 'system-health.index', 'users.index', 'messages.index', 'system-logs.index'],
        ];

        foreach ($pages as $role => $routes) {
            $user = $this->user($role);
            foreach ($routes as $route) {
                $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
                // No menu item, button, card or link to it anywhere on the page.
                $this->assertStringNotContainsString('Chatbot Knowledge', $html, "{$role} {$route}");
                $this->assertStringNotContainsString('/ai-admin', $html, "{$role} {$route}");
            }
        }
    }

    public function test_the_pages_send_every_role_to_their_dashboard_while_the_flag_is_off(): void
    {
        $term = AiBlockedTerm::query()->firstOrFail();
        $before = [AiKnowledgeTopic::count(), AiTerm::count(), AiSynonym::count(), AiFaq::count(), AiBlockedTerm::count()];

        foreach ([User::ROLE_FARMER => 'farmer.dashboard', User::ROLE_MAO => 'mao.dashboard', User::ROLE_IT_EXPERT => 'admin.dashboard'] as $role => $dashboard) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('ai-admin.home'))->assertRedirect(route('dashboard'));
            foreach (['topics', 'terms', 'synonyms', 'faqs', 'blocked-terms', 'blocked-patterns'] as $resource) {
                $this->actingAs($user)->get(route('ai-admin.index', $resource))->assertRedirect(route('dashboard'));
            }
            // Writes are switched off with the pages: nothing is added, changed or toggled.
            $this->actingAs($user)->post(route('ai-admin.store', 'blocked-terms'), ['term' => 'newword', 'language' => 'en', 'category' => 'abusive'])->assertRedirect(route('dashboard'));
            $this->actingAs($user)->put(route('ai-admin.update', ['blocked-terms', $term->id]), ['term' => 'changed', 'language' => 'en', 'category' => 'abusive'])->assertRedirect(route('dashboard'));
            $this->actingAs($user)->post(route('ai-admin.toggle', ['blocked-terms', $term->id]))->assertRedirect(route('dashboard'));

            // "Their dashboard": the role's own.
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($dashboard));
        }

        $this->assertSame($before, [AiKnowledgeTopic::count(), AiTerm::count(), AiSynonym::count(), AiFaq::count(), AiBlockedTerm::count()]);
        $this->assertSame([$term->term, true], [$term->fresh()->term, (bool) $term->fresh()->is_active]);

        auth()->logout();
        $this->get(route('ai-admin.index', 'topics'))->assertRedirect(route('login'));
    }

    public function test_the_chatbot_still_answers_from_the_knowledge_while_the_tab_is_hidden(): void
    {
        $this->assertFalse(config('iclimate.chatbot_knowledge_ui'));

        // English terms and Filipino terms, each in its own language.
        foreach (['What is transplanting?' => 'moving seedlings', 'What is a hectare?' => '10,000 square meters', 'Ano ang punla?' => 'batang palay', 'Ano ang abono?' => 'sustansya'] as $question => $expected) {
            $chat = $this->ask($question);
            $this->assertSame(self::KB, $chat['source_type'], $question);
            $this->assertStringContainsString($expected, $chat['answer'], $question);
        }

        // A synonym saved in the database is understood (the knowledge is read from the tables, not from the pages).
        $topic = AiKnowledgeTopic::create(['slug' => 'rat_control', 'category' => 'rice_farming', 'title_en' => 'Rat control', 'title_fil' => 'Pagkontrol ng daga']);
        $term = AiTerm::create(['topic_id' => $topic->id, 'category' => 'rice_farming', 'term_en' => 'Rat control',
            'approved_response_en' => 'Keep the field clean and work with your neighbors to control rats together.', 'source' => 'Test entry']);
        AiSynonym::create(['term_id' => $term->id, 'synonym' => 'bantay-daga', 'language' => 'fil', 'kind' => 'synonym']);
        $chat = $this->ask('What does bantay-daga mean?');
        $this->assertSame(self::KB, $chat['source_type']);
        $this->assertStringContainsString('control rats together', $chat['answer']);
    }

    public function test_blocked_words_are_still_blocked_while_the_tab_is_hidden(): void
    {
        $moderation = app(ModerationService::class);

        foreach (['you are an idiot', 'this is bullshit'] as $message) {
            $this->assertTrue($moderation->inspect($message)['blocked'], $message);
        }
        $this->assertFalse($moderation->inspect('When should I plant rice?')['blocked']);

        // A blocked word added to the table (for example by a seeder) takes effect on the next message.
        AiBlockedTerm::create(['term' => 'Zorblax', 'language' => 'en', 'category' => 'abusive']);
        $this->assertTrue($moderation->inspect('zorblax')['blocked']);
        $this->assertNotSame(self::KB, $this->ask('zorblax')['source_type'] ?? null);
    }

    public function test_setting_the_flag_to_true_brings_the_tab_back_for_it_only(): void
    {
        config(['iclimate.chatbot_knowledge_ui' => true]);
        $it = $this->user(User::ROLE_IT_EXPERT);

        $html = $this->actingAs($it)->get(route('admin.dashboard'))->assertOk()->getContent();
        preg_match_all('#<span class="sidebar-label">([^<]+)</span>#', $html, $labels);
        $this->assertContains('Chatbot Knowledge', $labels[1]);
        $this->assertStringContainsString('href="'.route('ai-admin.home').'"', $html);

        $this->actingAs($it)->get(route('ai-admin.home'))->assertRedirect(route('ai-admin.index', ['resource' => 'topics']));
        foreach (['topics', 'terms', 'synonyms', 'faqs', 'blocked-terms', 'blocked-patterns'] as $resource) {
            $this->actingAs($it)->get(route('ai-admin.index', $resource))->assertOk();
        }
        $this->actingAs($it)->post(route('ai-admin.store', 'blocked-terms'), ['term' => 'Quuxword', 'language' => 'en', 'category' => 'abusive'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_blocked_terms', ['term' => 'Quuxword']);

        // Still not for MAO or farmers, in the menu or by URL.
        foreach ([User::ROLE_MAO => 'mao.dashboard', User::ROLE_FARMER => 'farmer.dashboard'] as $role => $dashboard) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route($dashboard))->assertOk()->assertDontSee('Chatbot Knowledge');
            $this->actingAs($user)->get(route('ai-admin.index', 'topics'))->assertForbidden();
        }
    }
}
