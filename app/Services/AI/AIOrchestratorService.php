<?php

namespace App\Services\AI;

use App\Models\AIChat;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Prediction\PredictionOrchestrator;
use App\Exceptions\PredictionNotReady;
use App\Services\Prediction\PredictionReadMode;
use App\Services\Prediction\YieldFarmTypeAdjustment;
use App\Services\Prediction\YieldPredictionStore;
use App\Services\PredictionService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\TyphoonSafetyService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\BusinessClock;
use App\Support\LianBarangays;
use App\Support\ActiveFarm;
use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIOrchestratorService
{
    public function __construct(
        private readonly IntentDetectionService $languageDetector,
        private readonly IntentClassifierService $classifier,
        private readonly PredictionService $predictionService,
        private readonly FarmerDashboardWeatherService $weatherService,
        private readonly LianBarangayWeatherService $barangayWeatherService,
        private readonly PredictionOrchestrator $predictionOrchestrator,
        private readonly GroqChatService $groq,
        private readonly BarangayRiskMapService $riskMapService,
        private readonly ModerationService $moderation,
        private readonly LexiconService $lexicon,
        private readonly KnowledgeAnswerService $knowledge,
        private readonly AiTimings $timings,
        private readonly ConversationContext $context,
        private readonly PredictionReadMode $readMode,
    ) {}

    /** The weather read once for the current message, so two tools never fetch it twice. */
    private ?array $weatherMemo = null;

    /** @var array<string, array<string, mixed>> */
    private array $barangayWeatherMemo = [];

    /** @var array<string, Collection<int, PlantingAdvisory>> */
    private array $advisoriesMemo = [];

    private ?array $typhoonEventMemo = null;

    private bool $typhoonEventLoaded = false;

    /**
     * @return array<string, mixed>
     */
    public function answer(User $user, string $question): array
    {
        // The controller normally starts the stopwatch (so it also covers the chat insert); if this is
        // called on its own, start and finish it here.
        $ownsTimings = ! $this->timings->isActive();

        if ($ownsTimings) {
            $this->timings->begin();
        }

        $this->context->reset();
        $this->weatherMemo = null;
        $this->barangayWeatherMemo = [];
        $this->advisoriesMemo = [];
        $this->typhoonEventMemo = null;
        $this->typhoonEventLoaded = false;

        // A chat request only READS prepared predictions: it may never train a model or process a dataset.
        $this->readMode->enter();

        try {
            $result = $this->respond($user, $question);
        } finally {
            $this->readMode->leave();
        }

        $result['response_time_ms'] = $this->timings->elapsedMs();

        $intent = (string) ($result['intent'] ?? 'unknown');

        // Which route produced the answer: filter, approved knowledge, iClimate's own
        // data, or (only when a Groq call actually happened) Groq.
        $this->timings->route($intent, match (true) {
            $intent === 'moderation_blocked' => 'moderation',
            $intent === 'farming_knowledge' => 'knowledge',
            $intent === 'prediction_refreshing' => 'refreshing',
            $this->timings->groqCalls() > 0 => 'groq',
            default => 'system',
        });

        if ($ownsTimings) {
            $this->timings->report();
        }

        return $result;
    }

    /**
     * Cheapest answer first: language filter, then approved knowledge and
     * iClimate's own weather and prediction data, and Groq only when none of
     * those can answer.
     *
     * @return array<string, mixed>
     */
    private function respond(User $user, string $question): array
    {
        $startedAt = microtime(true);

        // Language filter first: a blocked message goes no further (no
        // classifier, no LLM, no history lookup, no storage of its text).
        $moderation = $this->timings->time('moderation', fn () => $this->moderation->inspect($question));

        if ($moderation['blocked']) {
            return $this->moderated($user, $question, $moderation, $startedAt);
        }

        $memory = $this->conversationMemory($user);
        $language = $this->languageDetector->detectLanguage($question, $memory, $user->aiLanguageCode());
        $farmContext = FarmerChatContext::for($user, $question);

        // "Why?" / "Show me the details" right after a short farmer answer: give the full report behind it.
        if (filled($memory['last_details'] ?? null) && FarmerAnswerStyle::isDetailsFollowUp($question)) {
            return $this->textResult((string) $memory['last_details'], (string) ($memory['last_intent'] ?: 'details'), 'Details', 'iClimate', $language, [...$memory, 'details' => null, 'brief' => false], ['confidence_score' => 80]);
        }

        // Farmer asking when to harvest: answer directly from their active farm's VarietyHarvestEstimator.
        if ($farmContext->isFarmer && $this->isHarvestTimingQuestion($question)) {
            return $this->harvestTiming($farmContext, $language, $memory);
        }

        // Rule-based classification: instant. Groq is asked below, and only if the rules cannot place the message.
        $classification = $this->timings->time('intent', fn () => $this->classifier->classifyLocal($question, $memory));
        $intent = $classification['intent'];
        $entities = $classification['entities'];
        $awaitingFollowUp = false;

        if (($pending = (string) data_get($memory, 'pending_intent')) !== '' && $this->hasArea($entities)) {
            $intent = $pending;
            $awaitingFollowUp = true;
        }

        // Approved farming/weather vocabulary: definitions and how-to answers
        // come from the knowledge base, live-data questions keep their tools.
        [$analysis, $knowledgeResult] = $this->timings->time('knowledge', function () use ($question, $awaitingFollowUp, $intent, $language, $memory, $startedAt): array {
            $analysis = $this->lexicon->analyze($question);

            return [$analysis, $awaitingFollowUp ? null : $this->farmingKnowledge($analysis, $intent, $language, $memory, $startedAt)];
        });

        if ($knowledgeResult !== null) {
            return $knowledgeResult;
        }

        $intent = $this->refineIntentWithConcepts($intent, $analysis);

        // Only now, for a message that is still unplaced, is Groq worth its wait.
        if (! $awaitingFollowUp && $this->classifier->needsLlm(['intent' => $intent, 'requires_clarification' => (bool) ($classification['requires_clarification'] ?? false)])) {
            $llm = $this->timings->time('intent', fn () => $this->classifier->classifyWithLlm($user, $question, $language, $memory));

            if ($llm !== null) {
                $classification = $llm;
                $intent = $llm['intent'];
                $entities = $llm['entities'];
            }
        }

        if (($classification['requires_clarification'] ?? false) && $intent === IntentClassifierService::UNKNOWN) {
            return $this->clarification(
                $language,
                $memory,
                'unknown_prediction',
                'Do you want a weather/rainfall prediction or a rice-yield prediction?',
                'Weather/rainfall prediction ba o rice-yield prediction ang gusto mo?'
            );
        }

        Log::info('AI orchestrator selected intent.', [
            'user_id' => $user->id,
            'intent' => $intent,
            'classifier_source' => $classification['source'],
            'confidence' => $classification['confidence'],
        ]);

        try {
            $result = match ($intent) {
                IntentClassifierService::GREETING => $this->greeting($language, $memory),
                IntentClassifierService::CURRENT_WEATHER => $this->currentWeather($user, $question, $language, $memory, $entities, $farmContext),
                IntentClassifierService::WEATHER_FORECAST => $this->weatherForecast($user, $question, $language, $memory, $entities, $farmContext),
                IntentClassifierService::WEATHER_PREDICTION => $this->timings->time('prediction', fn () => $this->weatherPrediction($user, $question, $language, $memory, $entities, $farmContext)),
                IntentClassifierService::RICE_YIELD_PREDICTION,
                IntentClassifierService::PLANTING_RECOMMENDATION,
                IntentClassifierService::IRRIGATION_RECOMMENDATION => $this->timings->time('prediction', fn () => $this->cachedPrediction($user, $question, $language, $intent, $entities, $memory, fn () => $this->modelBackedPrediction($user, $question, $language, $memory, $intent, $entities, $farmContext), $farmContext)),
                IntentClassifierService::CLIMATE_RISK => $this->climateRisk($user, $question, $language, $memory, $entities, $farmContext),
                IntentClassifierService::WEATHER_ADVISORY => $this->advisories($user, $question, $language, $memory, $farmContext),
                IntentClassifierService::SYSTEM_HELP,
                IntentClassifierService::PROFILE_ACCOUNT,
                IntentClassifierService::COMMUNITY,
                IntentClassifierService::REPORTS,
                IntentClassifierService::GENERAL_CHAT,
                IntentClassifierService::UNKNOWN => $this->timings->time('assistant', fn () => $this->predictionService->answer($user, $question, $farmContext)),
                default => $this->timings->time('assistant', fn () => $this->predictionService->answer($user, $question, $farmContext)),
            };
        } catch (PredictionNotReady) {
            // Nothing prepared yet and this request may not build it: say so, and a refresh is already queued.
            $result = $this->predictionRefreshing($language, $memory, $farmContext);
        } catch (Throwable $exception) {
            Log::warning('AI orchestrator route failed.', [
                'intent' => $intent,
                'message' => $exception->getMessage(),
            ]);

            $result = $this->toolFailure($intent, $language, $exception->getMessage());
        }

        $result['language'] = $result['language'] ?? $language;
        // last_details is read from the previous chat; saving it again would copy the report into every chat.
        unset($result['memory']['last_details']);
        $result['memory'] = [
            ...($result['memory'] ?? \Illuminate\Support\Arr::except($memory, ['last_details'])),
            'orchestrator' => [
                'intent' => $intent,
                'classifier_source' => $classification['source'],
                'classifier_confidence' => $classification['confidence'],
                'entities' => $entities,
            ],
        ];

        return $result;
    }

    private function greeting(string $language, array $memory): array
    {
        return $this->textResult(
            $language === 'Tagalog'
                ? 'Hello! Ano ang maitutulong ko sa iyo ngayon?'
                : 'Hello! How can I help you today?',
            IntentClassifierService::GREETING,
            'Assistant',
            'Climora AI',
            $language,
            $memory,
            ['confidence_score' => 100],
        );
    }

    /**
     * The reply to a message the language filter blocked. It says nothing
     * about what was matched and does not repeat the message; the controller
     * replaces the stored question because of `question_redacted`.
     *
     * @param  array{category: ?string, reason_code: ?string}  $moderation
     * @return array<string, mixed>
     */
    private function moderated(User $user, string $question, array $moderation, float $startedAt): array
    {
        $language = $this->languageDetector->detectLanguage($question, [], $user->aiLanguageCode());

        // Profanity with no other Filipino cue words ("putangina mo") reads as
        // English to the language detector; the matched entry knows better.
        if (! str_contains($language, 'Tagalog') && ($moderation['language'] ?? null) === 'fil') {
            $language = 'Tagalog';
        }

        $this->moderation->record($user, $language, $moderation);

        $result = $this->textResult($this->moderation->message($language), 'moderation_blocked', 'Language filter', 'Climora AI', $language, [], [
            'confidence_score' => 100,
            'prediction_result' => ['moderated' => true],
        ]);
        $result['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        $result['question_redacted'] = true;

        return $result;
    }

    /**
     * Answers from approved knowledge when the message is a definition or
     * how-to question (or a short, unclassified mention of a known term), and
     * never for live weather, forecasts, yield estimates or account topics.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    private function farmingKnowledge(array $analysis, string $intent, string $language, array $memory, float $startedAt): ?array
    {
        if ($analysis['concepts'] === []) {
            return null;
        }

        $mode = $this->knowledge->informationalMode($analysis);
        $unclassified = in_array($intent, [IntentClassifierService::GENERAL_CHAT, IntentClassifierService::UNKNOWN], true);
        $recommendation = in_array($intent, [IntentClassifierService::PLANTING_RECOMMENDATION, IntentClassifierService::IRRIGATION_RECOMMENDATION], true);
        $toolIntent = $recommendation || in_array($intent, [
            IntentClassifierService::CURRENT_WEATHER,
            IntentClassifierService::WEATHER_FORECAST,
            IntentClassifierService::WEATHER_PREDICTION,
            IntentClassifierService::RICE_YIELD_PREDICTION,
            IntentClassifierService::CLIMATE_RISK,
            IntentClassifierService::WEATHER_ADVISORY,
        ], true);

        $eligible = match ($mode) {
            KnowledgeAnswerService::MODE_DEFINITION => $unclassified || $toolIntent,
            // "When should I harvest?" reads as a yield request to the classifier, but with no
            // numbers or "my" it is asking how, not for an estimate.
            KnowledgeAnswerService::MODE_HOWTO => $unclassified || $recommendation || $intent === IntentClassifierService::RICE_YIELD_PREDICTION,
            default => $unclassified,
        };

        if (! $eligible) {
            return null;
        }

        // A bare mention that also carries a time or "my" cue ("maulap ba ngayon")
        // is a live-data question: leave it to the weather and prediction tools.
        if ($mode === null && ($analysis['has_now'] || $analysis['has_future'] || $analysis['has_personal'] || $analysis['has_number'])) {
            return null;
        }

        // A short message naming several different things: ask, do not guess.
        if ($unclassified && $mode === null && ($question = $this->knowledge->clarification($analysis, $language)) !== null) {
            $result = $this->textResult($question, 'clarification', 'Clarification', 'Climora AI', $language, $memory, ['confidence_score' => 70]);
            $result['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            return $result;
        }

        $hit = $this->knowledge->find($analysis, $language, $mode);

        if ($hit !== null) {
            $result = $this->textResult($hit['answer'], 'farming_knowledge', 'Approved Knowledge Base', $hit['source'], $language, $memory, ['confidence_score' => 90]);
            $result['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            return $result;
        }

        if ($unclassified && ($question = $this->knowledge->clarification($analysis, $language)) !== null) {
            $result = $this->textResult($question, 'clarification', 'Clarification', 'Climora AI', $language, $memory, ['confidence_score' => 70]);
            $result['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            return $result;
        }

        return null;
    }

    /**
     * Filipino and Taglish weather words the classifier does not recognise
     * (e.g. "uulan ba bukas") still reach the live-weather tools. Only
     * unclassified messages are re-routed.
     *
     * @param  array<string, mixed>  $analysis
     */
    private function refineIntentWithConcepts(string $intent, array $analysis): string
    {
        if (! in_array($intent, [IntentClassifierService::GENERAL_CHAT, IntentClassifierService::UNKNOWN], true)) {
            return $intent;
        }

        $weather = array_filter($analysis['concepts'], fn (array $concept): bool => $concept['category'] === 'weather' && ! $concept['fuzzy']);

        if ($weather === []) {
            return $intent;
        }

        return match (true) {
            $analysis['has_future'] => IntentClassifierService::WEATHER_FORECAST,
            $analysis['has_now'] => IntentClassifierService::CURRENT_WEATHER,
            default => $intent,
        };
    }

    /**
     * The same person asking the same prediction question again within a few
     * minutes gets the answer already produced, and two identical requests in
     * flight at once do the work once (the second waits for the first).
     *
     * @param  array<string, mixed>  $entities
     * @param  array<string, mixed>  $memory
     * @param  callable(): array<string, mixed>  $compute
     * @return array<string, mixed>
     */
    private function cachedPrediction(User $user, string $question, string $language, string $intent, array $entities, array $memory, callable $compute, ?FarmerChatContext $farmContext = null): array
    {
        $ttl = (int) config('ai.prediction_result_cache_seconds', 600);

        if ($ttl <= 0) {
            return $compute();
        }

        $key = 'ai:prediction:'.md5(json_encode([$user->id, $farmContext?->activeFarm?->id, $farmContext?->barangay, $intent, $language, $entities, mb_strtolower(preg_replace('/\s+/', ' ', trim($question)) ?? '')]));

        $fromCache = fn (): ?array => $this->timings->time('cache', function () use ($key, $memory): ?array {
            $hit = Cache::get($key);

            if (! is_array($hit)) {
                return null;
            }

            // The answer is reused, the conversation state is this request's.
            $hit['memory'] = [...$memory, 'pending_intent' => null];

            return $hit;
        });

        if (($hit = $fromCache()) !== null) {
            return $hit;
        }

        $lock = Cache::lock($key.':lock', 30);
        $owned = false;

        try {
            $lock->block(max(1, min(4, (int) floor($this->timings->remainingSeconds() - 2))));
            $owned = true;
        } catch (LockTimeoutException) {
            // Another request is still computing it; do not hold this one up any longer.
        }

        try {
            // Whoever held the lock may have just finished it.
            if ($owned && ($hit = $fromCache()) !== null) {
                return $hit;
            }

            $result = $compute();

            if (($result['prediction_result']['error'] ?? null) === null && ! ($result['prediction_result']['requires_clarification'] ?? false)) {
                Cache::put($key, $result, $ttl);
            }

            return $result;
        } finally {
            if ($owned) {
                $lock->release();
            }
        }
    }

    /**
     * The short answer when a prediction has not been prepared yet. A refresh
     * is already queued; nothing is guessed and nothing is trained in this request.
     *
     * @param  array<string, mixed>  $memory
     * @return array<string, mixed>
     */
    private function predictionRefreshing(string $language, array $memory, ?FarmerChatContext $farmContext = null): array
    {
        $tagalog = str_contains($language, 'Tagalog');
        $text = $this->translate(
            $language,
            'The prediction data is being refreshed right now (yield refreshes every 3 hours; forecasts refresh daily), so I cannot give you a number yet. Please ask again in a few minutes. I will not guess it.',
            'Ini-refresh pa ang prediction data ngayon (nagre-refresh ang ani tuwing 3 oras; araw-araw ang forecast), kaya wala pa akong maibibigay na numero. Subukan ulit sa loob ng ilang minuto. Hindi ako manghuhula.'
        );
        if ($farmContext !== null && $farmContext->isFarmer) {
            $text = $this->appendIncompleteProfileLine($text, $farmContext, $tagalog);
        }

        $result = $this->textResult(
            $text,
            'prediction_refreshing',
            'Prediction Status',
            'iClimate prediction system',
            $language,
            $memory,
            ['confidence_score' => 0, 'prediction_result' => ['refreshing' => true]],
        );
        $result['refreshing'] = true;

        return $result;
    }

    /**
     * Live weather, fetched at most once per message. With a warm cache this is
     * instant; on a cold cache each provider gets a short limit, and if both
     * fail the service returns the last data it had.
     *
     * @return array<string, mixed>
     */
    private function weather(): array
    {
        return $this->weatherMemo ??= $this->timings->time('weather', fn (): array => $this->weatherService->current(
            false,
            max(1.0, min((float) config('ai.weather_timeout', 3), $this->timings->remainingSeconds() - 1)),
        ));
    }

    /**
     * That barangay's shared weather record — the same reading the Heat Map and Advisories show. A due record
     * is refreshed after the reply (its last available values are used now, marked delayed when they are); only
     * a cold cache with no reading at all is fetched now (all 19 in one batched request), time permitting.
     *
     * @return array<string, mixed>
     */
    private function barangayWeather(string $barangay): array
    {
        return $this->barangayWeatherMemo[$barangay] ??= $this->timings->time('barangay_weather', function () use ($barangay): array {
            $record = $this->barangayWeatherService->getCurrentWeather($barangay) ?? [];

            if (($record['status'] ?? 'unavailable') === 'unavailable' && $this->timings->remainingSeconds() > 8) {
                return rescue(fn (): array => $this->barangayWeatherService->latestForBarangay($barangay), $record, false);
            }

            $this->barangayWeatherService->refreshIfDue($barangay);

            return $record;
        });
    }

    /**
     * The official Lian barangay a weather question asks about, or null for a Lian-wide answer: a barangay named in the
     * question, or the farmer's own registered barangay when they ask about "my barangay" / "my farm" / "sa bukid".
     * Anything else (or an unconfirmed registered barangay) is answered for Lian as a whole.
     *
     * @param  array<string, mixed>  $entities
     */
    private function weatherBarangay(User $user, string $question, array $entities, ?FarmerChatContext $farmContext = null): ?string
    {
        $text = mb_strtolower($question);
        $named = is_string($entities['barangay'] ?? null) ? $entities['barangay'] : null;
        $named ??= collect(LianBarangays::all())->first(fn (string $barangay): bool => str_contains($text, mb_strtolower($barangay)));
        if ($named !== null) {
            return LianBarangays::canonicalName($named);
        }

        $ownPlace = ['my barangay', 'our barangay', 'my farm', 'my field', 'aming barangay', 'barangay ko', 'bukid ko', 'aking bukid', 'sa bukid', 'my area', 'lugar ko'];

        if (str($text)->contains($ownPlace)) {
            return $farmContext?->barangay ?? $user->registeredBarangay();
        }

        return $farmContext?->overriddenBy === 'farm_number' || $farmContext?->overriddenBy === 'farm_name'
            ? $farmContext->barangay
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentWeather(User $user, string $question, string $language, array $memory, array $entities, FarmerChatContext $farmContext): array
    {
        $barangay = $this->weatherBarangay($user, $question, $entities, $farmContext);
        $barangayWeather = $barangay ? $this->barangayWeather($barangay) : null;
        $weather = $barangayWeather ?? $this->weather();
        $tool = [
            'tool' => 'current_weather',
            'success' => $barangayWeather
                ? ($weather['status'] ?? 'unavailable') !== 'unavailable' && is_numeric($weather['temperature_c'] ?? null)
                : (bool) ($weather['success'] ?? false),
            'source' => $weather['provider'] ?? $weather['source'] ?? 'Weather service',
            'location' => $barangayWeather ? ['name' => $barangay, 'municipality' => 'Lian', 'province' => 'Batangas'] : ($weather['location'] ?? ['name' => 'Lian', 'province' => 'Batangas']),
            'farm_context' => $farmContext->toArray(),
            'data' => $barangayWeather ? [
                'condition' => $weather['condition'] ?? null,
                'temperature_c' => $weather['temperature_c'] ?? null,
                'feels_like_c' => null,
                'humidity_percent' => $weather['humidity_percent'] ?? null,
                'rainfall_today_mm' => $weather['rainfall_24h_mm'] ?? null,
                'rain_probability_percent' => $weather['rain_probability_percent'] ?? null,
                'wind_kph' => $weather['wind_speed_kmh'] ?? null,
                'provider_updated_at' => $weather['fetched_at'] ?? null,
                'stale' => (bool) ($weather['stale'] ?? false),
            ] : [
                'condition' => \App\Support\WeatherConditionLabel::display(data_get($weather, 'current.condition')),
                'temperature_c' => data_get($weather, 'current.temperature'),
                'feels_like_c' => data_get($weather, 'current.feels_like'),
                'humidity_percent' => data_get($weather, 'current.humidity'),
                'rainfall_today_mm' => data_get($weather, 'today.rainfall'),
                'wind_kph' => data_get($weather, 'current.wind_speed'),
                'provider_updated_at' => data_get($weather, 'timestamps.server_fetched_at') ?? $weather['fetched_at_label'] ?? null,
                'stale' => (bool) ($weather['stale'] ?? false),
            ],
        ];

        $tagalog = str_contains($language, 'Tagalog');
        if (! $tool['success']) {
            Log::warning('Climora current_weather unavailable.', ['barangay' => $barangay]);
        } elseif ($tool['data']['stale'] ?? false) {
            Log::warning('Climora current_weather is stale.', ['barangay' => $barangay]);
        }

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $user, $barangay, $tagalog) {
            $updated = $this->formatUpdatedAt($tool['data']['provider_updated_at'] ?? null, $tagalog);
            $updatedSuffix = $updated ? ' '.$updated : '';

            if (! $tool['success']) {
                return $this->translate(
                    $language,
                    ($barangay ? "Latest weather for {$barangay} is temporarily unavailable." : 'Current weather data is temporarily unavailable.').' Weather refreshes every 3 minutes.',
                    ($barangay ? "Hindi available ngayon ang latest weather para sa {$barangay}." : 'Hindi available ngayon ang current weather data.').' Nagre-refresh ang panahon tuwing 3 minuto.'
                );
            }

            $data = $tool['data'];

            if ($barangay) {
                $temperature = is_numeric($data['temperature_c'] ?? null) ? FarmerAnswerStyle::number($data['temperature_c']).'°C' : 'temperature unavailable';
                $chance = is_numeric($data['rain_probability_percent'] ?? null) ? FarmerAnswerStyle::number($data['rain_probability_percent']).'%' : 'unavailable';
                $rainfall = is_numeric($data['rainfall_today_mm'] ?? null) ? FarmerAnswerStyle::number($data['rainfall_today_mm']).' mm' : 'unavailable';
                $delayedEn = $data['stale'] ? ' Weather update is temporarily delayed (refreshes every 3 minutes); these are the last available values.' : '';
                $delayedTl = $data['stale'] ? ' Delayed ang weather update (nagre-refresh tuwing 3 minuto); ito ang huling available na datos.' : '';

                return $this->translate(
                    $language,
                    "Latest weather update for {$barangay}, Lian: {$data['condition']}, {$temperature}. Rain chance for the next 24 hours is {$chance}, with {$rainfall} expected rainfall.{$updatedSuffix}{$delayedEn}",
                    "Latest weather update para sa {$barangay}, Lian: {$data['condition']}, {$temperature}. Ang tsansa ng ulan sa susunod na 24 oras ay {$chance}, na may {$rainfall} na inaasahang ulan.{$updatedSuffix}{$delayedTl}"
                );
            }

            if ($user->role === User::ROLE_FARMER) {
                $temperature = is_numeric($data['temperature_c'] ?? null) ? FarmerAnswerStyle::number($data['temperature_c']).'°C' : null;
                $wind = is_numeric($data['wind_kph'] ?? null) ? FarmerAnswerStyle::number($data['wind_kph']).' km/h' : null;
                $en = array_filter([FarmerAnswerStyle::condition($data['condition'], false), $temperature, $wind ? 'wind '.$wind : null]);
                $tl = array_filter([FarmerAnswerStyle::condition($data['condition'], true), $temperature, $wind ? 'hangin na '.$wind : null]);

                return $this->translate(
                    $language,
                    'Current weather in Lian: '.implode(', ', $en).'.'.$updatedSuffix,
                    'Kasalukuyang panahon sa Lian: '.implode(', ', $tl).'.'.$updatedSuffix
                );
            }

            return $this->translate(
                $language,
                'According to '.$tool['source'].', the current weather in Lian, Batangas is '.$data['condition'].' with '.$data['temperature_c'].' C, humidity '.$data['humidity_percent'].'%, and wind '.$data['wind_kph'].' km/h.'.$updatedSuffix,
                'Ayon sa '.$tool['source'].', ang kasalukuyang panahon sa Lian, Batangas ay '.$data['condition'].' na may '.$data['temperature_c'].' C, humidity na '.$data['humidity_percent'].'%, at hangin na '.$data['wind_kph'].' km/h.'.$updatedSuffix
            );
        });

        if ($farmContext->isFarmer) {
            $answer = $this->appendWhatToDo($answer, $farmContext, [
                'rainfall_mm' => $tool['data']['rainfall_today_mm'] ?? null,
                'rain_chance' => $tool['data']['rain_probability_percent'] ?? null,
                'temperature_c' => $tool['data']['temperature_c'] ?? null,
                'condition' => $tool['data']['condition'] ?? null,
            ], [], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'current_weather', 'Live Weather', (string) $tool['source'], $language, $memory, [
            'tool_result' => $tool,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function weatherForecast(User $user, string $question, string $language, array $memory, array $entities, FarmerChatContext $farmContext): array
    {
        $barangay = $this->weatherBarangay($user, $question, $entities, $farmContext);
        $barangayWeather = $barangay ? $this->barangayWeather($barangay) : null;
        $weather = $barangayWeather ?? $this->weather();
        $forecast = $barangayWeather ? [[
            'day' => 'Next 24 hours',
            'condition' => $weather['condition'] ?? 'Condition unavailable',
            'rainfall' => $weather['rainfall_24h_mm'] ?? null,
            'precipitation_probability' => $weather['rain_probability_percent'] ?? null,
            'temperature_max' => $weather['temperature_c'] ?? null,
        ]] : array_map(
            fn ($day) => is_array($day) && isset($day['condition']) ? ['condition' => \App\Support\WeatherConditionLabel::display($day['condition'])] + $day : $day,
            (array) ($weather['forecast'] ?? [])
        );
        $target = $entities['target_period'] ?? null;
        $selected = $target === 'tomorrow' && isset($forecast[1]) ? [$forecast[1]] : $forecast;
        $updatedAt = $barangayWeather
            ? ($weather['fetched_at'] ?? null)
            : (data_get($weather, 'timestamps.server_fetched_at') ?? $weather['fetched_at_label'] ?? null);
        $stale = (bool) ($weather['stale'] ?? false);

        $tool = [
            'tool' => 'weather_forecast',
            'success' => ($barangayWeather ? ($weather['status'] ?? 'unavailable') !== 'unavailable' : (bool) ($weather['success'] ?? false)) && $forecast !== [],
            'source' => $weather['provider'] ?? $weather['source'] ?? 'Weather forecast service',
            'location' => $barangayWeather ? ['name' => $barangay, 'municipality' => 'Lian', 'province' => 'Batangas'] : ($weather['location'] ?? ['name' => 'Lian', 'province' => 'Batangas']),
            'target_period' => $target ?: 'next_7_days',
            'farm_context' => $farmContext->toArray(),
            'updated_at' => $updatedAt,
            'stale' => $stale,
            'data' => array_values(array_slice($selected, 0, 7)),
        ];

        $tagalog = str_contains($language, 'Tagalog');
        if (! $tool['success']) {
            Log::warning('Climora weather_forecast unavailable.', ['barangay' => $barangay]);
        } elseif ($stale) {
            Log::warning('Climora weather_forecast is stale.', ['barangay' => $barangay]);
        }

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $user, $barangay, $tagalog, $updatedAt, $stale) {
            $updated = $this->formatUpdatedAt($updatedAt, $tagalog);
            $updatedSuffix = $updated ? ' '.$updated : '';

            if (! $tool['success']) {
                return $this->translate(
                    $language,
                    'The weather forecast is temporarily unavailable (weather refreshes every 3 minutes). I will not guess the forecast.',
                    'Hindi available ngayon ang weather forecast (nagre-refresh ang panahon tuwing 3 minuto). Hindi ako manghuhula ng forecast.'
                );
            }

            $first = $tool['data'][0] ?? [];
            $staleNote = $stale
                ? ($tagalog ? ' Delayed ang update (nagre-refresh tuwing 3 minuto).' : ' Update is delayed (weather refreshes every 3 minutes).')
                : '';

            if ($barangay) {
                $chance = is_numeric($first['precipitation_probability'] ?? null) ? FarmerAnswerStyle::number($first['precipitation_probability']).'%' : 'unavailable';
                $rainfall = is_numeric($first['rainfall'] ?? null) ? FarmerAnswerStyle::number($first['rainfall']).' mm' : 'unavailable';

                return $this->translate(
                    $language,
                    "For {$barangay}, Lian, the next-24-hour rain chance is {$chance}, with {$rainfall} expected rainfall.{$updatedSuffix}{$staleNote}",
                    "Para sa {$barangay}, Lian, ang tsansa ng ulan sa susunod na 24 oras ay {$chance}, na may {$rainfall} na inaasahang ulan.{$updatedSuffix}{$staleNote}"
                );
            }

            if ($user->role === User::ROLE_FARMER) {
                $rain = is_numeric($first['rainfall'] ?? null) ? FarmerAnswerStyle::number($first['rainfall']).' mm' : null;
                $high = is_numeric($first['temperature_max'] ?? null) ? FarmerAnswerStyle::number($first['temperature_max']).'°C' : null;
                $chance = is_numeric($first['precipitation_probability'] ?? null) ? FarmerAnswerStyle::number($first['precipitation_probability']).'%' : null;

                $en = FarmerAnswerStyle::day($first, false).' '.FarmerAnswerStyle::condition($first['condition'] ?? null, false, 'sentence').'.'
                    .match (true) {
                        $high && $rain => " Expected temperature is up to {$high} with {$rain} of rain.",
                        (bool) $rain => " Expected rain is {$rain}.",
                        (bool) $high => " Expected temperature is up to {$high}.",
                        default => '',
                    }
                    .($chance ? " Rain chance is {$chance}." : '')
                    .$updatedSuffix.$staleNote;
                $tl = FarmerAnswerStyle::day($first, true).' ay '.FarmerAnswerStyle::condition($first['condition'] ?? null, true, 'sentence').'.'
                    .match (true) {
                        $high && $rain => " Inaasahang temperatura ay hanggang {$high} at ulan ay {$rain}.",
                        (bool) $rain => " Inaasahang ulan ay {$rain}.",
                        (bool) $high => " Inaasahang temperatura ay hanggang {$high}.",
                        default => '',
                    }
                    .($chance ? " May {$chance} na tsansa ng ulan." : '')
                    .$updatedSuffix.$staleNote;

                return $this->translate($language, $en, $tl);
            }

            return $this->translate(
                $language,
                'Based on '.$tool['source'].', '.$first['day'].' is expected to be '.$first['condition'].' with about '.$first['rainfall'].' mm rainfall and '.$first['precipitation_probability'].'% rain chance.'.$updatedSuffix,
                'Batay sa '.$tool['source'].', sa '.$first['day'].' ay inaasahang '.$first['condition'].' na may humigit-kumulang '.$first['rainfall'].' mm ulan at '.$first['precipitation_probability'].'% chance of rain.'.$updatedSuffix
            );
        });

        if ($farmContext->isFarmer) {
            $first = $tool['data'][0] ?? [];
            $answer = $this->appendWhatToDo($answer, $farmContext, [
                'rainfall_mm' => $first['rainfall'] ?? null,
                'rain_chance' => $first['precipitation_probability'] ?? null,
                'temperature_c' => $first['temperature_max'] ?? null,
                'condition' => $first['condition'] ?? null,
            ], [], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'weather_forecast', 'Live Weather Forecast', (string) $tool['source'], $language, $memory, [
            'tool_result' => $tool,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function weatherPrediction(User $user, string $question, string $language, array $memory, array $entities, FarmerChatContext $farmContext): array
    {
        $targetMonth = $this->targetMonth($entities);
        // Same persisted forecast the dashboards, calendar and risk map read,
        // so the chatbot never quotes a different monthly figure.
        $prediction = $this->predictionOrchestrator->monthlyWeatherForecast($targetMonth);
        $tagalog = str_contains($language, 'Tagalog');
        $tool = [
            'tool' => 'weather_prediction',
            'success' => (bool) ($prediction['ready'] ?? false),
            'source' => $prediction['source_name'] ?? 'iClimate monthly Random Forest model',
            'model_used' => 'storage/models/weather_model.pkl / MonthlyWeatherRandomForest',
            'target_month' => $targetMonth->format('F Y'),
            'farm_context' => $farmContext->toArray(),
            'data' => $prediction['predictions'] ?? [],
            'confidence' => $prediction['confidence'] ?? null,
            'message' => $prediction['message'] ?? null,
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $user) {
            if (! $tool['success']) {
                $why = $user->role === User::ROLE_FARMER ? '' : ' '.$tool['message'];

                return $this->translate($language, 'I cannot get the monthly weather forecast right now (monthly forecasts refresh daily).'.$why, 'Hindi ko makuha ngayon ang buwanang forecast ng panahon (araw-araw nagre-refresh ang forecast).'.$why);
            }

            $data = $tool['data'];

            return $this->translate(
                $language,
                'Based on the iClimate monthly Random Forest model, the estimate for '.$tool['target_month'].' is '.$data['rainfall'].' mm rainfall, '.$data['temperature'].' C temperature, '.$data['humidity'].'% humidity, and a wind speed reading of '.$data['wind_speed'].'.',
                'Batay sa iClimate monthly Random Forest model, ang estimate para sa '.$tool['target_month'].' ay '.$data['rainfall'].' mm ulan, '.$data['temperature'].' C temperatura, '.$data['humidity'].'% humidity, at wind speed reading na '.$data['wind_speed'].'.'
            );
        });

        if ($tool['success'] && $user->role === User::ROLE_FARMER && ! FarmerAnswerStyle::asksForDetails($question)) {
            $data = $tool['data'];
            $memory = [...$memory, 'details' => $answer, 'brief' => true];
            $answer = FarmerAnswerStyle::monthlyWeather((string) $tool['target_month'], $data['rainfall'] ?? null, $data['temperature'] ?? null, $data['humidity'] ?? null, $tagalog);
        }

        if ($farmContext->isFarmer) {
            $data = $tool['data'];
            $answer = $this->appendWhatToDo($answer, $farmContext, [
                'rainfall_mm' => $data['rainfall'] ?? null,
                'temperature_c' => $data['temperature'] ?? null,
            ], [], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'weather_prediction', 'Trained Model', (string) $tool['source'], $language, $memory, [
            'tool_result' => $tool,
            'weather_prediction' => [
                ...($prediction['predictions'] ?? []),
                'target_month' => $targetMonth->format('F Y'),
                'source_name' => $tool['source'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function modelBackedPrediction(User $user, string $question, string $language, array $memory, string $intent, array $entities, FarmerChatContext $farmContext): array
    {
        $tagalog = str_contains($language, 'Tagalog');

        if ($intent === IntentClassifierService::RICE_YIELD_PREDICTION && $this->predictionService->hasInvalidTargetDate($question)) {
            return $this->predictionService->answer($user, $question, $farmContext);
        }

        // Stored yield_predictions (warm-farmer-yields) with YieldFarmTypeAdjustment for farmer yield questions without manual overrides.
        if ($intent === IntentClassifierService::RICE_YIELD_PREDICTION && $farmContext->isFarmer && ! $this->hasArea($entities) && ! FarmerAnswerStyle::asksForDetails($question)) {
            $storedYield = $this->storedYieldAnswer($farmContext, $language, $memory);
            if ($storedYield !== null) {
                return $storedYield;
            }
        }

        $hasSavedFarmArea = $farmContext->farmArea !== null;
        if ($intent === IntentClassifierService::RICE_YIELD_PREDICTION && ! $this->hasArea($entities) && ! $hasSavedFarmArea) {
            return $this->clarification($language, $memory, 'rice_yield_prediction', 'I can predict your rice yield. What farm area in hectares should I use? For example: 2.5 hectares.', 'Kaya kong i-predict ang rice yield mo. Ilang ektarya ang gagamitin ko? Halimbawa: 2.5 hectares.');
        }

        $modelQuestion = $question;
        if ($intent === IntentClassifierService::RICE_YIELD_PREDICTION && $this->hasArea($entities) && ! str_contains(strtolower($question), 'yield')) {
            $modelQuestion = 'Predict my rice yield for '.$entities['area'].' hectares.';
        }

        $result = $this->predictionService->answer($user, $modelQuestion, $farmContext);

        if ($farmContext->isFarmer && $intent === IntentClassifierService::PLANTING_RECOMMENDATION) {
            $result['answer'] = $this->appendWhatToDo((string) ($result['answer'] ?? ''), $farmContext, [], [], $tagalog);
            $result['answer'] = $this->appendIncompleteProfileLine((string) $result['answer'], $farmContext, $tagalog);
        } elseif ($farmContext->isFarmer && $farmContext->incompleteProfile) {
            $result['answer'] = $this->appendIncompleteProfileLine((string) ($result['answer'] ?? ''), $farmContext, $tagalog);
        }

        $result['memory'] = [
            ...($result['memory'] ?? $memory),
            'pending_intent' => null,
        ];

        return $result;
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    /**
     * Staleness-aware climate risk lookup.
     *
     * HeatmapArea.risk_score/risk_level are only as fresh as the last
     * successful `iclimate:refresh-barangay-risk` run (or cache-miss risk map
     * compute) that could reach that specific barangay. Presenting an old row
     * as unqualified present-tense fact would misrepresent it, so every row
     * is classified before being turned into an answer:
     *  - CURRENT: risk_computed_at is within HeatmapArea::CURRENT_WINDOW_HOURS
     *    AND the live BarangayRiskMapService does not currently report this
     *    barangay as No Data (a barangay that has gone No Data live — e.g.
     *    because monthly rainfall recently became unavailable — must not be
     *    presented as current just because an old row still exists).
     *  - STALE: a row exists but fails either condition above.
     *  - NO CURRENT DATA: no row exists at all for the barangay.
     *
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function climateRisk(User $user, string $question, string $language, array $memory, array $entities, FarmerChatContext $farmContext): array
    {
        // A place named in the question always wins over the asker's own barangay, including Lian barangays outside
        // the supported list (the classifier only knows supported names): "Is Cumba high risk?" / "Ano ang climate
        // risk sa Cumba?" must be answered for Cumba, in either language, never for the asker's profile barangay.
        $explicitPlace = (is_string($entities['barangay'] ?? null) ? $entities['barangay'] : null)
            ?? LianBarangays::namedIn($question);

        $barangay = $explicitPlace
            ?? ($user->role === User::ROLE_FARMER ? $farmContext->barangay : ($user->barangay ?? $user->farmerProfile?->barangay));
        $barangay = (string) ($barangay ?? '');

        if ($barangay !== '') {
            return $this->climateRiskForBarangay($user, $question, $language, $memory, $barangay, $farmContext);
        }

        // No specific barangay named: rank across whatever rows are CURRENT
        // right now, never mixing in stale rows unqualified.
        $current = HeatmapArea::query()
            ->computed()
            ->orderByDesc('risk_score')
            ->take(20)
            ->get()
            ->filter(fn (HeatmapArea $area): bool => $area->isCurrentlyComputed())
            ->take(5)
            ->values();

        $tagalog = str_contains($language, 'Tagalog');
        $tool = [
            'tool' => 'climate_risk',
            'success' => $current->isNotEmpty(),
            'status' => $current->isNotEmpty() ? 'current' : 'no_data',
            'source' => 'iClimate Heat Map Risk Records',
            'farm_context' => $farmContext->toArray(),
            'data' => $current->map(fn (HeatmapArea $area): array => [
                'barangay' => $area->barangay,
                'risk_level' => $area->risk_level,
                'risk_type' => $area->risk_type ?: 'general conditions',
                'risk_score' => $area->risk_score,
                'predicted_yield' => $area->predicted_yield,
                'rainfall_status' => $area->rainfall_status,
                'planting_advisory' => $area->planting_advisory,
                'irrigation_recommendation' => $area->irrigation_recommendation,
                'risk_computed_at' => $area->risk_computed_at?->toIso8601String(),
            ])->all(),
        ];

        if (! $tool['success']) {
            Log::warning('Climora climate_risk: no current risk rows available.');
        }

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $user, $tagalog) {
            if (! $tool['success']) {
                return ClimateRiskReplies::noData($language, forBarangay: false);
            }

            $first = $tool['data'][0];
            $updated = $this->formatUpdatedAt($first['risk_computed_at'] ?? null, $tagalog);
            $updatedSuffix = $updated ? ' '.$updated : '';

            if ($user->role === User::ROLE_FARMER) {
                return $this->translate(
                    $language,
                    $first['barangay'].' has '.$first['risk_level'].' weather risk for '.$first['risk_type'].'. This may affect rice fields there.'.$updatedSuffix,
                    'May '.$first['risk_level'].' na panganib sa panahon sa '.$first['barangay'].' para sa '.$first['risk_type'].'. Maaari itong makaapekto sa mga palayan doon.'.$updatedSuffix
                );
            }

            return $this->translate(
                $language,
                $first['barangay'].' is marked '.$first['risk_level'].' risk for '.$first['risk_type'].' in the iClimate heat map. Planting guidance: '.$first['planting_advisory'].$updatedSuffix,
                'Ang '.$first['barangay'].' ay naka-markang '.$first['risk_level'].' risk para sa '.$first['risk_type'].' sa iClimate heat map. Gabay sa pagtatanim: '.$first['planting_advisory'].$updatedSuffix
            );
        });

        if ($farmContext->isFarmer && $tool['success']) {
            $first = $tool['data'][0] ?? [];
            $answer = $this->appendWhatToDo($answer, $farmContext, [], [
                'risk_level' => $first['risk_level'] ?? null,
                'risk_type' => $first['risk_type'] ?? null,
            ], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'climate_risk', 'iClimate Risk Records', 'iClimate Heat Map', $language, $memory, [
            'tool_result' => $tool,
            'warnings' => $tool['data'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function climateRiskForBarangay(User $user, string $question, string $language, array $memory, string $barangay, FarmerChatContext $farmContext): array
    {
        $row = HeatmapArea::query()
            ->computed()
            ->where('barangay', 'like', '%'.$barangay.'%')
            ->orderByDesc('risk_score')
            ->first();

        $liveDetail = null;
        try {
            $liveDetail = $this->riskMapService->detailFor($barangay);
        } catch (Throwable $exception) {
            Log::warning('Climora climate_risk: live risk map lookup failed; falling back to stored HeatmapArea only.', [
                'barangay' => $barangay,
                'message' => $exception->getMessage(),
            ]);
        }
        $status = ClimateRiskReplies::status($row, $liveDetail);
        $tagalog = str_contains($language, 'Tagalog');

        if ($status !== ClimateRiskReplies::CURRENT) {
            Log::warning('Climora climate_risk: barangay risk status is '.$status.'.', ['barangay' => $barangay]);
        }

        $tool = [
            'tool' => 'climate_risk',
            'success' => $status !== ClimateRiskReplies::NO_DATA,
            'status' => $status,
            'source' => 'iClimate Heat Map Risk Records',
            'farm_context' => $farmContext->toArray(),
            'data' => $row ? [[
                'barangay' => $row->barangay,
                'risk_level' => $row->risk_level,
                'risk_type' => $row->risk_type ?: 'general conditions',
                'risk_score' => $row->risk_score,
                'predicted_yield' => $row->predicted_yield,
                'rainfall_status' => $row->rainfall_status,
                'planting_advisory' => $row->planting_advisory,
                'irrigation_recommendation' => $row->irrigation_recommendation,
                'risk_computed_at' => $row->risk_computed_at?->toIso8601String(),
            ]] : [],
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $status, $row, $tagalog) {
            if ($status === ClimateRiskReplies::NO_DATA || $row === null) {
                $note = $tagalog ? ' Nagre-refresh ang risk map tuwing 30 minuto.' : ' Risk map refreshes every 30 minutes.';

                return ClimateRiskReplies::noData($language).$note;
            }

            $updated = $this->formatUpdatedAt($row->risk_computed_at, $tagalog);
            $updatedSuffix = $updated ? ' '.$updated : '';

            if ($status === ClimateRiskReplies::STALE) {
                $date = $row->risk_computed_at?->toFormattedDateString() ?? 'an earlier date';

                return $this->translate(
                    $language,
                    'As of '.$date.', '.$row->barangay.' was marked '.$row->risk_level.' risk for '.($row->risk_type ?: 'general conditions').'. This is from an earlier computation, and current conditions are unknown — this may not reflect current conditions (risk map refreshes every 30 minutes).',
                    'Noong '.$date.', ang '.$row->barangay.' ay naka-markang '.$row->risk_level.' risk para sa '.($row->risk_type ?: 'general conditions').'. Mula ito sa nakaraang kalkulasyon, hindi alam ang kasalukuyang kondisyon — maaaring hindi na ito reflektibo sa kasalukuyan (nagre-refresh ang risk map tuwing 30 minuto).'
                );
            }

            return $this->translate(
                $language,
                $row->barangay.' is marked '.$row->risk_level.' risk for '.($row->risk_type ?: 'general conditions').' in the iClimate heat map. Planting guidance: '.$row->planting_advisory.$updatedSuffix,
                'Ang '.$row->barangay.' ay naka-markang '.$row->risk_level.' risk para sa '.($row->risk_type ?: 'general conditions').' sa iClimate heat map. Gabay sa pagtatanim: '.$row->planting_advisory.$updatedSuffix
            );
        });

        if ($farmContext->isFarmer) {
            $answer = $this->appendWhatToDo($answer, $farmContext, [], $row && $status === ClimateRiskReplies::CURRENT ? [
                'risk_level' => $row->risk_level,
                'risk_type' => $row->risk_type,
            ] : [], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'climate_risk', 'iClimate Risk Records', 'iClimate Heat Map', $language, $memory, [
            'tool_result' => $tool,
            'warnings' => $tool['data'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function advisories(User $user, string $question, string $language, array $memory, FarmerChatContext $farmContext): array
    {
        $barangay = $user->role === User::ROLE_FARMER ? $farmContext->barangay : ($user->barangay ?? $user->farmerProfile?->barangay);
        $advisories = $this->activeAdvisoriesFor($barangay);
        $typhoonEvent = $this->typhoonEvent();
        $tagalog = str_contains($language, 'Tagalog');

        $safetyResponse = null;
        if ($typhoonEvent !== null && \App\Support\SchemaCache::hasTable('typhoon_safety_responses')) {
            $safetyResponse = TyphoonSafetyResponse::query()
                ->where('user_id', $user->id)
                ->where('event_key', $typhoonEvent['key'])
                ->first();
        }

        $tool = [
            'tool' => 'weather_advisory',
            'success' => true,
            'source' => 'iClimate Planting Advisories',
            'farm_context' => $farmContext->toArray(),
            'typhoon_event' => $typhoonEvent,
            'typhoon_safety_status' => $safetyResponse?->status,
            'data' => $advisories->map(fn (PlantingAdvisory $advisory): array => [
                'title' => $advisory->title,
                'type' => $advisory->typeLabel(),
                'severity' => $advisory->severityLabel(),
                'target' => $advisory->targetLabel(),
                'summary' => $advisory->summary ?: $advisory->message ?: $advisory->content,
                'recommended_action' => $advisory->recommended_action,
                'source' => $advisory->plainSourceLabel(),
                'published_at' => $advisory->published_at?->toIso8601String(),
                'valid_until' => $advisory->valid_until?->toDateString(),
            ])->all(),
        ];

        $asksTyphoon = (bool) preg_match('/\b(typhoon|bagyo|storm|cyclone|signal)\b/i', $question);

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language, $user, $typhoonEvent, $safetyResponse, $asksTyphoon, $tagalog) {
            if ($typhoonEvent !== null) {
                $safetyNote = '';
                if ($safetyResponse !== null) {
                    $safetyNote = $tagalog
                        ? ' Ang iyong Typhoon Safety status: '.$safetyResponse->status.'.'
                        : ' Your Typhoon Safety status: '.$safetyResponse->status.'.';
                } elseif ($user->role === User::ROLE_FARMER) {
                    $safetyNote = $tagalog
                        ? ' I-update ang iyong Typhoon Safety status sa dashboard.'
                        : ' Please update your Typhoon Safety status on your dashboard.';
                }

                return $this->translate(
                    $language,
                    'Typhoon alert: '.$typhoonEvent['title'].' ('.$typhoonEvent['severity'].'). '.($typhoonEvent['reason'] ?: '').$safetyNote,
                    'Babala ng bagyo: '.$typhoonEvent['title'].' ('.$typhoonEvent['severity'].'). '.($typhoonEvent['reason'] ?: '').$safetyNote
                );
            }

            if ($tool['data'] === []) {
                if ($asksTyphoon) {
                    return $this->translate(
                        $language,
                        'There is no active typhoon advisory for Lian right now. PAGASA and local advisories refresh every 15 minutes.',
                        'Walang aktibong babala ng bagyo para sa Lian ngayon. Nagre-refresh ang PAGASA at lokal na abiso tuwing 15 minuto.'
                    );
                }

                return $this->translate(
                    $language,
                    'There are no active iClimate advisories for your area right now (advisories refresh every 15 minutes).',
                    'Walang active iClimate advisories para sa lugar mo ngayon (nagre-refresh ang mga abiso tuwing 15 minuto).'
                );
            }

            $first = $tool['data'][0];
            $updated = $this->formatUpdatedAt($first['published_at'] ?? null, $tagalog);
            $updatedSuffix = $updated ? ' '.$updated : '';

            if ($user->role === User::ROLE_FARMER) {
                $action = trim((string) ($first['recommended_action'] ?? ''));
                $summary = trim((string) ($first['summary'] ?? ''));

                return $this->translate(
                    $language,
                    ($asksTyphoon ? 'No active typhoon alert. Latest advisory: ' : 'Advisory: ').$first['title'].'. '.($action !== '' ? $action : ($summary !== '' ? $summary : 'Follow the guidance for your area.')).$updatedSuffix,
                    ($asksTyphoon ? 'Walang aktibong babala ng bagyo. Pinakabagong abiso: ' : 'Abiso: ').$first['title'].'. '.($action !== '' ? $action : ($summary !== '' ? $summary : 'Sundin ang gabay para sa lugar mo.')).$updatedSuffix
                );
            }

            return $this->translate($language, 'The latest active advisory is '.$first['title'].'. '.$first['summary'].$updatedSuffix, 'Ang latest active advisory ay '.$first['title'].'. '.$first['summary'].$updatedSuffix);
        });

        if ($farmContext->isFarmer) {
            $answer = $this->appendWhatToDo($answer, $farmContext, [], [], $tagalog);
            $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);
        }

        return $this->textResult($answer, 'weather_advisory', 'iClimate Advisories', 'iClimate Planting Advisories', $language, $memory, [
            'tool_result' => $tool,
            'warnings' => $tool['data'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function clarification(string $language, array $memory, string $pendingIntent, string $english, string $tagalog): array
    {
        return $this->textResult($this->translate($language, $english, $tagalog), $pendingIntent, 'Clarification', 'Climora AI', $language, [
            ...$memory,
            'pending_intent' => $pendingIntent,
        ], [
            'confidence_score' => 80,
            'prediction_result' => [
                'requires_clarification' => true,
                'missing' => [$pendingIntent === 'unknown_prediction' ? 'prediction_type' : 'area'],
                'pending_intent' => $pendingIntent,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolFailure(string $intent, string $language, string $message): array
    {
        return $this->textResult(
            $this->translate($language, 'I could not complete that trusted iClimate lookup right now. Please try again in a moment.', 'Hindi ko matapos ang trusted iClimate lookup ngayon. Subukan ulit mamaya.'),
            $intent,
            'Tool Failure',
            'Climora AI',
            $language,
            [],
            [
                'confidence_score' => 0,
                'prediction_result' => ['error' => $message],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $tool
     */
    private function groundedOrFallback(User $user, string $question, string $language, array $tool, array $memory, callable $fallback): string
    {
        // iClimate can already write this answer from its own data. Sending it to
        // Groq to re-word it only adds seconds, so it is returned directly unless
        // re-wording is explicitly switched on (AI_GROQ_PHRASING).
        $direct = (string) $fallback();

        if ($direct !== '' && ! config('ai.groq.phrase_system_answers', false)) {
            return $direct;
        }

        // A climate-risk answer that is not CURRENT carries required wording (no rating / earlier rating). Re-worded
        // AI text must never replace it, or it could drop the qualifier or name a level the data does not have.
        if ($direct !== '' && ($tool['tool'] ?? null) === 'climate_risk' && ($tool['status'] ?? null) !== ClimateRiskReplies::CURRENT) {
            return $direct;
        }

        return $this->groq->groundedResponse($user, $question, $language, $tool, $memory) ?: $direct;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function textResult(string $answer, string $intent, string $sourceType, string $sourceName, string $language, array $memory, array $metadata = []): array
    {
        return [
            'intent' => $intent,
            'answer' => $answer,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'source_url' => null,
            'source_credit' => 'Source: '.$sourceName,
            'language' => $language,
            'memory' => $memory,
            'prediction_result' => $metadata['prediction_result'] ?? Arr::get($metadata, 'tool_result'),
            'weather_prediction' => $metadata['weather_prediction'] ?? null,
            'rice_yield_prediction' => $metadata['rice_yield_prediction'] ?? null,
            'planting_recommendation' => $metadata['planting_recommendation'] ?? null,
            'irrigation_recommendation' => $metadata['irrigation_recommendation'] ?? null,
            'warnings' => $metadata['warnings'] ?? [],
            'explanation' => $answer,
            'confidence_score' => (float) ($metadata['confidence_score'] ?? 80),
            'response_time_ms' => 0,
        ];
    }

    private function conversationMemory(User $user): array
    {
        return $this->context->recent($user);
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function hasArea(array $entities): bool
    {
        return isset($entities['area']) && is_numeric($entities['area']) && (float) $entities['area'] > 0;
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function targetMonth(array $entities): CarbonImmutable
    {
        if (($entities['target_period'] ?? null) === 'next_month') {
            return \App\Support\BusinessClock::now()->toImmutable()->addMonthNoOverflow()->startOfMonth();
        }

        if (isset($entities['target_date']) && is_string($entities['target_date'])) {
            try {
                return CarbonImmutable::parse($entities['target_date'])->startOfMonth();
            } catch (Throwable) {
                return \App\Support\BusinessClock::now()->toImmutable()->addMonthNoOverflow()->startOfMonth();
            }
        }

        return \App\Support\BusinessClock::now()->toImmutable()->addMonthNoOverflow()->startOfMonth();
    }

    private function translate(string $language, string $english, string $tagalog): string
    {
        return str_contains($language, 'Tagalog') ? $tagalog : $english;
    }

    private function isHarvestTimingQuestion(string $question): bool
    {
        $text = mb_strtolower(trim($question));

        return (bool) preg_match('/\b(when\s+(to|should\s+i|will\s+i|can\s+i)\s+harvest|expected\s+harvest\s+date|harvest\s+date|kailan\s+(ang\s+)?(ani|anihan|mag-aani|aani))\b/iu', $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestTiming(FarmerChatContext $farmContext, string $language, array $memory): array
    {
        $tagalog = str_contains($language, 'Tagalog');
        $farmLabel = $farmContext->activeFarm?->farm_name ?: ($tagalog ? 'iyong bukid' : 'your farm');

        if ($farmContext->expectedHarvestDate !== null) {
            $date = $farmContext->expectedHarvestDate->format('M j, Y');
            $stage = $farmContext->stageLabel($tagalog);
            $variety = $farmContext->variety ?: ($tagalog ? 'nakatalang binhi' : 'recorded variety');
            $planted = $farmContext->plantingDate?->format('M j, Y');
            $window = ($farmContext->harvestWindowStart && $farmContext->harvestWindowEnd)
                ? $farmContext->harvestWindowStart->format('M j').'–'.$farmContext->harvestWindowEnd->format('M j, Y')
                : null;

            $answer = $tagalog
                ? "Para sa {$farmLabel} ({$variety}".($planted ? ", itinanim noong {$planted}" : '')."), ang inaasahang petsa ng ani ay {$date}".($window ? " (window: {$window})" : '').". Kasalukuyang yugto ng palay: {$stage}."
                : "For {$farmLabel} ({$variety}".($planted ? ", planted {$planted}" : '')."), expected harvest is {$date}".($window ? " (window: {$window})" : '').". Current crop stage: {$stage}.";
        } else {
            $answer = $tagalog
                ? "Wala pang nakatalang petsa ng pagtanim para sa {$farmLabel}, kaya hindi pa makalkula ang petsa ng ani. Magdagdag ng planting schedule sa My Farm."
                : "No planting date is recorded yet for {$farmLabel}, so an expected harvest date cannot be estimated yet. Add a planting schedule in My Farm.";
        }

        $answer = $this->appendWhatToDo($answer, $farmContext, [], [], $tagalog);
        $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);

        return $this->textResult($answer, 'harvest_timing', 'Variety Harvest Estimator', 'iClimate Farm Schedule', $language, $memory, [
            'tool_result' => [
                'tool' => 'harvest_timing',
                'success' => $farmContext->expectedHarvestDate !== null,
                'farm_context' => $farmContext->toArray(),
            ],
        ]);
    }

    /**
     * Read stored yield_predictions (warm-farmer-yields) and apply YieldFarmTypeAdjustment.
     *
     * @return array<string, mixed>|null
     */
    private function storedYieldAnswer(FarmerChatContext $farmContext, string $language, array $memory): ?array
    {
        $store = app(YieldPredictionStore::class);
        if (! $store->available()) {
            return null;
        }

        $seasonStart = $store->seasonStart();
        $row = null;
        if ($farmContext->activeFarm !== null) {
            $row = YieldPrediction::query()
                ->where('scope', YieldPrediction::SCOPE_FARM)
                ->where('farm_id', $farmContext->activeFarm->id)
                ->whereDate('season_start', $seasonStart)
                ->when(\App\Support\SchemaCache::hasColumn('yield_predictions', 'period'), fn ($q) => $q->where('period', 'season'))
                ->first();
        }

        if ($row === null && $farmContext->barangay !== null) {
            $row = YieldPrediction::query()
                ->where('scope', YieldPrediction::SCOPE_BARANGAY)
                ->where('barangay', $farmContext->barangay)
                ->whereDate('season_start', $seasonStart)
                ->when(\App\Support\SchemaCache::hasColumn('yield_predictions', 'period'), fn ($q) => $q->where('period', 'season'))
                ->first();
        }

        if ($row === null || ! is_numeric($row->predicted_yield)) {
            return null;
        }

        if (! \App\Services\Prediction\YieldModelGate::showsStored($row->basis, $row->model_input)) {
            return null;
        }

        $tagalog = str_contains($language, 'Tagalog');
        $season = $row->season ?: RiceSeason::window($seasonStart)['season'];
        $adjustment = app(YieldFarmTypeAdjustment::class)->forRequest($farmContext->farmType, $season, (float) $row->predicted_yield);
        $adjustedYield = (float) $adjustment['adjusted_yield'];
        $area = $farmContext->farmArea ?? (is_numeric($row->area_hectares) ? (float) $row->area_hectares : null);
        $totalTons = $area !== null ? round($adjustedYield * $area, 2) : null;

        $updated = $this->formatUpdatedAt($row->generated_at ?? $row->updated_at, $tagalog);
        $farmLabel = $farmContext->activeFarm?->farm_name ?: ($farmContext->barangay ? 'Barangay '.$farmContext->barangay : ($tagalog ? 'iyong bukid' : 'your farm'));
        $farmTypeLabel = $farmContext->farmType ?: ($tagalog ? 'Rainfed' : 'Rainfed');

        $answer = $tagalog
            ? "Para sa {$farmLabel} ({$farmTypeLabel}), ang inaasahang ani ay ".number_format($adjustedYield, 2).' tonelada bawat ektarya'.($totalTons !== null ? ' (mga '.number_format($totalTons, 2).' tonelada kabuuan)' : '').'.'.($updated ? ' '.$updated : '')
            : "For {$farmLabel} ({$farmTypeLabel}), your expected yield is ".number_format($adjustedYield, 2).' tons per hectare'.($totalTons !== null ? ' (about '.number_format($totalTons, 2).' tons total)' : '').'.'.($updated ? ' '.$updated : '');

        if ($adjustment['source'] !== 'none' && filled($adjustment['basis'])) {
            $answer .= ' '.$adjustment['basis'].'.';
        }

        $answer = $this->appendWhatToDo($answer, $farmContext, [], [], $tagalog);
        $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);

        return $this->textResult($answer, IntentClassifierService::RICE_YIELD_PREDICTION, 'Stored Yield Prediction', 'iClimate Stored Yield', $language, $memory, [
            'rice_yield_prediction' => [
                'predicted_yield' => $adjustedYield,
                'model_value' => $adjustment['model_value'],
                'farm_type_factor' => $adjustment['factor'],
                'estimated_total_production_tons' => $totalTons,
                'farm_area_hectares' => $area,
                'farm_type' => $farmTypeLabel,
                'season' => $season,
                'updated_at' => ($row->generated_at ?? $row->updated_at)?->toIso8601String(),
            ],
            'tool_result' => [
                'tool' => 'stored_yield_prediction',
                'success' => true,
                'farm_context' => $farmContext->toArray(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function yieldUnavailableFallback(FarmerChatContext $farmContext, string $language, array $memory): array
    {
        $tagalog = str_contains($language, 'Tagalog');
        $parts = [];
        $parts[] = $tagalog
            ? 'Hindi pa available ngayon ang inaasahang ani dahil hindi maabot ang prediction service o wala pang naka-save na datos (nagre-refresh ang ani tuwing 3 oras).'
            : 'Expected yield is not available right now because the prediction service is unavailable or no stored estimate exists yet (yield refreshes every 3 hours).';

        if ($farmContext->expectedHarvestDate !== null) {
            $parts[] = $tagalog
                ? 'Inaasahang petsa ng ani: '.$farmContext->expectedHarvestDate->format('M j, Y').' (yugto: '.$farmContext->stageLabel(true).').'
                : 'Expected harvest date: '.$farmContext->expectedHarvestDate->format('M j, Y').' (stage: '.$farmContext->stageLabel(false).').';
        }

        $answer = $this->appendWhatToDo(implode(' ', $parts), $farmContext, [], [], $tagalog);
        $answer = $this->appendIncompleteProfileLine($answer, $farmContext, $tagalog);

        return $this->textResult($answer, IntentClassifierService::RICE_YIELD_PREDICTION, 'Prediction Status', 'iClimate Stored Yield', $language, $memory, [
            'confidence_score' => 0,
            'prediction_result' => ['unavailable' => true, 'refresh_schedule' => 'every 3 hours'],
        ]);
    }

    /**
     * @return Collection<int, PlantingAdvisory>
     */
    private function activeAdvisoriesFor(?string $barangay): Collection
    {
        $key = $barangay ?: '__all__';

        return $this->advisoriesMemo[$key] ??= rescue(
            fn () => PlantingAdvisory::query()->active()->forBarangay($barangay)->latest('published_at')->take(5)->get(),
            collect(),
            false
        );
    }

    private function typhoonEvent(): ?array
    {
        if (! $this->typhoonEventLoaded) {
            $this->typhoonEventLoaded = true;
            $this->typhoonEventMemo = rescue(fn () => app(TyphoonSafetyService::class)->activeEvent(), null, false);
        }

        return $this->typhoonEventMemo;
    }

    private function formatUpdatedAt(mixed $timestamp, bool $tagalog): ?string
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        try {
            $dt = $timestamp instanceof CarbonImmutable
                ? $timestamp
                : CarbonImmutable::parse((string) $timestamp);
            $formatted = $dt->timezone(BusinessClock::timezone())->format('M j, g:i A');

            return $tagalog ? "(Na-update: {$formatted})" : "(Updated {$formatted})";
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $weatherCtx
     * @param  array<string, mixed>  $riskCtx
     */
    private function appendWhatToDo(string $answer, FarmerChatContext $farmContext, array $weatherCtx, array $riskCtx, bool $tagalog): string
    {
        $advisories = $this->activeAdvisoriesFor($farmContext->barangay);
        $typhoonEvent = $this->typhoonEvent();

        if ($riskCtx === [] && $farmContext->barangay !== null) {
            $area = rescue(fn () => HeatmapArea::query()->computed()->where('barangay', $farmContext->barangay)->first(), null, false);
            if ($area !== null && $area->isCurrentlyComputed()) {
                $riskCtx = ['risk_level' => $area->risk_level, 'risk_type' => $area->risk_type];
            }
        }

        return FarmerActionBuilder::append($answer, $farmContext, $weatherCtx, $riskCtx, $advisories, $typhoonEvent, $tagalog);
    }

    private function appendIncompleteProfileLine(string $answer, FarmerChatContext $farmContext, bool $tagalog): string
    {
        if (! $farmContext->isFarmer || ! $farmContext->incompleteProfile) {
            return $answer;
        }

        $url = '/farmer/complete-profile';
        $line = $tagalog
            ? "Paalala: Kumpletuhin ang iyong farm profile para sa payo sa barangay mo: {$url}"
            : "Tip: Complete your farm profile for barangay-specific advice: {$url}";

        return rtrim($answer)."\n\n".$line;
    }
}
