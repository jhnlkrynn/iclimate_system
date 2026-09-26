<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\User;
use App\Services\AI\AiTimings;
use App\Services\AI\GroqChatService;
use App\Services\Weather\FarmerDashboardWeatherService;
use Database\Seeders\AiModerationSeeder;
use Database\Seeders\AiVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The chatbot answers from iClimate's own data first, keeps every network wait
 * short, and stops asking Groq once it has proved slow.
 */
class AiResponseSpeedTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'gsk_super_secret_token_123';

    private User $farmer;

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.groq.enabled' => true,
            'services.groq.key' => self::SECRET,
            'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
            'services.weather.cache_minutes' => 1,
            'ai.response_budget_seconds' => 10,
        ]);

        $this->farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        Log::listen(function ($event): void {
            $this->logs[] = ['message' => $event->message, 'context' => $event->context];
        });
    }

    /** @return array<string, mixed> */
    private function ask(string $question): array
    {
        return $this->actingAs($this->farmer)
            ->postJson(route('ai-chat.message'), ['question' => $question, 'save_conversation' => false])
            ->assertOk()
            ->json('chat');
    }

    private function groqRequests(): int
    {
        return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'groq'))->count();
    }

    private function weatherRequests(): int
    {
        return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'open-meteo'))->count();
    }

    /** @return array<string, mixed> the context of the last timing log line */
    private function timing(): array
    {
        $lines = array_values(array_filter($this->logs, fn (array $log): bool => $log['message'] === 'AI response timing'));
        $this->assertNotEmpty($lines, 'a timing line must be logged for every message');

        return end($lines)['context'];
    }

    private function openMeteoPayload(): array
    {
        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => now('Asia/Manila')->format('Y-m-d\TH:i'), 'temperature_2m' => 25.5, 'relative_humidity_2m' => 95,
                'apparent_temperature' => 27.1, 'precipitation' => 0.1, 'rain' => 0.1, 'showers' => 0, 'weather_code' => 61,
                'cloud_cover' => 80, 'pressure_msl' => 1008, 'surface_pressure' => 1005, 'wind_speed_10m' => 8.4,
                'wind_direction_10m' => 180, 'wind_gusts_10m' => 15.2, 'is_day' => 1,
            ],
            'daily' => [
                'time' => [now('Asia/Manila')->toDateString(), now('Asia/Manila')->addDay()->toDateString()],
                'weather_code' => [61, 2], 'temperature_2m_max' => [30.2, 31.1], 'temperature_2m_min' => [24.1, 24.4],
                'apparent_temperature_max' => [33.0, 34.0], 'apparent_temperature_min' => [26.0, 26.4],
                'sunrise' => ['2026-08-09T05:42', '2026-08-10T05:42'], 'sunset' => ['2026-08-09T18:20', '2026-08-10T18:19'],
                'precipitation_sum' => [6.2, 2.0], 'rain_sum' => [5.7, 1.4], 'showers_sum' => [0.5, 0.2],
                'precipitation_probability_max' => [80, 45], 'wind_speed_10m_max' => [12.5, 10.0], 'wind_gusts_10m_max' => [20.0, 18.0],
            ],
            'hourly' => [
                'time' => ['2026-08-09T23:00'], 'temperature_2m' => [25.5], 'relative_humidity_2m' => [95],
                'precipitation_probability' => [80], 'precipitation' => [0.1], 'rain' => [0.1], 'weather_code' => [61],
            ],
        ];
    }

    // ------------------------------------------------ system data before Groq

    public function test_weather_questions_bypass_groq_when_direct_data_is_available(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"intent":"unknown","confidence":0.9}']]]]),
        ]);

        foreach (['Will it rain this week?', 'Uulan ba bukas?', 'What is the weather today?'] as $question) {
            $chat = $this->ask($question);

            $this->assertNotEmpty($chat['answer'], $question);
            $this->assertMatchesRegularExpression('/\d/', $chat['answer'], "{$question}: the answer must carry real weather values");
        }

        $this->assertSame(0, $this->groqRequests(), 'Groq must not be called when iClimate has the data');
        $this->assertSame('system', $this->timing()['path']);
        $this->assertSame(0, $this->timing()['groq_calls']);
    }

    public function test_knowledge_answers_bypass_groq_when_matched(): void
    {
        $this->seed(AiVocabularySeeder::class);
        Http::fake(['api.groq.com/*' => Http::response([], 500)]);

        foreach (['What is a typhoon?', 'Ano ang punla?', 'Paano magtanim ng palay?'] as $question) {
            $this->assertSame('Approved Knowledge Base', $this->ask($question)['source_type'], $question);
        }

        $this->assertSame(0, $this->groqRequests());
        $this->assertSame('knowledge', $this->timing()['path']);
    }

    public function test_moderation_blocks_before_anything_is_fetched_or_asked(): void
    {
        $this->seed(AiModerationSeeder::class);
        Http::fake();

        $this->ask('putangina mo');

        Http::assertNothingSent();
        $this->assertSame('moderation', $this->timing()['path']);
    }

    public function test_groq_is_only_asked_when_nothing_else_can_place_the_message(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hello there!']]]])]);

        $this->ask('Tell me something interesting about the moon');

        $this->assertGreaterThan(0, $this->groqRequests(), 'an unplaced message is the case Groq is for');
        $this->assertGreaterThan(0, $this->timing()['groq_calls']);
        $this->assertSame('groq', $this->timing()['path']);
    }

    // ---------------------------------------------- Groq slow or down: no hanging

    public function test_a_groq_timeout_does_not_hang_the_chatbot_and_stops_further_calls(): void
    {
        $attempts = 0;
        Http::fake(['api.groq.com/*' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        }]);

        $started = microtime(true);
        $first = $this->ask('Tell me something interesting about the moon');
        $second = $this->ask('And another question about the sun');
        $elapsed = microtime(true) - $started;

        $this->assertNotEmpty($first['answer'], 'a short fallback answer, not an error page');
        $this->assertNotEmpty($second['answer']);
        $this->assertLessThan(3, $elapsed, 'a failing Groq must not add its own wait');

        // The first message tries once (no retries) and trips the cool-down; the next skips Groq entirely.
        $this->assertSame(1, $attempts);
    }

    public function test_a_groq_server_error_also_pauses_groq(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'overloaded'], 503)]);

        $this->ask('Tell me something interesting about the moon');
        $this->ask('Tell me something interesting about the sun');

        $this->assertSame(1, $this->groqRequests());
    }

    public function test_groq_gets_a_short_limit_and_no_retries(): void
    {
        $groq = app(GroqChatService::class);
        $method = new \ReflectionMethod($groq, 'requestTimeout');

        app(AiTimings::class)->begin();

        $this->assertLessThanOrEqual(4.0, $method->invoke($groq), 'chat timeout is configured, not the old 12 s');
        $this->assertGreaterThanOrEqual(1.0, $method->invoke($groq));

        config(['ai.response_budget_seconds' => 3]);
        app(AiTimings::class)->begin();
        $this->assertLessThanOrEqual(2.5, $method->invoke($groq), 'never more than the budget still left');
    }

    public function test_groq_is_skipped_when_the_response_budget_is_spent(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);
        config(['ai.response_budget_seconds' => 1]); // less than the 1.5 s minimum worth waiting for

        $chat = $this->ask('Tell me something interesting about the moon');

        $this->assertNotEmpty($chat['answer']);
        $this->assertSame(0, $this->groqRequests());
    }

    public function test_the_cool_down_ends(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 500)]);
        $groq = app(GroqChatService::class);
        app(AiTimings::class)->begin();

        $this->ask('Tell me something interesting about the moon');
        $this->assertFalse($groq->usable(), 'paused after a server error');

        Cache::forget('ai:groq:cooldown');
        $this->assertTrue($groq->usable());
    }

    // -------------------------------------------- caching and no duplicate calls

    public function test_repeated_weather_questions_are_served_from_cache_with_one_provider_call(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => function () {
                usleep(300_000); // a slow provider

                return Http::response($this->openMeteoPayload(), 200);
            },
        ]);

        $started = microtime(true);
        $this->ask('Will it rain this week?');
        $cold = microtime(true) - $started;

        $started = microtime(true);
        $this->ask('Will it rain this week?');
        $warm = microtime(true) - $started;

        $this->assertSame(1, $this->weatherRequests(), 'the second answer must not go back to the provider');
        $this->assertLessThan($cold, $warm, 'a cached answer is faster than one that had to fetch');
        $this->assertLessThan(0.3, $warm, 'and does not include the provider wait');
    }

    public function test_the_chatbot_reuses_weather_a_few_minutes_old_instead_of_refetching(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200)]);

        $service = app(FarmerDashboardWeatherService::class);
        $service->current();                       // the dashboard fetched it
        Cache::forget($this->weatherCacheKey($service)); // its 1-minute cache has expired

        $this->travel(4)->minutes();
        $this->ask('Will it rain this week?');

        $this->assertSame(1, $this->weatherRequests(), 'a 4-minute-old snapshot is good enough for chat');

        $this->travel(20)->minutes();
        Cache::forget($this->weatherCacheKey($service));
        $this->ask('Will it rain this week?');

        $this->assertSame(2, $this->weatherRequests(), 'but an old one is refreshed');
    }

    private function weatherCacheKey(FarmerDashboardWeatherService $service): string
    {
        $method = new \ReflectionMethod($service, 'cacheKey');

        return $method->invoke($service);
    }

    public function test_a_cold_weather_lookup_from_chat_is_strict_no_retries(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['reason' => 'overloaded'], 503)]);

        app(FarmerDashboardWeatherService::class)->current(false, 3.0);

        $this->assertSame(1, $this->weatherRequests(), 'one attempt, no retries, when a person is waiting');
    }

    public function test_the_patient_lookup_used_elsewhere_still_retries(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['reason' => 'overloaded'], 503)]);

        app(FarmerDashboardWeatherService::class)->current();

        $this->assertGreaterThan(1, $this->weatherRequests(), 'dashboard and jobs keep their retry behavior');
    }

    public function test_one_message_never_fetches_the_weather_twice(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200)]);
        Cache::flush();

        $this->ask('Will it rain this week?');

        $this->assertSame(1, $this->weatherRequests());
    }

    public function test_the_knowledge_base_lookup_is_remembered(): void
    {
        \App\Models\KnowledgeBase::create([
            'question' => 'How does the planting calendar work?', 'answer' => 'Use the calendar page.',
            'category' => 'general_chat', 'keywords' => ['planting', 'calendar'], 'verified' => true,
        ]);
        Http::fake(['api.groq.com/*' => Http::response([], 500)]);

        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'from "knowledge_base"') && str_contains($query->sql, 'like')) {
                $queries++;
            }
        });

        $service = app(\App\Services\AI\KnowledgeBaseService::class);
        $service->search('How does the planting calendar work?', 'general_chat');
        $service->search('How does the planting calendar work?', 'general_chat');
        $service->search('How does the planting calendar work?', 'general_chat');

        $this->assertSame(1, $queries, 'the search runs once; repeats come from the cache');
        $this->assertSame(1, $service->search('How does the planting calendar work?', 'general_chat')?->id);
    }

    public function test_only_recent_history_is_read_and_only_once_per_message(): void
    {
        foreach (range(1, 12) as $i) {
            AIChat::create([
                'user_id' => $this->farmer->id, 'question' => "old question {$i}", 'answer' => str_repeat('x', 5000), 'intent' => 'general_chat',
                'prediction_result' => ['big' => str_repeat('y', 20000)], 'explanation' => str_repeat('z', 5000),
            ]);
        }
        Http::fake(['api.groq.com/*' => Http::response([], 500)]);
        config(['services.groq.enabled' => false]);

        $selects = [];
        DB::listen(function ($query) use (&$selects): void {
            if (str_contains($query->sql, 'from "a_i_chats"')) {
                $selects[] = $query->sql;
            }
        });

        // A message that falls through to the general assistant reads history in two places today.
        $this->ask('Tell me something interesting about the moon');

        $this->assertCount(1, $selects, 'orchestrator and prediction service share one history read');
        $this->assertStringContainsString('limit 3', $selects[0]);
        $this->assertStringNotContainsString('prediction_result', $selects[0], 'large result columns are never loaded');
        $this->assertStringNotContainsString('explanation', $selects[0]);
        $this->assertStringNotContainsString('"answer"', $selects[0]);
    }

    // --------------------------------------------------------------- timing logs

    public function test_every_stage_is_timed_and_logged_without_content_or_secrets(): void
    {
        $this->seed(AiVocabularySeeder::class);
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => 'A moon fact.']]]]),
        ]);

        $question = 'Will it rain this week? my secret farm name is Zebra Acres';
        $this->ask($question);
        $weatherLine = $this->timing();

        $this->ask('Tell me something interesting about the moon');
        $groqLine = $this->timing();

        foreach (['moderation', 'intent', 'knowledge', 'weather'] as $stage) {
            $this->assertArrayHasKey($stage, $weatherLine['stages_ms'], "weather question: {$stage}");
        }
        $this->assertArrayHasKey('groq', $groqLine['stages_ms']);
        $this->assertArrayHasKey('assistant', $groqLine['stages_ms']);
        $this->assertGreaterThanOrEqual(0, $weatherLine['total_ms']);
        $this->assertSame(['intent', 'path', 'groq_calls', 'stages_ms', 'total_ms'], array_keys($weatherLine));

        $everything = json_encode($this->logs);
        foreach (['Zebra Acres', 'interesting about the moon', self::SECRET, 'Bearer'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $everything, "the logs must not contain: {$forbidden}");
        }
    }

    public function test_the_prediction_stage_is_timed(): void
    {
        Http::fake(['api.groq.com/*' => Http::response([], 500), '*' => Http::response([], 500)]);
        config(['ai.response_budget_seconds' => 300]);

        $this->ask('What is the predicted rice yield for my 2 hectare farm next month?');

        $this->assertArrayHasKey('prediction', $this->timing()['stages_ms']);
    }

    public function test_response_time_covers_the_whole_request(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200)]);

        $chat = $this->actingAs($this->farmer)
            ->postJson(route('ai-chat.message'), ['question' => 'Will it rain this week?', 'save_conversation' => false])
            ->json('chat');

        $this->assertSame($this->timing()['total_ms'], $chat['response_time_ms']);
    }
}
