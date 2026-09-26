<?php

namespace Tests\Feature;

use App\Http\Controllers\AIChatController;
use App\Models\AIChat;
use App\Models\AiBlockedPattern;
use App\Models\AiBlockedTerm;
use App\Models\AiKnowledgeTopic;
use App\Models\AiModerationEvent;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\AI\GroqChatService;
use App\Services\AI\IntentClassifierService;
use App\Services\AI\ModerationService;
use Database\Seeders\AiModerationSeeder;
use Database\Seeders\AiVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiLanguageFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(); // a real service call would be a test failure
        $this->seed(AiModerationSeeder::class);
        $this->farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
    }

    private function moderation(): ModerationService
    {
        return app(ModerationService::class);
    }

    private function isBlocked(string $message): bool
    {
        return $this->moderation()->inspect($message)['blocked'];
    }

    /** @return array<string, mixed> */
    private function ask(string $question, bool $save = true): array
    {
        return $this->actingAs($this->farmer)
            ->postJson(route('ai-chat.message'), ['question' => $question, 'save_conversation' => $save])
            ->assertOk()
            ->json('chat');
    }

    // ---------------------------------------------------------- what is blocked

    public function test_english_profanity_and_abuse_is_blocked(): void
    {
        foreach ([
            'fuck you', 'this is bullshit', 'you are an idiot', 'what a stupid asshole', 'shut up',
            'go to hell', 'you piece of shit', 'bitch', 'son of a bitch', 'suck my dick', 'show me porn',
        ] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_filipino_profanity_and_abuse_is_blocked(): void
    {
        foreach ([
            'putangina mo', 'putang ina', 'tang ina mo', 'tangina', 'gago', 'ulol ka', 'bobo',
            'tanga ka talaga', 'tarantado', 'punyeta', 'pakyu', 'kupal ka', 'bwisit', 'siraulo',
            'mamatay ka', 'kantot', 'ano ang abono, gago',
        ] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_intentionally_misspelled_profanity_is_blocked(): void
    {
        foreach (['fuk you', 'fck this', 'fuq', 'phuck', 'shet', 'pakshet', 'tnginamo', 'potangina', 'putragis', 'puneta', 'pak yu'] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_repeated_character_variants_are_blocked(): void
    {
        foreach (['fuuuuck', 'fuuuck you', 'shiiiit', 'gaaaago', 'boboooo', 'ulooool', 'assssshole', 'putaaaa', 'biiiitch'] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_leetspeak_and_symbol_substitutions_are_blocked(): void
    {
        foreach (['sh1t', 'b1tch', 'g@go', 'g4go', 'f*ck', 'f.u.c.k', 'f u c k', 'p u t a', 't4ngina', 'put4ng1na', '@sshole', '$hit', 'sh!t', 'b0bo', 'ul0l'] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_context_patterns_catch_ordinary_words_used_as_abuse(): void
    {
        foreach (['hayop ka', 'lintik ka', 'peste ka', 'animal ka', 'ang hayop mo', 'salot kayo', 'patayin kita'] as $message) {
            $this->assertTrue($this->isBlocked($message), $message);
        }
    }

    public function test_abuse_hidden_inside_a_farming_question_is_still_blocked(): void
    {
        $this->assertTrue($this->isBlocked('Kailan mag-aani ng palay, gago?'));
        $this->assertTrue($this->isBlocked('What is the rainfall tomorrow you idiot'));
        $this->assertTrue($this->isBlocked("ANO ANG ABONO?? PUTANGINA"));
    }

    // ------------------------------------------------- what must NOT be blocked

    public function test_harmless_farming_and_weather_words_are_not_blocked(): void
    {
        foreach ([
            // Filipino farming words that resemble slang or contain a blocked sequence
            'Paano gamutin ang peste sa palay?', 'Ano ang damo sa palayan?', 'Ano ang hayop na sumisira sa palay?',
            'May lintik ba bukas?', 'Ang lintik at kidlat ay delikado sa bukid', 'Bibili ako ng puto at bibingka',
            'leche flan for the fiesta', 'Kailan ang tag-ulan?', 'Ano ang pataba sa lupa?', 'Bulok na ang palay',
            // English words that merely contain a blocked word
            'class assessment of the grass', 'the bass in the fishpond', 'Scunthorpe', 'a cocktail of fertilizer',
            'assistant', 'passage', 'classification of typhoon', 'compass', 'analysis of rice yield',
            'I need a hoe for weeding', 'the rooster and the hen', 'Where do I put a sack of palay?',
            'shitake mushrooms', 'therapist', 'peacock', 'Dickens', 'title of the land', 'Bangkok',
            // ordinary weather questions
            'Will it rain tomorrow?', 'Uulan ba bukas?', 'What is the probability of rain?', 'Gaano kalakas ang hangin?',
            'How much fertilizer per hectare?', '5 mm of rain and 30 C', 'sunny 1 3 5 7',
        ] as $message) {
            $this->assertFalse($this->isBlocked($message), $message);
        }
    }

    public function test_an_exception_phrase_keeps_a_word_usable_only_in_that_context(): void
    {
        $this->assertFalse($this->isBlocked('gusto ko ng leche flan'));
        $this->assertTrue($this->isBlocked('leche ka'));
        $this->assertTrue($this->isBlocked('leche'));
    }

    // -------------------------------------------- moderation behaviour in the chatbot

    public function test_a_blocked_message_gets_a_short_neutral_reply_in_the_persons_language(): void
    {
        $english = $this->ask('you are an idiot, what is the weather');
        $filipino = $this->ask('putangina mo');
        $filipinoSentence = $this->ask('Ano ang abono, gago?');

        $this->assertSame(ModerationService::MESSAGE_EN, $english['answer']);
        $this->assertSame(ModerationService::MESSAGE_FIL, $filipino['answer']);
        $this->assertSame(ModerationService::MESSAGE_FIL, $filipinoSentence['answer']);
        $this->assertSame('Pakigamit po ang maayos na pananalita upang matulungan kita tungkol sa pagsasaka ng palay at panahon.', ModerationService::MESSAGE_FIL);
        $this->assertSame('Please use appropriate language so I can help with rice farming and weather questions.', ModerationService::MESSAGE_EN);
    }

    public function test_the_reply_never_repeats_or_argues_with_the_abusive_words(): void
    {
        foreach (['you are an idiot', 'putangina mo', 'fuuuck this', 'gago ka'] as $message) {
            $chat = $this->ask($message);
            $json = json_encode($chat);

            foreach (['idiot', 'putangina', 'fuuuck', 'gago', 'fuck'] as $word) {
                $this->assertStringNotContainsString($word, strtolower($json), "{$message} echoed {$word}");
            }
            $this->assertSame('moderation_blocked', $chat['intent'] ?? 'moderation_blocked');
        }
    }

    public function test_blocked_messages_never_reach_the_normal_ai_pipeline(): void
    {
        $this->mock(IntentClassifierService::class, fn ($mock) => $mock->shouldNotReceive('classify'));
        $this->mock(GroqChatService::class, function ($mock): void {
            $mock->shouldNotReceive('classifyIntent');
            $mock->shouldNotReceive('groundedResponse');
        });

        foreach (['fuck you', 'putangina mo', 'sh1t', 'gaaaago'] as $message) {
            $this->ask($message);
        }

        Http::assertNothingSent();
    }

    public function test_a_blocked_message_is_not_stored_or_returned(): void
    {
        $chat = $this->ask('putangina mo gago');

        $this->assertSame(AIChatController::REDACTED_QUESTION, $chat['question']);
        $this->assertSame(1, AIChat::count());

        $stored = AIChat::first();
        $this->assertSame(AIChatController::REDACTED_QUESTION, $stored->question);
        $this->assertStringNotContainsString('putangina', strtolower(json_encode($stored->toArray())));

        // Not saved when the person chose not to save, and still not returned.
        $unsaved = $this->ask('fuck you', save: false);
        $this->assertSame(AIChatController::REDACTED_QUESTION, $unsaved['question']);
        $this->assertSame(1, AIChat::count());
    }

    public function test_a_blocked_message_does_not_leak_into_later_conversation_memory(): void
    {
        $this->ask('fuck you');
        $chat = $this->ask('Ano ang punla?');

        $this->assertStringNotContainsString('fuck', strtolower(json_encode($chat)));
    }

    public function test_moderation_logs_hold_only_safe_metadata(): void
    {
        $logs = [];
        Log::listen(function ($event) use (&$logs): void {
            $logs[] = $event->message.' '.json_encode($event->context);
        });

        $this->ask('putangina mo, you idiot');
        $this->ask('f*ck this sh1t');

        $events = AiModerationEvent::all();
        $this->assertCount(2, $events);

        foreach ($events as $event) {
            $this->assertSame($this->farmer->id, $event->user_id);
            $this->assertTrue($event->blocked);
            $this->assertContains($event->category, ['profanity', 'insult', 'sexual', 'slur', 'abusive']);
            $this->assertContains($event->reason_code, ['blocked_term', 'blocked_pattern']);
            $this->assertNotEmpty($event->language);
            $this->assertNotNull($event->created_at);
        }

        // The table has no column that could hold the message, and nothing anywhere contains it.
        $this->assertSame(
            ['id', 'user_id', 'language', 'category', 'reason_code', 'blocked', 'created_at'],
            \Illuminate\Support\Facades\Schema::getColumnListing('ai_moderation_events'),
        );

        $everything = strtolower(json_encode([
            AiModerationEvent::all()->toArray(),
            SystemLog::all()->toArray(),
            $logs,
        ]));
        foreach (['putangina', 'idiot', 'f*ck', 'sh1t', 'shit'] as $word) {
            $this->assertStringNotContainsString($word, $everything, $word);
        }
    }

    public function test_harmless_messages_are_not_recorded_as_moderation_events(): void
    {
        $this->seed(AiVocabularySeeder::class);

        $this->ask('Ano ang punla?');
        $this->ask('What is a typhoon?');

        $this->assertSame(0, AiModerationEvent::count());
    }

    public function test_normal_chat_still_works_after_the_filter(): void
    {
        $this->seed(AiVocabularySeeder::class);

        $chat = $this->ask('Ano ang punla?');

        $this->assertSame('Approved Knowledge Base', $chat['source_type']);
    }

    // ------------------------------------------------------ performance and cache

    private function moderationQueries(callable $work): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count): void {
            if (str_contains($query->sql, 'ai_blocked_terms') || str_contains($query->sql, 'ai_blocked_patterns')) {
                $count++;
            }
        });
        $work();

        return $count;
    }

    public function test_the_dictionary_is_cached_and_not_queried_for_every_message(): void
    {
        $this->moderation()->inspect('warm up'); // loads and caches the dictionary

        $queries = $this->moderationQueries(function (): void {
            foreach (range(1, 10) as $i) {
                $this->moderation()->inspect("harmless question number {$i} about rice");
            }
        });

        $this->assertSame(0, $queries);
    }

    public function test_the_dictionary_is_loaded_once_when_the_cache_is_cold(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        $queries = $this->moderationQueries(function (): void {
            foreach (range(1, 5) as $i) {
                $this->moderation()->inspect('is this loaded once?');
            }
        });

        $this->assertSame(2, $queries, 'one query for terms and one for patterns, once');
    }

    public function test_an_admin_edit_is_picked_up_by_the_next_message(): void
    {
        $this->assertFalse($this->isBlocked('zorblax'));

        $term = AiBlockedTerm::create(['term' => 'Zorblax', 'language' => 'en', 'category' => 'abusive']);
        $this->assertTrue($this->isBlocked('zorblax'));
        $this->assertTrue($this->isBlocked('z0rbl4x'));

        $term->update(['is_active' => false]);
        $this->assertFalse($this->isBlocked('zorblax'));

        $term->update(['is_active' => true, 'exceptions' => ['zorblax garden']]);
        $this->assertFalse($this->isBlocked('the zorblax garden'));
        $this->assertTrue($this->isBlocked('zorblax'));

        AiBlockedPattern::create(['pattern' => '\\bquux\\s+off\\b', 'language' => 'en', 'category' => 'abusive']);
        $this->assertTrue($this->isBlocked('quux off'));
    }

    // ------------------------------------------------------------------ admin

    private function admin(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_farmers_cannot_see_or_change_any_chatbot_data(): void
    {
        $term = AiBlockedTerm::first();
        $before = AiBlockedTerm::count();

        foreach (['topics', 'terms', 'synonyms', 'faqs', 'blocked-terms', 'blocked-patterns'] as $resource) {
            $this->actingAs($this->farmer)->get(route('ai-admin.index', $resource))->assertForbidden();
            $this->actingAs($this->farmer)->post(route('ai-admin.store', $resource), ['term' => 'newword'])->assertForbidden();
        }

        $this->actingAs($this->farmer)->put(route('ai-admin.update', ['blocked-terms', $term->id]), ['term' => 'changed'])->assertForbidden();
        $this->actingAs($this->farmer)->post(route('ai-admin.toggle', ['blocked-terms', $term->id]))->assertForbidden();

        $this->assertSame($before, AiBlockedTerm::count());
        $this->assertTrue($term->refresh()->is_active);
        $this->assertNotSame('changed', $term->term);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('ai-admin.index', 'blocked-terms'))->assertRedirect(route('login'));
    }

    public function test_the_dictionary_is_never_shown_to_a_farmer(): void
    {
        $chat = $this->ask('fuck');
        $this->actingAs($this->farmer)->get(route('ai-chat.index'))->assertRedirect();

        $this->assertStringNotContainsString('putangina', strtolower(json_encode($chat)));
        $this->assertStringNotContainsString('tarantado', strtolower(json_encode($chat)));
    }

    public function test_it_experts_and_mao_can_manage_vocabulary_and_the_filter(): void
    {
        foreach ([User::ROLE_IT_EXPERT, User::ROLE_MAO] as $role) {
            $admin = $this->admin($role);

            foreach (['topics', 'terms', 'synonyms', 'faqs', 'blocked-terms', 'blocked-patterns'] as $resource) {
                $this->actingAs($admin)->get(route('ai-admin.index', $resource))->assertOk();
            }

            $this->actingAs($admin)->get(route('ai-admin.index', 'nonsense'))->assertNotFound();
        }
    }

    public function test_admins_can_add_edit_and_deactivate_entries(): void
    {
        $admin = $this->admin(User::ROLE_MAO);

        // topic -> term -> synonym -> faq
        $this->actingAs($admin)->post(route('ai-admin.store', 'topics'), [
            'slug' => 'rat_control', 'category' => 'rice_farming', 'title_en' => 'Rat control', 'title_fil' => 'Pagkontrol ng daga', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $topic = AiKnowledgeTopic::where('slug', 'rat_control')->firstOrFail();

        $this->actingAs($admin)->post(route('ai-admin.store', 'terms'), [
            'topic_id' => $topic->id, 'category' => 'rice_farming', 'term_en' => 'Rat control',
            'alt_spellings' => "rat baiting\ndaga control", 'approved_response_en' => 'Work together with neighbors.', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $term = $topic->terms()->firstOrFail();
        $this->assertSame(['rat baiting', 'daga control'], $term->alt_spellings);

        $this->actingAs($admin)->post(route('ai-admin.store', 'synonyms'), [
            'term_id' => $term->id, 'synonym' => 'pagpatay ng daga', 'language' => 'fil', 'kind' => 'synonym', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_synonyms', ['term_id' => $term->id, 'synonym' => 'pagpatay ng daga']);

        $this->actingAs($admin)->post(route('ai-admin.store', 'faqs'), [
            'topic_id' => $topic->id, 'category' => 'rice_farming', 'question_en' => 'How do I stop rats?', 'answer_en' => 'Keep fields clean.', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_faqs', ['question_en' => 'How do I stop rats?']);

        // edit, then deactivate
        $this->actingAs($admin)->put(route('ai-admin.update', ['terms', $term->id]), [
            'topic_id' => $topic->id, 'category' => 'rice_farming', 'term_en' => 'Rat control (edited)', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Rat control (edited)', $term->refresh()->term_en);

        $this->actingAs($admin)->post(route('ai-admin.toggle', ['terms', $term->id]))->assertRedirect();
        $this->assertFalse($term->refresh()->is_active);
    }

    public function test_admins_can_manage_blocked_terms_and_patterns_with_validation(): void
    {
        $admin = $this->admin(User::ROLE_IT_EXPERT);

        $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-terms'), [
            'term' => 'Zorblax!!', 'language' => 'en', 'category' => 'abusive', 'exceptions' => "zorblax garden", 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $term = AiBlockedTerm::where('normalized_term', 'zorblax')->firstOrFail();
        $this->assertSame(['zorblax garden'], $term->exceptions);
        $this->assertTrue($this->isBlocked('zorblax'));

        // the same word again is refused
        $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-terms'), ['term' => 'ZORBLAX', 'language' => 'en', 'category' => 'abusive'])->assertSessionHasErrors('form');
        $this->assertSame(1, AiBlockedTerm::where('normalized_term', 'zorblax')->count());

        // deactivate takes effect at once
        $this->actingAs($admin)->post(route('ai-admin.toggle', ['blocked-terms', $term->id]));
        $this->assertFalse($this->isBlocked('zorblax'));

        // patterns: a valid one is saved; a broken or dangerous one is refused
        $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-patterns'), ['pattern' => '\\bblorp\\s+ka\\b', 'language' => 'fil', 'category' => 'abusive', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($this->isBlocked('blorp ka'));

        foreach (['(unclosed', '(a+)+$', '(.*)*x', 'has~tilde', str_repeat('a', 201)] as $bad) {
            $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-patterns'), ['pattern' => $bad, 'language' => 'any', 'category' => 'abusive'])->assertSessionHasErrors();
        }
        $this->assertSame(0, AiBlockedPattern::where('pattern', '(a+)+$')->count());
    }

    public function test_admin_changes_are_audited_without_recording_blocked_words(): void
    {
        $admin = $this->admin(User::ROLE_IT_EXPERT);

        $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-terms'), ['term' => 'Zorblax', 'language' => 'en', 'category' => 'abusive', 'is_active' => 1]);

        $log = SystemLog::where('action', 'ai_vocabulary.created')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringNotContainsString('zorblax', strtolower($log->details));
        $this->assertSame('blocked-terms', json_decode($log->details, true)['resource']);
    }

    public function test_a_blocked_term_the_matcher_cannot_use_is_rejected(): void
    {
        $admin = $this->admin(User::ROLE_IT_EXPERT);

        $this->actingAs($admin)->post(route('ai-admin.store', 'blocked-terms'), ['term' => '!!!', 'language' => 'any', 'category' => 'abusive'])->assertSessionHasErrors('form');
    }
}
