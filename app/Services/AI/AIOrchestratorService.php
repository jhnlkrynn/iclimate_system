<?php

namespace App\Services\AI;

use App\Models\AIChat;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\HeatmapRiskService;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\PredictionService;
use App\Services\Weather\FarmerDashboardWeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIOrchestratorService
{
    public function __construct(
        private readonly IntentDetectionService $languageDetector,
        private readonly IntentClassifierService $classifier,
        private readonly PredictionService $predictionService,
        private readonly FarmerDashboardWeatherService $weatherService,
        private readonly MonthlyWeatherRandomForest $weatherForest,
        private readonly ChatbotResponseService $chatbotResponses,
        private readonly GroqChatService $groq,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function answer(User $user, string $question): array
    {
        $startedAt = microtime(true);
        $memory = $this->conversationMemory($user);
        $language = $this->languageDetector->detectLanguage($question, $memory);
        $classification = $this->classifier->classify($user, $question, $language, $memory);
        $intent = $classification['intent'];
        $entities = $classification['entities'];
        $questionText = str($question)->lower()->toString();

        if (! $this->groq->available() && str($questionText)->contains(['paano kaba tatalino', 'paano ka ba tatalino'])) {
            return $this->textResult(
                $this->translate(
                    $language,
                    'I can help with iClimate only: weather prediction, rice yield prediction, planting and irrigation advice, climate risk, fertilizer, pest and disease guidance, soil information, barangay information, announcements, notifications, profile, calendar, reports, and how to use the system.',
                    'Makakatulong ako tungkol sa iClimate lang: weather prediction, rice yield prediction, planting at irrigation advice, climate risk, fertilizer, pest at disease, soil, barangay information, announcements, notifications, profile, calendar, reports, at kung paano gamitin ang system.'
                ),
                $intent,
                'System Scope',
                'Climora AI',
                $language,
                $memory,
                ['confidence_score' => 80]
            );
        }

        if (($pending = (string) data_get($memory, 'pending_intent')) !== '' && $this->hasArea($entities)) {
            $intent = $pending;
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
            if ($latestPrediction = $this->chatbotResponses->latestPredictionAnswer($user, $question, $language)) {
                $result = $latestPrediction;
            } elseif ($this->shouldUseRuleAnswer($intent, $question) && $ruleAnswer = $this->chatbotResponses->answer($user, $question, $language, $intent)) {
                $result = $this->textResult(
                    (string) $ruleAnswer['answer'],
                    (string) ($ruleAnswer['intent'] ?? $intent),
                    'Chatbot Knowledge Base',
                    (string) $ruleAnswer['source_name'],
                    $language,
                    $memory,
                    [
                        'confidence_score' => $ruleAnswer['confidence'],
                        'prediction_result' => [
                            'chatbot_response_id' => $ruleAnswer['chatbot_response_id'],
                            'matched_keywords' => $ruleAnswer['matched_keywords'],
                            'answer_source' => 'Answered from the managed chatbot_responses table.',
                        ],
                    ],
                );
            } else {
                $result = match ($intent) {
                    IntentClassifierService::CURRENT_WEATHER => $this->currentWeather($user, $question, $language, $memory),
                    IntentClassifierService::WEATHER_FORECAST => $this->weatherForecast($user, $question, $language, $memory, $entities),
                    IntentClassifierService::WEATHER_PREDICTION => $this->weatherPrediction($user, $question, $language, $memory, $entities),
                    IntentClassifierService::RICE_YIELD_PREDICTION,
                    IntentClassifierService::PLANTING_RECOMMENDATION,
                    IntentClassifierService::IRRIGATION_RECOMMENDATION => $this->modelBackedPrediction($user, $question, $language, $memory, $intent, $entities),
                    IntentClassifierService::CLIMATE_RISK => $this->climateRisk($user, $question, $language, $memory, $entities),
                    IntentClassifierService::WEATHER_ADVISORY => $this->advisories($user, $question, $language, $memory),
                    IntentClassifierService::SYSTEM_HELP,
                    IntentClassifierService::PROFILE_ACCOUNT,
                    IntentClassifierService::COMMUNITY,
                    IntentClassifierService::REPORTS,
                    IntentClassifierService::GENERAL_CHAT,
                    IntentClassifierService::UNKNOWN => $this->predictionService->answer($user, $question),
                    default => $this->predictionService->answer($user, $question),
                };
            }
        } catch (Throwable $exception) {
            Log::warning('AI orchestrator route failed.', [
                'intent' => $intent,
                'message' => $exception->getMessage(),
            ]);

            $result = $this->toolFailure($intent, $language, $exception->getMessage());
        }

        $result['language'] = $result['language'] ?? $language;
        $result['memory'] = [
            ...($result['memory'] ?? $memory),
            'orchestrator' => [
                'intent' => $intent,
                'classifier_source' => $classification['source'],
                'classifier_confidence' => $classification['confidence'],
                'entities' => $entities,
            ],
        ];

        $result = $this->prepareVisibleResponse($user, $question, $language, $result);
        $result['response_time_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentWeather(User $user, string $question, string $language, array $memory): array
    {
        $weather = $this->weatherService->current();
        $tool = [
            'tool' => 'current_weather',
            'success' => (bool) ($weather['success'] ?? false),
            'source' => $weather['source'] ?? $weather['provider'] ?? 'Weather service',
            'location' => $weather['location'] ?? ['name' => 'Lian', 'province' => 'Batangas'],
            'data' => [
                'condition' => data_get($weather, 'current.condition'),
                'temperature_c' => data_get($weather, 'current.temperature'),
                'feels_like_c' => data_get($weather, 'current.feels_like'),
                'humidity_percent' => data_get($weather, 'current.humidity'),
                'rainfall_today_mm' => data_get($weather, 'today.rainfall'),
                'wind_kph' => data_get($weather, 'current.wind_speed'),
                'provider_updated_at' => $weather['fetched_at_label'] ?? null,
                'stale' => (bool) ($weather['stale'] ?? false),
            ],
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language) {
            if (! $tool['success']) {
                return $this->translate($language, 'Current weather data is temporarily unavailable. Please try refreshing again in a moment.', 'Hindi available ngayon ang current weather data. Subukan ulit mag-refresh mamaya.');
            }

            $data = $tool['data'];

            return $this->translate(
                $language,
                'According to '.$tool['source'].', the current weather in Lian, Batangas is '.$data['condition'].' with '.$data['temperature_c'].' C, humidity '.$data['humidity_percent'].'%, and wind '.$data['wind_kph'].' km/h.',
                'Ayon sa '.$tool['source'].', ang kasalukuyang panahon sa Lian, Batangas ay '.$data['condition'].' na may '.$data['temperature_c'].' C, humidity na '.$data['humidity_percent'].'%, at hangin na '.$data['wind_kph'].' km/h.'
            );
        });

        return $this->textResult($answer, 'current_weather', 'Live Weather', (string) $tool['source'], $language, $memory, [
            'tool_result' => $tool,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function weatherForecast(User $user, string $question, string $language, array $memory, array $entities): array
    {
        $weather = $this->weatherService->current();
        $forecast = (array) ($weather['forecast'] ?? []);
        $target = $entities['target_period'] ?? null;
        $selected = $target === 'tomorrow' && isset($forecast[1]) ? [$forecast[1]] : $forecast;
        $tool = [
            'tool' => 'weather_forecast',
            'success' => (bool) ($weather['success'] ?? false) && $forecast !== [],
            'source' => $weather['source'] ?? $weather['provider'] ?? 'Weather forecast service',
            'location' => $weather['location'] ?? ['name' => 'Lian', 'province' => 'Batangas'],
            'target_period' => $target ?: 'next_7_days',
            'data' => array_values(array_slice($selected, 0, 7)),
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language) {
            if (! $tool['success']) {
                return $this->translate($language, 'The weather forecast is temporarily unavailable. I will not guess the forecast.', 'Hindi available ngayon ang weather forecast. Hindi ako manghuhula ng forecast.');
            }

            $first = $tool['data'][0] ?? [];

            return $this->translate(
                $language,
                'Based on '.$tool['source'].', '.$first['day'].' is expected to be '.$first['condition'].' with about '.$first['rainfall'].' mm rainfall and '.$first['precipitation_probability'].'% rain chance.',
                'Batay sa '.$tool['source'].', sa '.$first['day'].' ay inaasahang '.$first['condition'].' na may humigit-kumulang '.$first['rainfall'].' mm ulan at '.$first['precipitation_probability'].'% chance of rain.'
            );
        });

        return $this->textResult($answer, 'weather_forecast', 'Live Weather Forecast', (string) $tool['source'], $language, $memory, [
            'tool_result' => $tool,
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function weatherPrediction(User $user, string $question, string $language, array $memory, array $entities): array
    {
        $targetMonth = $this->targetMonth($entities);
        $prediction = $this->weatherForest->predict($targetMonth);
        $tool = [
            'tool' => 'weather_prediction',
            'success' => (bool) ($prediction['ready'] ?? false),
            'source' => $prediction['source_name'] ?? 'iClimate monthly Random Forest model',
            'model_used' => 'storage/models/weather_model.pkl / MonthlyWeatherRandomForest',
            'target_month' => $targetMonth->format('F Y'),
            'data' => $prediction['predictions'] ?? [],
            'confidence' => $prediction['confidence'] ?? null,
            'message' => $prediction['message'] ?? null,
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language) {
            if (! $tool['success']) {
                return $this->translate($language, 'I could not generate the iClimate monthly weather model prediction right now. '.$tool['message'], 'Hindi ko magenerate ngayon ang iClimate monthly weather model prediction. '.$tool['message']);
            }

            $data = $tool['data'];

            return $this->translate(
                $language,
                'Based on the iClimate monthly Random Forest model, the estimate for '.$tool['target_month'].' is '.$data['rainfall'].' mm rainfall, '.$data['temperature'].' C temperature, '.$data['humidity'].'% humidity, and '.$data['wind_speed'].' km/h wind.',
                'Batay sa iClimate monthly Random Forest model, ang estimate para sa '.$tool['target_month'].' ay '.$data['rainfall'].' mm ulan, '.$data['temperature'].' C temperatura, '.$data['humidity'].'% humidity, at '.$data['wind_speed'].' km/h hangin.'
            );
        });

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
    private function modelBackedPrediction(User $user, string $question, string $language, array $memory, string $intent, array $entities): array
    {
        if (
            $intent === IntentClassifierService::RICE_YIELD_PREDICTION
            && ! $this->hasArea($entities)
            && ! $user->farmerProfile?->farm_area
            && ! $this->hasEnoughYieldContext($question, $entities)
        ) {
            return $this->clarification($language, $memory, 'rice_yield_prediction', 'I can predict your rice yield. What farm area in hectares should I use? For example: 2.5 hectares.', 'Kaya kong i-predict ang rice yield mo. Ilang ektarya ang gagamitin ko? Halimbawa: 2.5 hectares.');
        }

        $modelQuestion = $question;
        if ($intent === IntentClassifierService::RICE_YIELD_PREDICTION && $this->hasArea($entities) && ! str_contains(strtolower($question), 'yield')) {
            $modelQuestion = 'Predict my rice yield for '.$entities['area'].' hectares.';
        }

        $result = $this->predictionService->answer($user, $modelQuestion);
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
    private function climateRisk(User $user, string $question, string $language, array $memory, array $entities): array
    {
        $barangay = (string) ($entities['barangay'] ?? $user->barangay ?? $user->farmerProfile?->barangay ?? '');
        $query = HeatmapArea::query()->whereNotIn('barangay', HeatmapRiskService::PLACEHOLDER_NAMES);
        if ($barangay !== '') {
            $query->where('barangay', 'like', '%'.$barangay.'%');
        }

        $areas = $query->orderByDesc('risk_score')->take($barangay !== '' ? 1 : 5)->get();
        $tool = [
            'tool' => 'climate_risk',
            'success' => $areas->isNotEmpty(),
            'source' => 'iClimate Heat Map Risk Records',
            'data' => $areas->map(fn (HeatmapArea $area): array => [
                'barangay' => $area->barangay,
                'risk_level' => $area->risk_level,
                'risk_type' => $area->risk_type,
                'risk_score' => $area->risk_score,
                'predicted_yield' => $area->predicted_yield,
                'rainfall_status' => $area->rainfall_status,
                'planting_advisory' => $area->planting_advisory,
                'irrigation_recommendation' => $area->irrigation_recommendation,
            ])->all(),
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language) {
            if (! $tool['success']) {
                return $this->translate($language, 'I could not find a current heat-map risk record for that barangay.', 'Wala akong makitang current heat-map risk record para sa barangay na iyon.');
            }

            $first = $tool['data'][0];

            return $this->translate(
                $language,
                $first['barangay'].' is marked '.$first['risk_level'].' risk for '.$first['risk_type'].' in the iClimate heat map. Planting guidance: '.$first['planting_advisory'],
                'Ang '.$first['barangay'].' ay naka-markang '.$first['risk_level'].' risk para sa '.$first['risk_type'].' sa iClimate heat map. Gabay sa pagtatanim: '.$first['planting_advisory']
            );
        });

        return $this->textResult($answer, 'climate_risk', 'iClimate Risk Records', 'iClimate Heat Map', $language, $memory, [
            'tool_result' => $tool,
            'warnings' => $tool['data'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function advisories(User $user, string $question, string $language, array $memory): array
    {
        $barangay = $user->barangay ?? $user->farmerProfile?->barangay;
        $advisories = PlantingAdvisory::query()
            ->active()
            ->forBarangay($barangay)
            ->latest('published_at')
            ->take(5)
            ->get();

        $tool = [
            'tool' => 'weather_advisory',
            'success' => true,
            'source' => 'iClimate Planting Advisories',
            'data' => $advisories->map(fn (PlantingAdvisory $advisory): array => [
                'title' => $advisory->title,
                'type' => $advisory->typeLabel(),
                'severity' => $advisory->severityLabel(),
                'target' => $advisory->targetLabel(),
                'summary' => $advisory->summary ?: $advisory->message ?: $advisory->content,
                'recommended_action' => $advisory->recommended_action,
                'source' => $advisory->sourceLabel(),
                'valid_until' => $advisory->valid_until?->toDateString(),
            ])->all(),
        ];

        $answer = $this->groundedOrFallback($user, $question, $language, $tool, $memory, function () use ($tool, $language) {
            if ($tool['data'] === []) {
                return $this->translate($language, 'There are no active iClimate advisories for your area right now.', 'Walang active iClimate advisories para sa lugar mo ngayon.');
            }

            $first = $tool['data'][0];

            return $this->translate($language, 'The latest active advisory is '.$first['title'].'. '.$first['summary'], 'Ang latest active advisory ay '.$first['title'].'. '.$first['summary']);
        });

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
        return $this->groq->groundedResponse($user, $question, $language, $tool, $memory) ?: $fallback();
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
        $latest = AIChat::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $lastPending = $latest
            ->pluck('memory')
            ->filter(fn ($memory) => is_array($memory) && filled(data_get($memory, 'pending_intent')))
            ->first();

        return [
            'last_intent' => $latest->first()?->intent,
            'pending_intent' => data_get($lastPending, 'pending_intent'),
            'recent_questions' => $latest->pluck('question')->all(),
            'recent_facts' => $latest->pluck('intent')->filter()->take(5)->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function hasArea(array $entities): bool
    {
        return isset($entities['area']) && is_numeric($entities['area']) && (float) $entities['area'] > 0;
    }

    private function shouldUseRuleAnswer(string $intent, string $question): bool
    {
        if ($this->isGeneralFarmingGuideQuestion($question)) {
            return true;
        }

        if (str($question)->lower()->contains([
            'how do you predict',
            'how does iclimate predict',
            'how do you gather',
            'how do you get',
            'how do you collect',
            'weather data source',
            'weather source',
            'paano mo pinipredict',
            'paano mo hinuhulaan',
            'paano hinuhulaan',
        ])) {
            return false;
        }

        if ($this->isStaticFeatureQuestion($question)) {
            return true;
        }

        return in_array($intent, [
            IntentClassifierService::SYSTEM_HELP,
            IntentClassifierService::PROFILE_ACCOUNT,
            IntentClassifierService::COMMUNITY,
            IntentClassifierService::REPORTS,
            IntentClassifierService::GENERAL_CHAT,
            IntentClassifierService::UNKNOWN,
        ], true);
    }

    private function isGeneralFarmingGuideQuestion(string $question): bool
    {
        $text = str($question)->lower();

        if ($text->contains([
            'prediction',
            'predict',
            'forecast',
            'risk',
            'warning',
            'babala',
            'panganib',
            'ngayon',
            'today',
            'tomorrow',
            'this week',
            'next week',
            'current',
            'latest',
            'show',
            'explain the risk',
            'technical',
            'details',
            'detalye',
        ])) {
            return false;
        }

        return $text->contains([
            'paano magtanim',
            'paano mag tanim',
            'paano magtanim ng palay',
            'paano ang pagtatanim',
            'paraan ng pagtatanim',
            'tamang pagtatanim',
            'how to plant',
            'how do i plant',
            'how can i plant',
            'steps to plant',
            'planting guide',
            'rice planting guide',
            'how to grow rice',
        ]);
    }

    private function isStaticFeatureQuestion(string $question): bool
    {
        $text = str($question)->lower()->toString();

        if (str($text)->contains([
            'latest',
            'recent',
            'current',
            'right now',
            'today',
            'tomorrow',
            'this week',
            'next month',
            'predict for',
            'model predict',
            'active advisory',
            'active advisories',
            'risk right now',
        ])) {
            return false;
        }

        return str($text)->contains([
            'where can i view',
            'where do i view',
            'how do i use',
            'how do i get',
            'what does the',
            'what is a',
            'what are',
            'what does',
            'explain',
            'mean',
            'navigation',
            'login',
            'paano gamitin',
            'saan makikita',
            'ano ang ibig sabihin',
            'ano ang',
            'paano gumawa',
        ]);
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function hasEnoughYieldContext(string $question, array $entities): bool
    {
        if (isset($entities['season']) || isset($entities['target_period']) || isset($entities['target_date'])) {
            return true;
        }

        $text = str($question)->lower()->toString();

        if (str($text)->contains([
            'wet season',
            'dry season',
            'tag-ulan',
            'tag-init',
            'rainfall',
            'temperature',
            'humidity',
            'wind',
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december',
        ])) {
            return true;
        }

        return preg_match('/\b\d{4}-\d{1,2}(?:-\d{1,2})?\b/', $question) === 1;
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function targetMonth(array $entities): CarbonImmutable
    {
        if (($entities['target_period'] ?? null) === 'next_month') {
            return CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
        }

        if (isset($entities['target_date']) && is_string($entities['target_date'])) {
            try {
                return CarbonImmutable::parse($entities['target_date'])->startOfMonth();
            } catch (Throwable) {
                return CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
            }
        }

        return CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();
    }

    private function translate(string $language, string $english, string $tagalog): string
    {
        return str_contains($language, 'Tagalog') ? $tagalog : $english;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function prepareVisibleResponse(User $user, string $question, string $language, array $result): array
    {
        $result['prediction_result'] = is_array($result['prediction_result'] ?? null)
            ? [
                ...$result['prediction_result'],
                'suggested_questions' => $this->chatbotResponses->suggestedQuestions($user, $language),
            ]
            : [
                'suggested_questions' => $this->chatbotResponses->suggestedQuestions($user, $language),
            ];

        if ($this->chatbotResponses->wantsTechnicalDetails($question) || ! $this->isPredictionResult($result)) {
            return $result;
        }

        $result['answer'] = $this->shortPredictionAnswer($result, $language);
        $result['explanation'] = $result['answer'];

        if ($user->role !== User::ROLE_IT_EXPERT) {
            $result['prediction_result'] = collect($result['prediction_result'])
                ->except(['input_features', 'api_error', 'conversation_memory', 'model_version', 'feature_order', 'tool_result'])
                ->all();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function isPredictionResult(array $result): bool
    {
        return filled($result['weather_prediction'] ?? null)
            || filled($result['rice_yield_prediction'] ?? null)
            || filled($result['planting_recommendation'] ?? null)
            || filled($result['irrigation_recommendation'] ?? null)
            || filled($result['warnings'] ?? null)
            || in_array($result['source_type'] ?? null, ['Live Weather', 'Live Weather Forecast', 'Trained Model', 'Machine Learning', 'Machine Learning + Generative AI', 'Prediction Records', 'iClimate Risk Records', 'iClimate Advisories'], true);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function shortPredictionAnswer(array $result, string $language): string
    {
        $tagalog = str_contains($language, 'Tagalog') || str_contains($language, 'Mixed');
        $lines = [];

        $weather = (array) ($result['weather_prediction'] ?? []);
        $yield = (array) ($result['rice_yield_prediction'] ?? []);
        $planting = (array) ($result['planting_recommendation'] ?? []);
        $irrigation = (array) ($result['irrigation_recommendation'] ?? []);
        $warnings = collect($result['warnings'] ?? []);

        if ($weather !== []) {
            $condition = data_get($weather, 'predicted_weather') ?: data_get($weather, 'condition');
            $rainfall = data_get($weather, 'rainfall');
            $temperature = data_get($weather, 'temperature');
            $lines[] = $tagalog
                ? 'Panahon: '.($condition ?: 'walang label').($rainfall !== null ? " | Ulan: {$rainfall} mm" : '').($temperature !== null ? " | Temp: {$temperature} C" : '')
                : 'Weather: '.($condition ?: 'not labeled').($rainfall !== null ? " | Rainfall: {$rainfall} mm" : '').($temperature !== null ? " | Temp: {$temperature} C" : '');
        }

        if ($yield !== []) {
            $predictedYield = data_get($yield, 'predicted_yield');
            $total = data_get($yield, 'estimated_total_production_tons');
            $lines[] = $tagalog
                ? 'Rice yield: '.($predictedYield !== null ? number_format((float) $predictedYield, 2).' tons/hectare' : 'walang value').($total !== null ? ' | Total: '.number_format((float) $total, 2).' tons' : '')
                : 'Rice yield: '.($predictedYield !== null ? number_format((float) $predictedYield, 2).' tons/hectare' : 'not available').($total !== null ? ' | Total: '.number_format((float) $total, 2).' tons' : '');
        }

        if ($planting !== []) {
            $lines[] = ($tagalog ? 'Pagtatanim: ' : 'Planting: ').(data_get($planting, 'recommendation') ?: data_get($planting, 'action') ?: ($tagalog ? 'tingnan ang advisory' : 'check advisory'));
        }

        if ($irrigation !== []) {
            $lines[] = ($tagalog ? 'Patubig: ' : 'Irrigation: ').(data_get($irrigation, 'recommendation') ?: ($tagalog ? 'tingnan ang field condition' : 'check field condition'));
        }

        if ($warnings->isNotEmpty()) {
            $lines[] = ($tagalog ? 'Babala: ' : 'Warning: ').$warnings
                ->map(fn ($warning) => data_get($warning, 'title', data_get($warning, 'reason', 'Warning')))
                ->take(3)
                ->implode('; ');
        }

        if ($lines === []) {
            return $tagalog
                ? 'Wala pang prediction record na available para sa tanong na ito. Buksan ang Weather & Yield, Weather Prediction, Heat Map, o Advisories para gumawa o makita ang result.'
                : 'No prediction record is available for that question yet. Open Weather & Yield, Weather Prediction, Heat Map, or Advisories to generate or view a result.';
        }

        $intro = $tagalog ? 'Ito ang available sa iClimate:' : 'Here is what iClimate has available:';
        $note = $tagalog ? 'Ginamit ko lang ang data na nasa system.' : 'I only used data already available in the system.';

        return $intro."\n".implode("\n", $lines)."\n".$note;
    }
}
