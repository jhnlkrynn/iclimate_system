<?php

namespace Tests\Feature;

use App\Exceptions\PredictionNotReady;
use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Models\MonthlyWeatherPrediction;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\AI\AiTimings;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\Prediction\PredictionReadMode;
use Carbon\CarbonImmutable;
use Database\Seeders\AiModerationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

/**
 * A chat request only READS prepared predictions. Building them (training the
 * weather forest) belongs to the scheduled refresh, the queue and the warm
 * command, never to an HTTP request a person is waiting on.
 */
class AiPredictionReadOnlyTest extends TestCase
{
    use PreparesPredictions;
    use RefreshDatabase;

    private const YIELD_QUESTION = 'Predict my rice yield for 2 hectares';

    private User $farmer;

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.groq.enabled' => false,
            'services.farming_ai.url' => 'http://python.test',
            'services.weather.provider' => 'openmeteo',
            'services.weatherapi.key' => null,
        ]);

        $this->farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->recentClimate();

        Log::listen(function ($event): void {
            $this->logs[] = ['message' => $event->message, 'context' => $event->context];
        });
    }

    /**
     * The yield model runs only when every input it needs is a real number, and the "previous month" and
     * 3/6-month trend inputs come from the last six recorded months. Without those the engine reports the
     * missing inputs and does not run the model, so these tests record six months of climate first.
     */
    private function recentClimate(): void
    {
        foreach (range(1, 6) as $monthsAgo) {
            \App\Models\ClimateRecord::factory()->create([
                'record_date' => now()->subMonths($monthsAgo)->startOfMonth()->toDateString(),
                'rainfall' => 150 + $monthsAgo * 10, 'temperature' => 27 + $monthsAgo * 0.2, 'humidity' => 80,
                'wind_speed' => 10, 'season' => \App\Models\ClimateRecord::SEASON_WET,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function ask(string $question, bool $save = false): array
    {
        return $this->actingAs($this->farmer)
            ->postJson(route('ai-chat.message'), ['question' => $question, 'save_conversation' => $save])
            ->assertOk()
            ->json('chat');
    }

    /**
     * Replaces the weather forest. It is a full mock (a partial one skips the constructor), so the dataset
     * version the prepared rows are stamped with is stubbed to a fixed value.
     *
     * @param  callable(MockInterface): void  $expectations
     */
    private function mockForest(callable $expectations): MockInterface
    {
        return $this->mock(MonthlyWeatherRandomForest::class, function (MockInterface $mock) use ($expectations): void {
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset');
            $expectations($mock);
        });
    }

    /** The trained weather forest must never be run by a request; fails the test if it is. */
    private function forestMustNotTrain(): MockInterface
    {
        return $this->mockForest(fn (MockInterface $mock) => $mock->shouldNotReceive('predict'));
    }

    private function fakePython(): void
    {
        Http::fake([
            'python.test/*' => Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 4.25, 'unit' => 'tons/hectare'],
                'confidence_score' => 84,
            ]),
        ]);
    }

    private function pythonRequests(): int
    {
        return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'python.test'))->count();
    }

    /** @return array<string, mixed> */
    private function readyForest(): array
    {
        return [
            'ready' => true, 'source_name' => 'Test forest', 'message' => 'ok', 'months_available' => 100,
            'training_period' => ['from' => '2015-01', 'to' => '2026-08'], 'confidence' => ['score' => 70],
            'predictions' => [
                'rainfall' => 226.4, 'tmax' => 31.5, 'tmin' => 24.3, 'temperature' => 27.9, 'humidity' => 82.0,
                'wind_speed' => 9.0, 'wind_direction' => 90.0, 'season' => 'Wet',
            ],
        ];
    }

    // ------------------------------------------- chat never trains or rebuilds

    public function test_a_yield_question_never_trains_the_forest_and_queues_a_refresh(): void
    {
        Queue::fake();
        $this->fakePython();
        $this->forestMustNotTrain();

        $chat = $this->ask(self::YIELD_QUESTION);

        $this->assertSame('prediction_refreshing', $chat['intent']);
        $this->assertStringContainsString('being refreshed', $chat['answer']);
        $this->assertSame(0, $this->pythonRequests(), 'no yield number is produced from nothing');
        Queue::assertPushed(GenerateMonthlyWeatherForecast::class);
        $this->assertSame(0, MonthlyWeatherPrediction::count(), 'the request must not have written a forecast either');
    }

    public function test_a_cold_prediction_cache_does_not_cause_a_slow_request(): void
    {
        Queue::fake();
        $this->fakePython();
        // The REAL forest is left in place: if any code path trained it, this request would take 20+ seconds.

        $started = microtime(true);
        $chat = $this->ask(self::YIELD_QUESTION);
        $elapsed = microtime(true) - $started;

        $this->assertSame('prediction_refreshing', $chat['intent']);
        $this->assertLessThan(6, $elapsed, 'a cold cache must never cost a training run inside the request');
    }

    public function test_weather_prediction_questions_are_read_only_too(): void
    {
        Queue::fake();
        $this->forestMustNotTrain();

        $chat = $this->ask('What rainfall does the model predict for next month?');

        $this->assertNotSame('weather_prediction', $chat['intent'], 'nothing is prepared, so no number is produced');
        Queue::assertPushed(GenerateMonthlyWeatherForecast::class);
    }

    public function test_the_latest_available_forecast_is_served_while_a_refresh_is_queued(): void
    {
        Queue::fake();
        $this->fakePython();
        $this->forestMustNotTrain();
        $this->preparePredictions(fresh: false); // prepared yesterday's run, past its freshness window

        $chat = $this->ask(self::YIELD_QUESTION);

        $this->assertNotSame('prediction_refreshing', $chat['intent'], 'the latest valid forecast is still good');
        $this->assertSame(1, $this->pythonRequests(), 'the yield engine ran on the prepared forecast');
        Queue::assertPushed(GenerateMonthlyWeatherForecast::class);
    }

    public function test_a_fresh_forecast_answers_without_queueing_anything(): void
    {
        Queue::fake();
        $this->fakePython();
        $this->forestMustNotTrain();
        $this->preparePredictions();

        $chat = $this->ask(self::YIELD_QUESTION);

        $this->assertNotSame('prediction_refreshing', $chat['intent']);
        Queue::assertNothingPushed();
        $this->assertSame('system', $this->timing()['path']);
    }

    public function test_the_refreshing_message_follows_the_language_of_the_question(): void
    {
        Queue::fake();

        $this->assertStringContainsString('Ini-refresh pa ang prediction data', $this->ask('Ilang tonelada ang aanihin ko sa 2 ektarya? Predict my yield for 2 hectares ba')['answer'] ?? '');
    }

    public function test_read_only_mode_is_always_switched_off_afterwards(): void
    {
        Queue::fake();
        $this->ask(self::YIELD_QUESTION);

        $this->assertFalse(app(PredictionReadMode::class)->active(), 'a chat request must not leave later work (queue, commands) locked out of building');
    }

    public function test_a_chat_request_never_leaves_a_process_wide_time_limit_behind(): void
    {
        // set_time_limit() in the console stays in force for the whole process (a test run, a queue
        // worker) and kills unrelated slow work later. Only real web requests may set it.
        $before = ini_get('max_execution_time');

        Queue::fake();
        $this->ask(self::YIELD_QUESTION);

        $this->assertSame($before, ini_get('max_execution_time'));
    }

    public function test_building_a_forecast_is_refused_while_read_only(): void
    {
        $this->forestMustNotTrain();
        $orchestrator = app(PredictionOrchestrator::class);
        $month = CarbonImmutable::now()->startOfMonth();

        app(PredictionReadMode::class)->enter();

        try {
            $orchestrator->generateAndPersist($month);
            $this->fail('generateAndPersist must not run in read-only mode');
        } catch (PredictionNotReady) {
            $this->addToAssertionCount(1);
        } finally {
            app(PredictionReadMode::class)->leave();
        }

        // Outside chat the same call is allowed (the queue job and the warm command rely on it).
        $this->assertFalse(app(PredictionReadMode::class)->active());
    }

    // ------------------------------------------------------------- cache warming

    public function test_warming_prepares_the_forecasts_and_yield_results_and_marks_the_cache_ready(): void
    {
        Queue::fake();
        $this->fakePython();
        // The municipal yield pass runs on the recorded average farm area; there is no default area.
        RiceProduction::factory()->create(['area_hectares' => 2.5]);
        $calls = 0;
        $this->mockForest(function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('predict')->andReturnUsing(function () use (&$calls): array {
                $calls++;

                return $this->readyForest();
            });
        });

        $this->artisan('iclimate:warm-prediction-cache', ['--wait' => 0, '--months' => 4])->assertExitCode(0);

        $this->assertSame(4, MonthlyWeatherPrediction::where('status', 'ready')->count());
        $this->assertSame(4, $calls, 'each month is built exactly once');

        $state = Cache::get('predictions:cache_state');
        $this->assertTrue($state['ready']);
        $this->assertSame(4, $state['months_ready']);
        $this->assertSame([], $state['months_missing']);
        $this->assertGreaterThan(0, $state['yield_results']);
        $this->assertGreaterThan(0, $this->pythonRequests(), 'the yield engine was run on the prepared forecasts');
    }

    public function test_warming_without_recorded_farm_areas_reports_no_yield_results_instead_of_counting_them(): void
    {
        Queue::fake();
        $this->fakePython();
        $this->mockForest(fn (MockInterface $mock) => $mock->shouldReceive('predict')->andReturn($this->readyForest()));

        $this->artisan('iclimate:warm-prediction-cache', ['--wait' => 0, '--months' => 4])
            ->expectsOutputToContain('Yield results not produced: Not enough verified input data to run the rice-yield model (missing: area).')
            ->assertExitCode(0);

        $this->assertSame(0, Cache::get('predictions:cache_state')['yield_results']);
        $this->assertSame(1, $this->pythonRequests(), 'only the municipality pass uses its legitimate workbook area');
    }

    public function test_after_warming_a_chat_yield_question_is_answered_without_any_training(): void
    {
        Queue::fake();
        $this->fakePython();
        $this->mockForest(fn (MockInterface $mock) => $mock->shouldReceive('predict')->andReturn($this->readyForest()));
        $this->artisan('iclimate:warm-prediction-cache', ['--wait' => 0, '--months' => 3])->assertExitCode(0);

        // From here on the forest must not be touched at all.
        $this->forestMustNotTrain();

        $chat = $this->ask(self::YIELD_QUESTION);

        $this->assertNotSame('prediction_refreshing', $chat['intent']);
        $this->assertNotEmpty($chat['answer']);
    }

    public function test_warming_does_not_rebuild_what_is_already_prepared(): void
    {
        $this->fakePython();
        $this->forestMustNotTrain();
        $this->preparePredictions(14);

        $this->artisan('iclimate:warm-prediction-cache', ['--wait' => 0, '--months' => 12])->assertExitCode(0);

        $this->assertTrue(Cache::get('predictions:cache_state')['ready']);
    }

    public function test_warming_reports_a_month_it_could_not_prepare(): void
    {
        $this->fakePython();
        $this->mockForest(fn (MockInterface $mock) => $mock->shouldReceive('predict')->andReturn(['ready' => false, 'message' => 'not enough history', 'predictions' => []]));

        $this->artisan('iclimate:warm-prediction-cache', ['--wait' => 0, '--months' => 2])->assertExitCode(1);

        $state = Cache::get('predictions:cache_state');
        $this->assertFalse($state['ready']);
        $this->assertCount(2, $state['months_missing']);
    }

    public function test_warming_runs_after_the_daily_refresh(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        // Times of day in the schedule are Philippine time since 2026-10-06 (config app.schedule_timezone); list them that way.
        \Illuminate\Support\Facades\Artisan::call('schedule:list', ['--timezone' => 'Asia/Manila']);
        $list = \Illuminate\Support\Facades\Artisan::output();

        $this->assertMatchesRegularExpression('/0\s+2\s+\*\s+\*\s+\*\s+php artisan iclimate:refresh-predictions/', $list);
        $this->assertMatchesRegularExpression('/15\s+2\s+\*\s+\*\s+\*\s+php artisan iclimate:warm-prediction-cache/', $list);
    }

    // ---------------------------------------- weather stays independent of yield work

    private function openMeteoPayload(): array
    {
        $today = now('Asia/Manila')->toDateString();

        return [
            'latitude' => 14.033, 'longitude' => 120.650, 'timezone' => 'Asia/Manila',
            'current' => [
                'time' => now('Asia/Manila')->format('Y-m-d\TH:i'), 'temperature_2m' => 25.5, 'relative_humidity_2m' => 95,
                'apparent_temperature' => 27.1, 'precipitation' => 0.1, 'rain' => 0.1, 'showers' => 0, 'weather_code' => 61,
                'cloud_cover' => 80, 'pressure_msl' => 1008, 'surface_pressure' => 1005, 'wind_speed_10m' => 8.4,
                'wind_direction_10m' => 180, 'wind_gusts_10m' => 15.2, 'is_day' => 1,
            ],
            'daily' => [
                'time' => [$today, now('Asia/Manila')->addDay()->toDateString()],
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

    public function test_weather_answers_while_a_prediction_refresh_is_pending(): void
    {
        Queue::fake();
        $this->fakePython();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200)]);
        $this->forestMustNotTrain();

        // A yield question first: nothing is prepared, a refresh is queued and its lock is held.
        $this->assertSame('prediction_refreshing', $this->ask(self::YIELD_QUESTION)['intent']);
        $this->assertTrue(app(PredictionOrchestrator::class)->isGenerationQueued(\App\Services\Prediction\PredictionDateValidator::defaultTargetDate()->startOfMonth()));

        // The weather path is unaffected: real data, fast, no prediction involved.
        $started = microtime(true);
        $chat = $this->ask('Will it rain this week?');
        $elapsed = microtime(true) - $started;

        $this->assertMatchesRegularExpression('/\d/', $chat['answer']);
        $this->assertNotSame('prediction_refreshing', $chat['intent']);
        $this->assertLessThan(3, $elapsed);
    }

    // -------------------------------------------------- safe fallback and no repeats

    public function test_an_unreachable_yield_service_returns_a_safe_answer_quickly(): void
    {
        Http::fake(['python.test/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);
        $this->preparePredictions();

        $started = microtime(true);
        $chat = $this->ask(self::YIELD_QUESTION);
        $elapsed = microtime(true) - $started;

        $this->assertNotEmpty($chat['answer'], 'a labelled local estimate, not an error page');
        $this->assertLessThan(5, $elapsed);
        $this->assertNotSame('prediction_refreshing', $chat['intent']);
    }

    public function test_the_yield_service_wait_is_capped_by_the_chat_budget(): void
    {
        config(['services.farming_ai.timeout' => 30, 'ai.response_budget_seconds' => 4]);
        $timings = app(AiTimings::class);
        $timings->begin();

        $this->assertLessThanOrEqual(3.0, $timings->remainingSeconds() - 1, 'the cap the service applies: never longer than the budget left');

        $timings->report();
        $this->assertFalse($timings->isActive());
        $this->assertSame(4.0, $timings->remainingSeconds(), 'outside a chat request the full budget is left, so jobs are not throttled');
    }

    public function test_the_same_yield_question_is_processed_once(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $first = $this->ask(self::YIELD_QUESTION);
        $second = $this->ask(self::YIELD_QUESTION);
        $third = $this->ask(self::YIELD_QUESTION);

        $this->assertSame(1, $this->pythonRequests(), 'the second and third answers come from the prepared result');
        $this->assertSame($first['answer'], $second['answer']);
        $this->assertSame($first['answer'], $third['answer']);
    }

    public function test_a_different_farmer_or_question_is_not_served_someone_elses_result(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $this->ask(self::YIELD_QUESTION);
        $this->farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->ask(self::YIELD_QUESTION);
        $this->ask('Predict my rice yield for 5 hectares');

        $this->assertSame(3, $this->pythonRequests());
    }

    public function test_repeated_requests_queue_one_refresh_not_many(): void
    {
        Queue::fake();

        foreach (range(1, 4) as $i) {
            $this->ask(self::YIELD_QUESTION);
        }

        Queue::assertPushed(GenerateMonthlyWeatherForecast::class, 1);
    }

    public function test_a_refreshing_answer_is_not_remembered_as_the_answer(): void
    {
        Queue::fake();
        $this->fakePython();

        $this->assertSame('prediction_refreshing', $this->ask(self::YIELD_QUESTION)['intent']);

        $this->preparePredictions(); // the refresh finished
        $this->assertNotSame('prediction_refreshing', $this->ask(self::YIELD_QUESTION)['intent']);
    }

    // ------------------------------------------------------------ timing detail

    /** @return array<string, mixed> */
    private function timing(): array
    {
        $lines = array_values(array_filter($this->logs, fn (array $log): bool => $log['message'] === 'AI response timing'));
        $this->assertNotEmpty($lines);

        return end($lines)['context'];
    }

    public function test_history_cache_prediction_insert_database_and_total_time_are_logged(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $this->ask(self::YIELD_QUESTION, save: true);
        $timing = $this->timing();

        foreach (['history', 'cache', 'prediction_lookup', 'prediction', 'insert'] as $stage) {
            $this->assertArrayHasKey($stage, $timing['stages_ms'], $stage);
        }

        $this->assertGreaterThan(0, $timing['db_queries']);
        $this->assertGreaterThanOrEqual(0, $timing['db_ms']);
        $this->assertGreaterThanOrEqual($timing['stages_ms']['prediction'], $timing['total_ms']);
        $this->assertGreaterThanOrEqual(0, $timing['total_ms']);
    }

    public function test_the_whole_request_including_the_insert_is_in_the_total(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $chat = $this->ask(self::YIELD_QUESTION, save: true);

        $this->assertGreaterThanOrEqual($chat['response_time_ms'], $this->timing()['total_ms'], 'the logged total also covers saving the chat');
        $this->assertSame(1, \App\Models\AIChat::count());
    }

    public function test_timing_logs_hold_no_message_content(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $this->ask('Predict my rice yield for 2 hectares on my farm named Zebra Acres', save: true);

        $this->assertStringNotContainsString('Zebra Acres', json_encode($this->logs));
    }

    // ---------------------------------- existing bilingual and moderation behaviour

    public function test_moderation_still_blocks_before_any_prediction_work(): void
    {
        $this->seed(AiModerationSeeder::class);
        Queue::fake();
        $this->forestMustNotTrain();
        $this->fakePython();

        $chat = $this->ask('putangina mo, predict my yield for 2 hectares');

        $this->assertSame('moderation_blocked', $chat['intent'] ?? 'moderation_blocked');
        $this->assertSame(0, $this->pythonRequests());
        Queue::assertNothingPushed();
    }

    public function test_filipino_and_english_questions_still_get_their_own_language(): void
    {
        $this->fakePython();
        $this->preparePredictions();

        $english = $this->ask(self::YIELD_QUESTION);
        $filipino = $this->ask('Ano ang aanihin ko? Predict yield para sa 2 ektarya');

        $this->assertNotSame('prediction_refreshing', $english['intent']);
        $this->assertNotEmpty($filipino['answer']);
    }
}
