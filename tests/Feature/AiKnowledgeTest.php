<?php

namespace Tests\Feature;

use App\Models\AiFaq;
use App\Models\AiKnowledgeTopic;
use App\Models\AiSynonym;
use App\Models\AiTerm;
use App\Models\User;
use App\Services\AI\LexiconService;
use App\Services\Weather\FarmerDashboardWeatherService;
use Database\Seeders\AiVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private const KB = 'Approved Knowledge Base';

    private User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(); // nothing in these tests may reach a real service
        $this->seed(AiVocabularySeeder::class);
        $this->farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
    }

    /** @return array<string, mixed> the "chat" object of the chatbot's JSON reply */
    private function ask(string $question): array
    {
        return $this->actingAs($this->farmer)
            ->postJson(route('ai-chat.message'), ['question' => $question, 'save_conversation' => false])
            ->assertOk()
            ->json('chat');
    }

    private function assertKnowledgeAnswer(string $question, string $expected): void
    {
        $chat = $this->ask($question);

        $this->assertSame(self::KB, $chat['source_type'], "\"{$question}\" was not answered from the knowledge base");
        $this->assertStringContainsString($expected, $chat['answer'], $question);
    }

    // ------------------------------------------------------ vocabulary: English

    public function test_english_farming_terms_are_answered_from_approved_knowledge(): void
    {
        foreach ([
            'What is transplanting?' => 'moving seedlings',
            'What is direct seeding?' => 'straight into the field',
            'What is a hectare?' => '10,000 square meters',
            'What are seedlings?' => 'young rice plants',
            'What is land preparation?' => 'plowing, harrowing and leveling',
            'What is tillering?' => 'new shoots',
            'What is grain filling?' => 'fill with starch',
            'What is drying?' => 'moisture',
            'What is storage?' => 'well-ventilated',
            'What are weeds?' => 'compete with rice',
            'What are pests?' => 'stem borer',
            'What is fertilizer?' => 'nitrogen',
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    public function test_english_weather_terms_are_answered_from_approved_knowledge(): void
    {
        foreach ([
            'What is a typhoon?' => 'tropical storm',
            'What is a flood?' => 'covering land',
            'What is drought?' => 'too little rain',
            'What is a thunderstorm?' => 'thunder and lightning',
            'What is climate?' => 'many years',
            // Words usually asked about as live data need an explicit "what does ... mean".
            'What does humidity mean?' => 'water vapor',
            'What does rainfall mean?' => 'millimeters',
            'What does probability of rain mean?' => 'percentage',
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    // ------------------------------------------------------ vocabulary: Filipino

    public function test_filipino_farming_terms_are_answered_in_filipino(): void
    {
        foreach ([
            'Ano ang punla?' => 'batang palay',
            'Ano ang pag-aani?' => 'pagputol',
            'Ano ang abono?' => 'sustansya',
            'Ano ang sabog-tanim?' => 'direktang pagsabog',
            'Ano ang lipat-tanim?' => 'inihandang palayan',
            'Ano ang ektarya?' => 'metro kuwadrado',
            'Ano ang tag-ulan?' => 'maulang bahagi',
            'Ano ang tag-init?' => 'mas tuyo',
            'Ano ang patubig?' => 'pagbibigay ng tubig',
            'Ano ang damo?' => 'nakikipag-agawan',
            'Ano ang pagpapatuyo?' => 'bagong aning palay',
            'Ano ang bagyo?' => 'malakas na tropical storm',
            'Ano ang baha?' => 'tubig na tumatakip',
            'Ano ang tagtuyot?' => 'kulang ang ulan',
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    public function test_an_english_question_gets_an_english_answer_and_a_filipino_one_gets_filipino(): void
    {
        $english = $this->ask('What is harvest?');
        $filipino = $this->ask('Ano ang pag-aani?');

        $this->assertStringContainsString('cutting and gathering', $english['answer']);
        $this->assertStringNotContainsString('pagputol', $english['answer']);
        $this->assertStringContainsString('pagputol', $filipino['answer']);
        $this->assertStringNotContainsString('cutting and gathering', $filipino['answer']);
    }

    // ------------------------------------------------------ vocabulary: Taglish

    public function test_taglish_questions_are_understood(): void
    {
        foreach ([
            'Ano ang meaning ng irrigation?' => 'patubig',
            'Ano yung fertilizer sa palay?' => 'nitrogen',
            'Pwede bang explain ang tillering?' => 'pagsusuwi',
            'Ano ang ibig sabihin ng harvest?' => 'ani',
            'What does pataba mean?' => 'nitrogen',
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    // ------------------------------------------- synonyms, spellings, canonical concepts

    public function test_synonyms_and_local_words_map_to_the_same_concept(): void
    {
        $lexicon = app(LexiconService::class);

        // ulan / rain / rainfall / precipitation -> rainfall
        foreach (['ulan', 'rain', 'rainfall', 'precipitation', 'umuulan', 'uulan'] as $word) {
            $this->assertArrayHasKey('rainfall', $lexicon->analyze("about {$word}")['concepts'], $word);
        }

        // ani / harvest / pag-aani -> harvest
        foreach (['ani', 'harvest', 'pag-aani', 'pagaani', 'pag aani', 'harvesting'] as $word) {
            $this->assertArrayHasKey('harvest', $lexicon->analyze("about {$word}")['concepts'], $word);
        }

        // tanim / planting / pagtatanim -> planting
        foreach (['tanim', 'planting', 'pagtatanim', 'magtanim'] as $word) {
            $this->assertArrayHasKey('planting', $lexicon->analyze("about {$word}")['concepts'], $word);
        }

        // abono / pataba / fertilizer, punla / seedlings
        foreach (['abono', 'pataba', 'fertilizer', 'fertiliser'] as $word) {
            $this->assertArrayHasKey('fertilizer', $lexicon->analyze($word)['concepts'], $word);
        }
        $this->assertArrayHasKey('seedlings', $lexicon->analyze('punla')['concepts']);
    }

    public function test_the_wet_and_dry_season_words_are_not_mistaken_for_rain_or_heat(): void
    {
        $lexicon = app(LexiconService::class);

        $this->assertSame(['wet_season'], array_keys($lexicon->analyze('tag-ulan')['concepts']));
        $this->assertSame(['wet_season'], array_keys($lexicon->analyze('rainy season')['concepts']));
        $this->assertSame(['dry_season'], array_keys($lexicon->analyze('tag-init')['concepts']));
    }

    public function test_canonical_text_replaces_known_words_with_their_concept(): void
    {
        $analysis = app(LexiconService::class)->analyze('Uulan ba bukas?');

        $this->assertSame('uulan ba bukas', $analysis['normalized']);
        $this->assertSame('rainfall ba bukas', $analysis['canonical']);
    }

    public function test_spelling_variations_and_small_typos_are_tolerated(): void
    {
        foreach ([
            'Ano ang pagaani?' => 'pagputol',                       // hyphen dropped
            'Ano ang pag aani?' => 'pagputol',                      // hyphen as space
            'Ano ang tag ulan?' => 'maulang bahagi',
            'Ano ang tagulan?' => 'maulang bahagi',
            'What does fertiliser mean?' => 'nitrogen',             // alternative spelling
            'What does irigation mean?' => 'water',                 // one letter missing
            'What does fertilizr mean?' => 'nitrogen',              // one letter missing
            'WHAT IS TRANSPLANTING???' => 'moving seedlings',       // case and punctuation
            '  what   is   a   typhoon  ' => 'tropical storm',      // stray spaces
            'what’s a typhoon' => 'tropical storm',                 // curly apostrophe
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    public function test_a_word_the_vocabulary_does_not_contain_matches_nothing(): void
    {
        // "sakit" alone (sick/pain) is not a farming term here, only "sakit ng palay" is.
        $this->assertSame([], app(LexiconService::class)->analyze('sakit')['concepts']);
        $this->assertSame([], app(LexiconService::class)->analyze('xylophagous kalabaw')['concepts']);
    }

    public function test_short_abbreviations_only_count_after_a_number(): void
    {
        $lexicon = app(LexiconService::class);

        $this->assertArrayHasKey('hectare', $lexicon->analyze('2 ha')['concepts']);
        $this->assertArrayNotHasKey('hectare', $lexicon->analyze('ha ano yun')['concepts']);
    }

    // ------------------------------------------------------- unknown and ambiguous

    public function test_an_unknown_term_is_never_answered_from_the_knowledge_base(): void
    {
        $chat = $this->ask('What does xylophagous mean?');

        $this->assertNotSame(self::KB, $chat['source_type']);
        $this->assertNotSame('farming_knowledge', $chat['intent']);
    }

    public function test_a_short_message_naming_several_topics_gets_a_clarifying_question(): void
    {
        $chat = $this->ask('abono peste');

        $this->assertStringContainsString('Ang tinutukoy mo ba ay', $chat['answer']);
        $this->assertStringContainsString('Abono / Pataba', $chat['answer']);
        $this->assertStringContainsString('Peste', $chat['answer']);
        $this->assertStringEndsWith('?', $chat['answer']);
        $this->assertNotSame(self::KB, $chat['source_type']);

        $english = $this->ask('fertilizer pests');
        $this->assertStringStartsWith('Do you mean', $english['answer']);
    }

    public function test_generic_crop_words_do_not_make_a_question_ambiguous(): void
    {
        // "palay" is a context word; the question is clearly about harvest.
        $this->assertKnowledgeAnswer('Ano ang ani ng palay?', 'pagputol');
    }

    // ---------------------------------------------------------------- FAQs

    public function test_how_to_questions_use_approved_faqs(): void
    {
        foreach ([
            'How do I plant rice?' => 'prepare the land',
            'Paano magtanim ng palay?' => 'ihanda ang lupa',
            'When should I harvest rice?' => 'straw-colored',
            'Paano mag-imbak ng palay para hindi masira?' => 'sako',
            'How can I control weeds in my rice field?' => 'water depth',
            'What should I do before a typhoon?' => 'PAGASA',
        ] as $question => $expected) {
            $this->assertKnowledgeAnswer($question, $expected);
        }
    }

    public function test_a_how_to_question_with_no_close_faq_is_not_answered_with_a_definition(): void
    {
        // Mentions fertilizer, but asks for a plan, which no approved FAQ covers:
        // the knowledge base must stay out of it rather than answer a different question.
        $chat = $this->ask('How should I plan fertilizer for rice this season?');

        $this->assertNotSame(self::KB, $chat['source_type']);
    }

    // ------------------------------- weather and yield stay on real data, never invented

    private function weatherService(array $payload): void
    {
        $this->mock(FarmerDashboardWeatherService::class, fn ($mock) => $mock->shouldReceive('current')->andReturn($payload));
    }

    public function test_a_forecast_question_in_filipino_reports_only_the_real_forecast_values(): void
    {
        $this->weatherService([
            'success' => true,
            'source' => 'Test Weather Feed',
            'forecast' => [
                ['day' => 'Today', 'condition' => 'Cloudy', 'rainfall' => 0.5, 'precipitation_probability' => 10],
                ['day' => 'Tomorrow', 'condition' => 'Light rain', 'rainfall' => 3.2, 'precipitation_probability' => 40],
            ],
        ]);

        $chat = $this->ask('Uulan ba bukas?');

        $this->assertNotSame(self::KB, $chat['source_type']);
        $this->assertStringContainsString('3.2', $chat['answer']);
        $this->assertStringContainsString('40', $chat['answer']);
    }

    public function test_a_filipino_current_weather_question_is_routed_to_live_weather(): void
    {
        $this->weatherService([
            'success' => true,
            'source' => 'Test Weather Feed',
            'current' => ['condition' => 'Cloudy', 'temperature' => 29.4, 'humidity' => 81, 'wind_speed' => 12],
        ]);

        $chat = $this->ask('Maulap ba ngayon?');

        $this->assertNotSame(self::KB, $chat['source_type']);
        $this->assertStringContainsString('29.4', $chat['answer']);
    }

    public function test_a_weather_question_with_no_data_is_declined_not_invented(): void
    {
        $this->weatherService(['success' => false, 'forecast' => []]);

        $chat = $this->ask('Uulan ba bukas?');

        $this->assertStringContainsString('Hindi available', $chat['answer']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $chat['answer'], 'no weather figure may appear without data');
        $this->assertNotSame(self::KB, $chat['source_type']);
    }

    public function test_time_words_and_personal_words_keep_a_question_on_live_data(): void
    {
        $lexicon = app(LexiconService::class);
        $knowledge = app(\App\Services\AI\KnowledgeAnswerService::class);

        foreach ([
            'what is the temperature today',
            'what is the rainfall tomorrow',
            'ano ang ulan bukas',
            'what is my yield',
            'ano ang ani ko',
            'what is the yield for 2 hectares',
        ] as $question) {
            $this->assertNull($knowledge->informationalMode($lexicon->analyze($question)), $question);
        }
    }

    public function test_bare_weather_words_prefer_live_data_over_a_definition(): void
    {
        $lexicon = app(LexiconService::class);
        $knowledge = app(\App\Services\AI\KnowledgeAnswerService::class);

        $this->assertNull($knowledge->informationalMode($lexicon->analyze('what is the temperature')));
        $this->assertNull($knowledge->informationalMode($lexicon->analyze('ano ang halumigmig')));
        $this->assertSame('definition', $knowledge->informationalMode($lexicon->analyze('what does humidity mean')));
    }

    public function test_the_approved_content_contains_no_weather_or_yield_figures(): void
    {
        $figure = '/\b\d+(?:[.,]\d+)?\s*(?:mm|°c|km\/h|kph|tons?|t\/ha|%)/i';

        foreach (AiTerm::all() as $term) {
            foreach (['definition_en', 'definition_fil', 'approved_response_en', 'approved_response_fil', 'farming_context'] as $column) {
                $this->assertDoesNotMatchRegularExpression($figure, (string) $term->{$column}, "{$term->term_en}.{$column}");
            }
        }

        foreach (AiFaq::all() as $faq) {
            $this->assertDoesNotMatchRegularExpression($figure, $faq->answer_en.' '.$faq->answer_fil, $faq->question_en);
        }
    }

    // ------------------------------------------------- expandable through the database

    public function test_a_word_added_in_the_database_is_understood_on_the_next_message(): void
    {
        $topic = AiKnowledgeTopic::create(['slug' => 'rat_control', 'category' => 'rice_farming', 'title_en' => 'Rat control', 'title_fil' => 'Pagkontrol ng daga']);
        $term = AiTerm::create([
            'topic_id' => $topic->id, 'category' => 'rice_farming', 'term_en' => 'Rat control',
            'approved_response_en' => 'Keep the field clean and work with your neighbors to control rats together.',
            'source' => 'Test entry',
        ]);

        // Not yet known as a synonym.
        $this->assertSame([], app(LexiconService::class)->analyze('what is kontrol ng peste sa daga')['concepts']['x'] ?? []);

        AiSynonym::create(['term_id' => $term->id, 'synonym' => 'bantay-daga', 'language' => 'fil', 'kind' => 'synonym']);

        $chat = $this->ask('What does bantay-daga mean?');

        $this->assertSame(self::KB, $chat['source_type']);
        $this->assertStringContainsString('control rats together', $chat['answer']);
        $this->assertSame('Test entry', $chat['source_name']);
    }

    public function test_deactivating_a_topic_takes_effect_immediately(): void
    {
        $this->assertKnowledgeAnswer('What is transplanting?', 'moving seedlings');

        AiKnowledgeTopic::where('slug', 'transplanting')->first()->update(['is_active' => false]);

        $this->assertNotSame(self::KB, $this->ask('What is transplanting?')['source_type']);
    }

    public function test_the_vocabulary_is_cached_and_not_reloaded_for_every_message(): void
    {
        $lexicon = app(LexiconService::class);
        $lexicon->analyze('warm up the cache');

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'ai_knowledge_topics') || str_contains($query->sql, 'ai_terms') || str_contains($query->sql, 'ai_synonyms') || str_contains($query->sql, 'ai_faqs')) {
                $queries++;
            }
        });

        foreach (range(1, 5) as $i) {
            $lexicon->analyze('What is harvest?');
        }

        $this->assertSame(0, $queries, 'vocabulary tables must not be queried for each message');
    }

    public function test_seeding_again_never_overwrites_admin_edits(): void
    {
        AiKnowledgeTopic::where('slug', 'harvest')->first()->terms()->first()->update(['approved_response_en' => 'Edited by MAO.']);
        AiKnowledgeTopic::where('slug', 'planting')->first()->update(['is_active' => false]);

        $this->seed(AiVocabularySeeder::class);

        $this->assertSame('Edited by MAO.', AiKnowledgeTopic::where('slug', 'harvest')->first()->terms()->first()->approved_response_en);
        $this->assertFalse((bool) AiKnowledgeTopic::where('slug', 'planting')->value('is_active'));
        $this->assertSame(1, AiKnowledgeTopic::where('slug', 'harvest')->count());
    }

    /** Regression: weatherBarangay()/barangayWeather() were called but never defined, so every weather question failed. */
    public function test_a_weather_question_naming_a_barangay_answers_with_that_barangays_own_reading(): void
    {
        $this->mock(\App\Services\Weather\LianBarangayWeatherService::class, fn ($mock) => $mock->shouldReceive('refreshIfDue')->andReturn(false)->getMock()->shouldReceive('getCurrentWeather')->with('Binubusan')->andReturn([
            'provider' => 'Open-Meteo', 'status' => 'fresh', 'stale' => false, 'condition' => 'Light Rain',
            'temperature_c' => 27.4, 'humidity_percent' => 88, 'rainfall_24h_mm' => 6.2, 'rain_probability_percent' => 70,
            'wind_speed_kmh' => 9.1, 'fetched_at' => now()->toIso8601String(),
        ]));
        $this->weatherService(['success' => true, 'current' => ['condition' => 'Sunny', 'temperature' => 33.0]]);   // Lian-wide: must not be used

        $chat = $this->ask('What is the weather in Binubusan now?');

        $this->assertSame('Live Weather', $chat['source_type']);
        $this->assertStringContainsString('Binubusan', $chat['answer']);
        $this->assertStringContainsString('27.4°C', $chat['answer']);
        $this->assertStringNotContainsString('33', $chat['answer']);
    }

    /** Plain weather and "is it safe to work today" questions are about today's real weather, not a model or a typhoon guide. */
    public function test_everyday_weather_and_field_safety_questions_route_to_current_weather(): void
    {
        $classifier = app(\App\Services\AI\IntentClassifierService::class);
        $routes = [
            'What is the weather in Binubusan?' => 'current_weather',
            'Ano ang panahon sa Binubusan?' => 'current_weather',
            'What is the weather in my barangay?' => 'current_weather',
            'Is it safe to work on my farm today?' => 'current_weather',
            'Ligtas bang magtrabaho sa bukid ngayon?' => 'current_weather',
            'What is the weather tomorrow?' => 'weather_forecast',   // a later day stays a forecast
            'Kailan dapat magtanim?' => null,                        // "ani" inside "magtanim" is not a harvest question
        ];

        foreach ($routes as $question => $expected) {
            $intent = $classifier->classifyLocal($question)['intent'];
            $expected === null
                ? $this->assertNotSame('rice_yield_prediction', $intent, $question)
                : $this->assertSame($expected, $intent, $question);
        }
    }

    public function test_my_barangay_and_my_farm_use_the_farmers_own_registered_barangay(): void
    {
        \App\Models\FarmerProfile::factory()->create(['user_id' => $this->farmer->id, 'barangay' => 'Binubusan']);
        $this->mock(\App\Services\Weather\LianBarangayWeatherService::class, fn ($mock) => $mock->shouldReceive('refreshIfDue')->andReturn(false)->getMock()->shouldReceive('getCurrentWeather')->with('Binubusan')->andReturn([
            'provider' => 'Open-Meteo', 'status' => 'fresh', 'stale' => false, 'condition' => 'Partly Cloudy',
            'temperature_c' => 26.1, 'rainfall_24h_mm' => 2.0, 'rain_probability_percent' => 40, 'fetched_at' => now()->toIso8601String(),
        ]));
        $this->weatherService(['success' => true, 'current' => ['condition' => 'Sunny', 'temperature' => 33.0]]);

        foreach (['Is it safe to work on my farm today?', 'What is the weather in my barangay?'] as $question) {
            $chat = $this->ask($question);

            $this->assertSame('Live Weather', $chat['source_type'], $question);
            $this->assertStringContainsString('Binubusan', $chat['answer'], $question);
            $this->assertStringContainsString('26.1°C', $chat['answer'], $question);
            $this->assertStringNotContainsStringIgnoringCase('typhoon', $chat['answer'], $question);
        }
    }

    public function test_an_unavailable_barangay_reading_is_reported_not_replaced_by_lian_figures(): void
    {
        $this->mock(\App\Services\Weather\LianBarangayWeatherService::class, function ($mock) {
            $mock->shouldReceive('getCurrentWeather')->andReturn(null);                       // no reading at all: fetched now
            $mock->shouldReceive('refreshIfDue')->andReturn(false);
            $mock->shouldReceive('latestForBarangay')->andReturn(['provider' => 'Open-Meteo', 'status' => 'unavailable', 'stale' => true, 'temperature_c' => null]);
        });
        $this->weatherService(['success' => true, 'current' => ['condition' => 'Sunny', 'temperature' => 33.0]]);

        $chat = $this->ask('What is the weather in Binubusan now?');

        $this->assertStringContainsString('Binubusan', $chat['answer']);
        $this->assertStringContainsString('temporarily unavailable', $chat['answer']);
        $this->assertDoesNotMatchRegularExpression('/\d/', $chat['answer'], 'no weather figure may appear without data');
    }
}
