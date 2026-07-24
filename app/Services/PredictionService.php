<?php

namespace App\Services;

use App\Models\AIChat;
use App\Models\Announcement;
use App\Models\ClimateRecord;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\AI\GroqChatService;
use App\Services\AI\IntentDetectionService;
use App\Services\AI\KnowledgeBaseService;
use App\Services\AI\RoleAssistantService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use RuntimeException;

class PredictionService
{
    public function __construct(
        private readonly PythonService $python,
        private readonly DecisionSupportService $decisionSupport,
        private readonly IntentDetectionService $intentDetector,
        private readonly KnowledgeBaseService $knowledgeBase,
        private readonly GroqChatService $groqChat,
        private readonly RoleAssistantService $roleAssistant,
    ) {}

    public function answer(User $user, string $question): array
    {
        $startedAt = microtime(true);
        $memory = $this->conversationMemory($user);
        $language = $this->intentDetector->detectLanguage($question);
        $intentResult = $this->intentDetector->detect($question, $memory);
        $intent = $intentResult['intent'];

        if (! $intentResult['requires_prediction']) {
            return $this->answerWithoutPrediction($user, $question, $intent, $language, $memory, $intentResult, $startedAt);
        }

        $context = $this->contextFor($user, $question, $memory);
        $pythonResult = null;
        $apiError = null;

        try {
            $pythonResult = $this->python->farmingAssistant([
                'intent' => $intent,
                'question' => $question,
                'features' => $context,
            ]);
        } catch (RuntimeException $exception) {
            $apiError = $exception->getMessage();
        }

        $weather = $pythonResult['weather_prediction'] ?? $this->fallbackWeather($context);
        $yield = $pythonResult['rice_yield_prediction'] ?? null;
        $yield = is_array($yield) && Arr::get($yield, 'predicted_yield') !== null
            ? $yield
            : $this->fallbackYield($context, is_array($yield) ? $yield : []);
        $calibration = $this->calibrateYield($yield, $context);
        $yield = $calibration['yield'];
        $predictedYield = Arr::get($yield, 'predicted_yield');

        $decision = $this->decisionSupport->evaluate([
            ...$context,
            'predicted_yield' => $predictedYield,
            'predicted_weather' => Arr::get($weather, 'predicted_weather'),
        ]);

        $warnings = $this->normalizeWarnings($pythonResult['warnings'] ?? [], $decision);
        $quality = $this->predictionQuality($context, $calibration, $pythonResult, $apiError);
        $insights = $this->insights($intent, $context, $yield, $decision, $warnings, $quality, $language);
        $explanation = $this->explanation($weather, $yield, $insights, $apiError, $language);
        $confidence = $this->adjustedConfidence($pythonResult, $decision, $quality);
        $answer = $this->composeAnswer($intent, $weather, $yield, $decision, $warnings, $insights, $explanation, 'Machine Learning + Decision Support', $confidence, $language);
        $groqFallback = $this->groqPredictionFallback($user, $question, $intent, $language, $memory, $context, $weather, $yield, $decision, $warnings, $quality, $apiError, $pythonResult);

        if ($groqFallback) {
            $answer = $groqFallback['answer'];
        }

        return [
            'intent' => $intent,
            'answer' => $answer,
            'source_type' => $groqFallback ? 'Machine Learning + Generative AI' : 'Machine Learning',
            'source_name' => $groqFallback ? 'Predict.py fallback + '.$groqFallback['source_name'] : 'Local Python ML models + iClimate decision rules',
            'source_url' => null,
            'language' => $language,
            'memory' => $memory,
            'prediction_result' => [
                'python' => $pythonResult,
                'decision_support' => $decision,
                'input_features' => $context,
                'calibration' => $calibration['metadata'],
                'quality' => $quality,
                'insights' => $insights,
                'conversation_memory' => $memory,
                'api_error' => $apiError,
                'intent_detection' => $intentResult,
                'generative_ai' => $groqFallback ? [
                    'provider' => 'Groq',
                    'model' => $groqFallback['model'],
                    'usage' => $groqFallback['usage'],
                    'reason' => 'Python model output was unavailable or low-confidence, so Groq explained the iClimate fallback result.',
                ] : null,
                'answer_source' => 'Prediction values came from trained machine-learning models and iClimate decision rules.',
            ],
            'weather_prediction' => $weather,
            'rice_yield_prediction' => $yield,
            'planting_recommendation' => $decision['planting'],
            'irrigation_recommendation' => $decision['irrigation'],
            'warnings' => $warnings,
            'explanation' => $explanation,
            'confidence_score' => $confidence,
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function groqPredictionFallback(User $user, string $question, string $intent, string $language, array $memory, array $context, array $weather, array $yield, array $decision, array $warnings, array $quality, ?string $apiError, ?array $pythonResult): ?array
    {
        if (! $this->shouldAskGroqForPredictionFallback($quality, $apiError, $pythonResult)) {
            return null;
        }

        return $this->groqChat->predictionFallback($user, $question, $intent, $language, $memory, [
            'inputs_used' => Arr::only($context, ['rainfall', 'temp_avg', 'humidity', 'wind_speed', 'season', 'farm_type', 'area', 'barangay']),
            'weather_prediction' => $weather,
            'rice_yield_prediction' => $yield,
            'risk' => $decision['risk'] ?? null,
            'score' => $decision['score'] ?? null,
            'planting_recommendation' => $decision['planting'] ?? null,
            'irrigation_recommendation' => $decision['irrigation'] ?? null,
            'yield_advisory' => $decision['yield'] ?? null,
            'warnings' => $warnings,
            'quality' => $quality,
            'python_api_error' => $apiError,
            'fallback_used' => $apiError !== null || ! $pythonResult || (($yield['fallback'] ?? false) === true),
        ]);
    }

    private function shouldAskGroqForPredictionFallback(array $quality, ?string $apiError, ?array $pythonResult): bool
    {
        return $this->groqChat->available()
            && (
                $apiError !== null
                || ! $pythonResult
                || (($quality['score'] ?? 100) < 70)
            );
    }

    private function answerWithoutPrediction(User $user, string $question, string $intent, string $language, array $memory, array $intentResult, float $startedAt): array
    {
        if ($domainAnswer = $this->domainRecordAnswer($user, $question, $intent, $language)) {
            return $this->textResponse($domainAnswer['answer'], $intent, $language, $memory, $startedAt, [
                'source_type' => 'Knowledge Base',
                'source_name' => $domainAnswer['source_name'],
                'source_url' => null,
                'confidence_score' => $domainAnswer['confidence'],
                'intent_detection' => $intentResult,
            ]);
        }

        if ($record = $this->knowledgeBase->search($question, $intent)) {
            return $this->textResponse((string) $record->answer, $intent, $language, $memory, $startedAt, [
                'source_type' => 'Knowledge Base',
                'source_name' => $record->source_name ?: 'iClimate Knowledge Base',
                'source_url' => $record->source_url,
                'confidence_score' => (float) ($record->confidence ?? 82),
                'knowledge_base_id' => $record->id,
                'intent_detection' => $intentResult,
            ]);
        }

        if ($builtIn = $this->knowledgeBase->builtInAnswer($question, $intent, $language)) {
            return $this->textResponse($builtIn['answer'], $intent, $language, $memory, $startedAt, [
                'source_type' => 'Knowledge Base',
                'source_name' => $builtIn['source_name'],
                'source_url' => null,
                'confidence_score' => $builtIn['confidence'],
                'intent_detection' => $intentResult,
            ]);
        }

        if ($this->shouldUseLocalSystemAnswer($intent, $question)) {
            return $this->textResponse($this->localSystemAnswer($intent, $question, $language), $intent, $language, $memory, $startedAt, [
                'source_type' => 'Knowledge Base',
                'source_name' => 'PalayPilot',
                'source_url' => null,
                'confidence_score' => 82,
                'intent_detection' => $intentResult,
            ]);
        }

        if ($groqAnswer = $this->groqChat->answer($user, $question, $intent, $language, $memory)) {
            return $this->textResponse($groqAnswer['answer'], $intent, $language, $memory, $startedAt, [
                'source_type' => 'Generative AI',
                'source_name' => $groqAnswer['source_name'],
                'source_url' => null,
                'confidence_score' => $groqAnswer['confidence'],
                'intent_detection' => $intentResult,
                'generative_ai' => [
                    'provider' => 'Groq',
                    'model' => $groqAnswer['model'],
                    'usage' => $groqAnswer['usage'],
                ],
            ]);
        }

        if (! $this->isSupportedNonPredictionQuestion($intent, $question)) {
            return $this->textResponse($this->unsupportedAnswer($language), $intent, $language, $memory, $startedAt, [
                'source_type' => 'System Scope',
                'source_name' => 'PalayPilot',
                'source_url' => null,
                'confidence_score' => 80,
                'intent_detection' => $intentResult,
            ]);
        }

        $localAnswer = $this->localSystemAnswer($intent, $question, $language);

        return $this->textResponse($localAnswer, $intent, $language, $memory, $startedAt, [
            'source_type' => 'Knowledge Base',
            'source_name' => 'PalayPilot',
            'source_url' => null,
            'confidence_score' => 76,
            'intent_detection' => $intentResult,
        ]);
    }

    private function shouldUseLocalSystemAnswer(string $intent, string $question): bool
    {
        $text = str($question)->lower()->toString();

        return $intent === IntentDetectionService::SYSTEM_HELP
            && str($text)->contains([
                'how do you gather',
                'how do you get',
                'how do you collect',
                'where do you get',
                'weather source',
                'weather data source',
            ]);
    }

    private function conversationMemory(User $user): array
    {
        $latest = AIChat::query()
            ->where('user_id', $user->id)
            ->latest()
            ->take(3)
            ->get();

        return [
            'last_intent' => $latest->first()?->intent,
            'recent_questions' => $latest->pluck('question')->all(),
            'recent_facts' => $latest
                ->pluck('intent')
                ->filter()
                ->take(3)
                ->values()
                ->all(),
        ];
    }

    private function domainRecordAnswer(User $user, string $question, string $intent, string $language): ?array
    {
        $text = str($question)->lower()->toString();

        if ($intent === IntentDetectionService::BARANGAY_INFO || str_contains($text, 'barangay')) {
            if ($answer = $this->roleAssistant->barangayAnswer($user, $question, $language)) {
                return $answer;
            }
        }

        $mentionsMaoReport = str_contains($text, 'how many farmer') || str_contains($text, 'registered farmer')
            || str_contains($text, 'production summary') || str_contains($text, 'generate report')
            || (str_contains($text, 'farmer') && str_contains($text, 'many'));

        if ($intent === IntentDetectionService::MAO_REPORTS || $mentionsMaoReport) {
            if ($user->role !== User::ROLE_MAO) {
                return [
                    'answer' => $this->roleAssistant->roleRestrictedMessage($language, User::ROLE_MAO, $user->role),
                    'source_name' => 'PalayPilot',
                    'confidence' => 85,
                ];
            }

            if ($answer = $this->roleAssistant->maoAnswer($user, $question, $intent, $language)) {
                return $answer;
            }
        }

        if ($intent === IntentDetectionService::IT_SYSTEM_STATUS) {
            if ($user->role !== User::ROLE_IT_EXPERT) {
                return [
                    'answer' => $this->roleAssistant->roleRestrictedMessage($language, User::ROLE_IT_EXPERT, $user->role),
                    'source_name' => 'PalayPilot',
                    'confidence' => 85,
                ];
            }

            if ($answer = $this->roleAssistant->itAnswer($question, $intent, $language)) {
                return $answer;
            }
        }

        if ($intent === IntentDetectionService::ANNOUNCEMENT || str_contains($text, 'announcement')) {
            $announcement = Announcement::query()->where('status', 'Published')->latest()->first();

            if ($announcement) {
                return [
                    'answer' => 'Latest announcement: '.$announcement->title.'. '.$announcement->content,
                    'source_name' => 'iClimate Announcements',
                    'confidence' => 92,
                ];
            }
        }

        if ($intent === IntentDetectionService::NOTIFICATION || str_contains($text, 'notification')) {
            $notification = Notification::query()->where('user_id', $user->id)->latest()->first();

            if ($notification) {
                return [
                    'answer' => 'Latest notification: '.$notification->title.'. '.$notification->message,
                    'source_name' => 'iClimate Notifications',
                    'confidence' => 92,
                ];
            }
        }

        if ($intent === IntentDetectionService::CALENDAR || str_contains($text, 'calendar')) {
            $advisory = PlantingAdvisory::query()->where('status', 'Published')->latest()->first();

            if ($advisory) {
                return [
                    'answer' => 'Planting calendar guidance: '.$advisory->title.'. '.$advisory->content,
                    'source_name' => 'iClimate Planting Advisories',
                    'confidence' => 86,
                ];
            }
        }

        if ($intent === IntentDetectionService::USER_PROFILE || str_contains($text, 'profile')) {
            $profile = $user->farmerProfile;
            $answer = $profile
                ? 'Your profile shows barangay '.$profile->barangay.', farm area '.$profile->farm_area.' hectares, and farm type '.$profile->farm_type.'.'
                : 'No farmer profile is linked to your account yet. Add farm area, barangay, and farm type to improve iClimate recommendations.';

            return [
                'answer' => $answer,
                'source_name' => 'iClimate User Profile',
                'confidence' => 90,
            ];
        }

        return null;
    }

    private function isSupportedNonPredictionQuestion(string $intent, string $question): bool
    {
        $text = str($question)->lower()->toString();

        if (in_array($intent, [
            IntentDetectionService::SYSTEM_HELP,
            IntentDetectionService::ANNOUNCEMENT,
            IntentDetectionService::NOTIFICATION,
            IntentDetectionService::CALENDAR,
            IntentDetectionService::USER_PROFILE,
            IntentDetectionService::FARMING_ADVISORY,
            IntentDetectionService::GENERAL_AGRICULTURE,
            IntentDetectionService::BARANGAY_INFO,
            IntentDetectionService::MAO_REPORTS,
            IntentDetectionService::IT_SYSTEM_STATUS,
            IntentDetectionService::GENERAL_CONVERSATION,
        ], true)) {
            return true;
        }

        return str($text)->contains([
            'iclimate',
            'weather prediction',
            'rice yield',
            'planting advisory',
            'community feed',
            'notification',
            'announcement',
            'profile',
            'dashboard',
            'feedback',
            'climate record',
            'heat map',
            'report',
            'barangay',
            'database',
            'server',
            'backup',
            'maintenance',
            'fertilizer',
            'soil',
            'pest',
            'disease',
            'random forest',
            'rmse',
        ]);
    }

    private function unsupportedAnswer(string $language): string
    {
        if (str_contains($language, 'Tagalog')) {
            return 'Makakatulong ako tungkol sa iClimate lang: weather prediction, rice yield prediction, planting at irrigation advice, climate risk, fertilizer, pest at disease, soil, barangay information, announcements, notifications, profile, calendar, reports, at kung paano gamitin ang system.';
        }

        return 'I can help with iClimate only: weather prediction, rice yield prediction, planting and irrigation advice, climate risk, fertilizer, pest and disease guidance, soil information, barangay information, announcements, notifications, profile, calendar, reports, and how to use the system.';
    }

    private function localSystemAnswer(string $intent, string $question, string $language): string
    {
        $text = str($question)->lower()->toString();

        if ($intent === IntentDetectionService::GENERAL_CONVERSATION) {
            if (str_contains($text, 'thank') || str_contains($text, 'salamat')) {
                return str_contains($language, 'Tagalog')
                    ? 'Walang anuman! Tanong lang kung may kailangan ka pang malaman tungkol sa iClimate.'
                    : "You're welcome! Ask me anytime about weather, yield, planting, advisories, or your iClimate account.";
            }

            return str_contains($language, 'Tagalog')
                ? 'Kumusta! Ako si PalayPilot, ang iClimate rice guidance assistant. Puwede kang magtanong tungkol sa panahon, ani, pagtatanim, irigasyon, advisory, o sistema.'
                : 'Hello! I am PalayPilot, the iClimate rice guidance assistant. Ask me about weather, yield, planting, irrigation, advisories, or how to use the system.';
        }

        if ($intent === IntentDetectionService::SYSTEM_HELP && str($text)->contains(['weather', 'forecast', 'prediction'])) {
            return str_contains($language, 'Tagalog')
                ? 'Kinukuha ng iClimate ang weather inputs mula sa latest Open-Meteo forecast kapag available, at gumagamit din ng naka-save na climate records sa system bilang fallback o context. Pagkatapos, ipinapasa ang rainfall, temperature, humidity, wind speed, season, farm type, at farm area sa Predict.py/Python model at iClimate decision rules. Kung hindi maabot ang Python Farming AI API, gumagamit ang system ng local fallback rules para makapagbigay pa rin ng guidance. Hindi ako gumagawa ng sariling raw weather data; ipinapaliwanag at inaayos ko ang datos mula sa mga tool ng iClimate.'
                : 'iClimate gathers weather inputs from the latest Open-Meteo forecast when available, then uses saved climate records in the system as fallback or context. It passes rainfall, temperature, humidity, wind speed, season, farm type, and farm area into Predict.py/Python models and iClimate decision rules. If the Python Farming AI API cannot be reached, the system uses local fallback rules so guidance is still available. I do not create raw weather data myself; I explain and organize the data from iClimate tools.';
        }

        if ($intent === IntentDetectionService::FARMING_ADVISORY || str_contains($text, 'fertilizer')) {
            return str_contains($language, 'Tagalog')
                ? 'Para sa payong pangsakahan sa iClimate, gamitin ang weather prediction, rice yield result, at field condition bago magdesisyon. Kung fertilizer ang tanong, mas mabuting basehan ang soil test, crop stage, at inaasahang ulan.'
                : 'For iClimate farming advice, use the weather prediction, rice yield result, and actual field condition together. For fertilizer, base the decision on soil test results, crop stage, and expected rainfall.';
        }

        if ($intent === IntentDetectionService::GENERAL_AGRICULTURE || str_contains($text, 'rice') || str_contains($text, 'palay')) {
            return str_contains($language, 'Tagalog')
                ? 'Makakatulong ako sa rice farming kapag konektado ito sa iClimate, gaya ng ulan, ani, pagtatanim, patubig, climate risk, at advisory.'
                : 'I can help with rice farming when it is connected to iClimate, such as rainfall, yield, planting, irrigation, climate risk, and advisories.';
        }

        return str_contains($language, 'Tagalog')
            ? 'Naiintindihan ko ang tanong pero wala pa akong partikular na sagot na naka-save dito. Subukan ulit nang mas detalyado, o kumpirmahin sa MAO o IT staff.'
            : 'I understand the topic, but I do not have a specific saved answer for that yet. Try rephrasing with more detail, or confirm with your MAO or IT staff.';
    }

    private function textResponse(string $answer, string $intent, string $language, array $memory, float $startedAt, array $metadata): array
    {
        $confidence = (float) ($metadata['confidence_score'] ?? 65);
        $sourceType = (string) ($metadata['source_type'] ?? 'Knowledge Base');
        $sourceName = (string) ($metadata['source_name'] ?? $sourceType);

        return [
            'intent' => $intent,
            'answer' => $this->cleanVisibleAnswer($answer),
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'source_url' => $metadata['source_url'] ?? null,
            'language' => $language,
            'memory' => [
                ...$memory,
                'last_answer_source' => $sourceType,
                'last_answer_intent' => $intent,
            ],
            'prediction_result' => [
                'intent_detection' => $metadata['intent_detection'] ?? null,
                'knowledge_base_id' => $metadata['knowledge_base_id'] ?? null,
                'generative_ai' => $metadata['generative_ai'] ?? null,
                'answer_source' => isset($metadata['generative_ai'])
                    ? 'Answered by Groq using the PalayPilot iClimate instructions.'
                    : 'Answered from iClimate system knowledge, saved records, or built-in rules.',
            ],
            'weather_prediction' => null,
            'rice_yield_prediction' => null,
            'planting_recommendation' => null,
            'irrigation_recommendation' => null,
            'warnings' => [],
            'explanation' => $this->isTagalog($language)
                ? 'Ang tanong ay na-classify bilang '.$intent.' at sinagot mula sa '.$sourceType.'.'
                : 'Question classified as '.$intent.' and answered from '.$sourceType.'.',
            'confidence_score' => $confidence,
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function cleanVisibleAnswer(string $answer): string
    {
        $answer = trim($answer);

        $answer = preg_replace('/\A\s*Answer:\s*/i', '', $answer) ?? $answer;
        $answer = preg_replace('/\n{2,}(Explanation|Recommendation|Warning|Source|Confidence):[\s\S]*\z/i', '', $answer) ?? $answer;

        return trim($answer);
    }

    private function detectIntent(string $question, array $memory): string
    {
        $text = str($question)->lower()->toString();
        $scores = [
            'planting' => $this->score($text, ['plant', 'planting', 'seedling', 'seedlings', 'transplant', 'sow', 'punla', 'tanim', 'magtanim', 'next week']),
            'irrigation' => $this->score($text, ['irrigat', 'water', 'tubig', 'patubig', 'dilig', 'canal', 'rainfed', 'irrigated']),
            'yield' => $this->score($text, ['yield', 'harvest', 'production', 'ani', 'tons', 'low yield', 'palay']),
            'weather' => $this->score($text, ['rain', 'weather', 'storm', 'wind', 'ulan', 'bagyo', 'hangin', 'humidity', 'temperature']),
            'warning' => $this->score($text, ['drought', 'flood', 'warning', 'heat', 'risk', 'tagtuyot', 'baha', 'init', 'water shortage']),
            'fertilizer' => $this->score($text, ['fertilizer', 'fertiliser', 'soil', 'nitrogen', 'urea', 'compost', 'abono', 'pataba', 'soil test']),
            'pest_disease' => $this->score($text, ['pest', 'disease', 'insect', 'fungus', 'blast', 'tungro', 'brown spot', 'planthopper', 'sakit', 'peste']),
        ];

        arsort($scores);
        $intent = (string) array_key_first($scores);

        if (($scores[$intent] ?? 0) === 0 && str($text)->contains(['it', 'that', 'this', 'also', 'what about'])) {
            return (string) ($memory['last_intent'] ?? 'general');
        }

        return ($scores[$intent] ?? 0) > 0 ? $intent : 'general';
    }

    private function contextFor(User $user, string $question, array $memory = []): array
    {
        $forecast = $this->latestWeatherForecast();
        $latest = ClimateRecord::query()->latest('record_date')->first();
        $previous = ClimateRecord::query()->latest('record_date')->skip(1)->first();
        $records = ClimateRecord::query()->latest('record_date')->take(6)->get();
        $profile = $user->farmerProfile ?: FarmerProfile::query()->where('user_id', $user->id)->first();
        $memoryText = $question.' '.implode(' ', $memory['recent_questions'] ?? []);
        $numbers = $this->numbersFrom($question);
        $conditions = $this->conditionsFrom($memoryText);

        $rainfall = $numbers['rainfall'] ?? (float) ($forecast?->rainfall_mm ?? $latest?->rainfall ?? 180);
        $temperature = $numbers['temperature'] ?? $conditions['temperature'] ?? (float) ($forecast?->temperature ?? $latest?->temperature ?? 29);
        $humidity = $numbers['humidity'] ?? (float) ($forecast?->humidity ?? $latest?->humidity ?? 78);
        $windSpeed = $numbers['wind_speed'] ?? (float) ($forecast?->wind_speed ?? $latest?->wind_speed ?? 8);
        $season = $conditions['season']
            ?? (string) ($latest?->season ?? $this->seasonForDate($forecast?->forecast_date?->toImmutable() ?? CarbonImmutable::now()));

        return [
            'rainfall' => $rainfall,
            'temp_avg' => $temperature,
            'temp_range' => $numbers['temp_range'] ?? 8,
            'area' => $numbers['area'] ?? (float) ($profile?->farm_area ?? 1),
            'previous_rainfall' => (float) ($previous?->rainfall ?? $rainfall),
            'previous_temp' => (float) ($previous?->temperature ?? $temperature),
            'previous_humidity' => (float) ($previous?->humidity ?? $humidity),
            'previous_wind_speed' => (float) ($previous?->wind_speed ?? $windSpeed),
            'rainfall_6m' => round((float) ($records->avg('rainfall') ?: $rainfall), 2),
            'temp_3m' => round((float) ($records->take(3)->avg('temperature') ?: $temperature), 2),
            'temp_6m' => round((float) ($records->avg('temperature') ?: $temperature), 2),
            'seasonal_rainfall' => round((float) (($records->sum('rainfall') ?: $rainfall * 6)), 2),
            'seasonal_temp' => round((float) ($records->avg('temperature') ?: $temperature), 2),
            'humidity' => $humidity,
            'wind_speed' => $windSpeed,
            'season' => $season,
            'farm_type' => $conditions['farm_type'] ?? (string) ($profile?->farm_type ?? FarmerProfile::FARM_TYPE_RAINFED),
            'barangay' => (string) ($profile?->barangay ?? $user->barangay ?? ''),
            'month_num' => CarbonImmutable::now()->month,
            'source_notes' => $this->sourceNotes($numbers, $conditions, $latest, $forecast),
        ];
    }

    private function latestWeatherForecast(): ?ExternalWeatherData
    {
        $latestFetch = ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->max('fetched_at');

        if (! $latestFetch) {
            return null;
        }

        return ExternalWeatherData::query()
            ->where('source', 'Open-Meteo')
            ->where('fetched_at', $latestFetch)
            ->whereDate('forecast_date', '>=', CarbonImmutable::today())
            ->orderBy('forecast_date')
            ->first()
            ?? ExternalWeatherData::query()
                ->where('source', 'Open-Meteo')
                ->where('fetched_at', $latestFetch)
                ->orderByDesc('forecast_date')
                ->first();
    }

    private function seasonForDate(CarbonImmutable $date): string
    {
        return $date->month >= 5 && $date->month <= 10
            ? ClimateRecord::SEASON_WET
            : ClimateRecord::SEASON_DRY;
    }

    private function numbersFrom(string $question): array
    {
        $values = [];

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:mm|millimeter|millimeters)/i', $question, $match)) {
            $values['rainfall'] = (float) $match[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:c|degree|degrees|celsius)/i', $question, $match)) {
            $values['temperature'] = (float) $match[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:ha|hectare|hectares)/i', $question, $match)) {
            $values['area'] = (float) $match[1];
        }

        if (preg_match('/(?:humidity|halumigmig)\s*(?:is|:)?\s*(\d+(?:\.\d+)?)\s*%?/i', $question, $match)) {
            $values['humidity'] = (float) $match[1];
        }

        if (preg_match('/(?:wind|hangin|wind speed)\s*(?:is|:)?\s*(\d+(?:\.\d+)?)/i', $question, $match)) {
            $values['wind_speed'] = (float) $match[1];
        }

        if (preg_match('/(?:range|temp range)\s*(?:is|:)?\s*(\d+(?:\.\d+)?)/i', $question, $match)) {
            $values['temp_range'] = (float) $match[1];
        }

        return $values;
    }

    private function conditionsFrom(string $question): array
    {
        $text = str($question)->lower()->toString();
        $conditions = [];

        if (str_contains($text, 'rainfed') || str_contains($text, 'walang irigasyon')) {
            $conditions['farm_type'] = FarmerProfile::FARM_TYPE_RAINFED;
        } elseif (str_contains($text, 'irrigated') || str_contains($text, 'may irigasyon')) {
            $conditions['farm_type'] = FarmerProfile::FARM_TYPE_IRRIGATED;
        }

        if (str_contains($text, 'dry season') || str_contains($text, 'tag-init')) {
            $conditions['season'] = ClimateRecord::SEASON_DRY;
        } elseif (str_contains($text, 'wet season') || str_contains($text, 'tag-ulan')) {
            $conditions['season'] = ClimateRecord::SEASON_WET;
        }

        if (str_contains($text, 'very hot') || str_contains($text, 'sobrang init')) {
            $conditions['temperature'] = 35;
        }

        return $conditions;
    }

    private function sourceNotes(array $numbers, array $conditions, ?ClimateRecord $latest, ?ExternalWeatherData $forecast): array
    {
        $notes = [];

        foreach (['rainfall', 'temperature', 'humidity', 'wind_speed', 'area'] as $field) {
            if (array_key_exists($field, $numbers)) {
                $notes[] = str_replace('_', ' ', $field).' came from the farmer question.';
            }
        }

        foreach (['farm_type', 'season'] as $field) {
            if (array_key_exists($field, $conditions)) {
                $notes[] = str_replace('_', ' ', $field).' came from the farmer question.';
            }
        }

        if ($forecast) {
            $notes[] = 'Missing weather values used the latest Open-Meteo forecast for '.$forecast->forecast_date?->format('M d, Y').', fetched '.$forecast->fetched_at?->format('M d, Y H:i').'.';
        } elseif ($latest) {
            $notes[] = 'Missing weather values used the latest climate record from '.$latest->record_date?->format('M d, Y').'.';
        } else {
            $notes[] = 'Missing weather values used safe default planning values.';
        }

        return $notes;
    }

    private function fallbackWeather(array $context): array
    {
        $rainfall = (float) $context['rainfall'];
        $weather = match (true) {
            $rainfall >= 300 => 'Heavy Rain',
            $rainfall >= 120 => 'Rain',
            $rainfall <= 70 => 'Dry',
            default => 'Cloudy',
        };

        return [
            'predicted_weather' => $weather,
            'confidence' => 62,
            'explanation' => 'Generated from decision rules because the Python Flask API could not provide the weather model output.',
        ];
    }

    private function fallbackYield(array $context, array $modelYield = []): array
    {
        $records = RiceProduction::query()
            ->when($context['barangay'] !== '', fn ($query) => $query->where('barangay', $context['barangay']))
            ->where('season', $context['season'])
            ->whereNotNull('yield_per_hectare')
            ->latest('year')
            ->take(5)
            ->get();

        if ($records->isEmpty()) {
            $records = RiceProduction::query()
                ->where('season', $context['season'])
                ->whereNotNull('yield_per_hectare')
                ->latest('year')
                ->take(8)
                ->get();
        }

        $baseYield = $records->isNotEmpty()
            ? (float) $records->avg('yield_per_hectare')
            : (strtolower((string) $context['season']) === 'dry' ? 3.6 : 4.1);

        $adjustment = 0.0;
        $rainfall = (float) $context['rainfall'];
        $temperature = (float) $context['temp_avg'];
        $humidity = (float) $context['humidity'];
        $windSpeed = (float) $context['wind_speed'];
        $farmType = strtolower((string) $context['farm_type']);
        $season = strtolower((string) $context['season']);

        $adjustment += match (true) {
            $rainfall < 80 => -0.8,
            $rainfall < 120 => -0.35,
            $rainfall >= 180 && $rainfall <= 280 => 0.25,
            $rainfall > 350 => -0.65,
            $rainfall > 300 => -0.35,
            default => 0.0,
        };

        $adjustment += match (true) {
            $temperature > 34 => -0.4,
            $temperature > 32 => -0.2,
            $temperature < 22 => -0.25,
            default => 0.0,
        };

        if ($humidity > 92) {
            $adjustment -= 0.15;
        }

        if ($windSpeed > 30) {
            $adjustment -= 0.25;
        } elseif ($windSpeed > 20) {
            $adjustment -= 0.1;
        }

        if ($farmType === 'irrigated') {
            $adjustment += 0.15;
        } elseif ($farmType === 'rainfed' && $season === 'dry') {
            $adjustment -= 0.25;
        }

        $predictedYield = max(1.2, min(6.5, $baseYield + $adjustment));

        return [
            ...$modelYield,
            'predicted_yield' => round($predictedYield, 2),
            'unit' => $modelYield['unit'] ?? 'tons/hectare',
            'confidence' => $records->count() >= 2 ? 68 : 56,
            'fallback' => true,
            'fallback_note' => 'Estimated locally because the Python yield model did not return a numeric value.',
            'explanation' => 'Fallback estimate based on recent rice production records when available, then adjusted for rainfall, temperature, humidity, wind, farm type, and season.',
            'records_used' => $records->count(),
            'base_yield' => round($baseYield, 2),
            'climate_adjustment' => round($adjustment, 2),
        ];
    }

    private function normalizeWarnings(array $pythonWarnings, array $decision): array
    {
        $warnings = [];

        foreach ($pythonWarnings as $warning) {
            $warnings[] = is_array($warning) ? $warning : ['title' => $warning, 'reason' => $warning];
        }

        foreach ($decision['notifications'] as $notification) {
            $warnings[] = ['title' => $notification, 'reason' => $notification];
        }

        return array_values(array_unique($warnings, SORT_REGULAR));
    }

    private function calibrateYield(array $yield, array $context): array
    {
        $predicted = Arr::get($yield, 'predicted_yield');

        if ($predicted === null) {
            return [
                'yield' => $yield,
                'metadata' => [
                    'applied' => false,
                    'reason' => 'No numeric model yield was returned.',
                ],
            ];
        }

        if (($yield['fallback'] ?? false) === true) {
            return [
                'yield' => [
                    ...$yield,
                    'raw_predicted_yield' => round((float) $predicted, 2),
                    'calibrated' => false,
                ],
                'metadata' => [
                    'applied' => false,
                    'records_used' => $yield['records_used'] ?? 0,
                    'reason' => 'Yield was estimated by local fallback rules because no numeric model yield was returned.',
                    'fallback' => true,
                ],
            ];
        }

        $query = RiceProduction::query();

        if ($context['barangay'] !== '') {
            $query->where('barangay', $context['barangay']);
        }

        $localRecords = (clone $query)
            ->where('season', $context['season'])
            ->latest('year')
            ->take(5)
            ->get();

        if ($localRecords->count() < 2) {
            $localRecords = RiceProduction::query()
                ->where('season', $context['season'])
                ->latest('year')
                ->take(8)
                ->get();
        }

        if ($localRecords->count() < 2) {
            return [
                'yield' => [
                    ...$yield,
                    'raw_predicted_yield' => round((float) $predicted, 2),
                    'calibrated' => false,
                ],
                'metadata' => [
                    'applied' => false,
                    'records_used' => $localRecords->count(),
                    'reason' => 'Not enough historical rice production records for local calibration.',
                ],
            ];
        }

        $localAverage = (float) $localRecords->avg('yield_per_hectare');
        $weight = $localRecords->count() >= 5 ? 0.25 : 0.15;
        $calibrated = ((float) $predicted * (1 - $weight)) + ($localAverage * $weight);

        return [
            'yield' => [
                ...$yield,
                'raw_predicted_yield' => round((float) $predicted, 2),
                'predicted_yield' => round($calibrated, 2),
                'calibrated' => true,
                'calibration_note' => 'Adjusted toward recent saved rice production records for the same season.',
            ],
            'metadata' => [
                'applied' => true,
                'records_used' => $localRecords->count(),
                'local_average_yield' => round($localAverage, 2),
                'weight' => $weight,
            ],
        ];
    }

    private function predictionQuality(array $context, array $calibration, ?array $pythonResult, ?string $apiError): array
    {
        $issues = [];

        if ($apiError) {
            $issues[] = 'Python model API unavailable; fallback rules were used.';
        }

        if (! $pythonResult) {
            $issues[] = 'No direct machine-learning response was available.';
        }

        if (($calibration['metadata']['fallback'] ?? false) === true) {
            $issues[] = $calibration['metadata']['reason'] ?? 'Local fallback yield estimate was used.';
        } elseif (($calibration['metadata']['applied'] ?? false) === false) {
            $issues[] = $calibration['metadata']['reason'] ?? 'No local calibration was applied.';
        }

        if ((float) $context['rainfall'] < 0 || (float) $context['rainfall'] > 600) {
            $issues[] = 'Rainfall is outside the normal model planning range.';
        }

        if ((float) $context['temp_avg'] < 18 || (float) $context['temp_avg'] > 40) {
            $issues[] = 'Temperature is outside the normal model planning range.';
        }

        if ((float) $context['humidity'] < 35 || (float) $context['humidity'] > 100) {
            $issues[] = 'Humidity is outside the normal model planning range.';
        }

        if ((float) $context['area'] <= 0) {
            $issues[] = 'Farm area is missing or invalid.';
        }

        $score = 100 - (count($issues) * 12);

        if (($calibration['metadata']['applied'] ?? false) === true) {
            $score += 6;
        }

        $score = max(35, min(95, $score));

        return [
            'score' => $score,
            'label' => match (true) {
                $score >= 82 => 'High reliability',
                $score >= 65 => 'Moderate reliability',
                default => 'Low reliability',
            },
            'issues' => $issues,
        ];
    }

    private function adjustedConfidence(?array $pythonResult, array $decision, array $quality): float
    {
        $base = (float) ($pythonResult['confidence_score'] ?? $decision['confidence']['value'] ?? 65);
        $confidence = ($base * 0.65) + ((float) $quality['score'] * 0.35);

        return round(max(35, min(95, $confidence)), 2);
    }

    private function insights(string $intent, array $context, array $yield, array $decision, array $warnings, array $quality, string $language): array
    {
        $tagalog = $this->isTagalog($language);
        $yieldValue = Arr::get($yield, 'predicted_yield');
        $steps = [];
        $reasons = [];

        foreach ($decision['stress_factors'] as $factor) {
            $reasons[] = $tagalog
                ? $this->tagalogStressLabel((string) $factor['label']).': '.$this->tagalogPhrase((string) $factor['detail'])
                : $factor['label'].': '.$factor['detail'];
        }

        if ($warnings !== []) {
            $steps[] = $tagalog ? 'Suriin ang bukid pagkatapos ng ulan at panatilihing bukas ang drainage channels.' : 'Check the field after rainfall and keep drainage channels open.';
        }

        if ((float) $context['rainfall'] < 100) {
            $steps[] = $tagalog ? 'Para sa rainfed na bukid, ipagpaliban muna ang paglilipat-tanim hanggang bumuti ang ulan o makahanap ng dagdag na patubig.' : 'For rainfed fields, delay transplanting until rainfall improves or arrange supplemental water.';
        }

        if ((float) $context['rainfall'] > 300) {
            $steps[] = $tagalog ? 'Iwasan muna ang dagdag na tubig at tingnan ang mabababang bahagi kung may waterlogging.' : 'Avoid adding more water and inspect low areas for waterlogging.';
        }

        if ((float) $context['temp_avg'] >= 34) {
            $steps[] = $tagalog ? 'Magpatubig sa umaga o late afternoon para mabawasan ang heat stress.' : 'Schedule irrigation early morning or late afternoon to reduce heat stress.';
        }

        if ($yieldValue !== null && (float) $yieldValue < 3) {
            $steps[] = $tagalog ? 'Mag-request ng soil testing at repasuhin ang timing ng fertilizer bago ang susunod na crop stage.' : 'Request soil testing and review fertilizer timing before the next crop stage.';
        }

        if ($intent === IntentDetectionService::FARMING_ADVISORY) {
            $steps[] = $tagalog ? 'Unahin ang soil-test results; kung wala pa, hatiin ang nitrogen application imbes na isang bagsakan.' : 'Use soil-test results first; if unavailable, split nitrogen applications instead of applying all at once.';
            $steps[] = $tagalog ? 'Iwasang maglagay ng fertilizer bago ang malakas na ulan dahil puwedeng maanod ang nutrients.' : 'Avoid fertilizer application before heavy rain because nutrients can be washed away.';
            $steps[] = $tagalog ? 'Suriin ang dahon dalawang beses kada linggo pagkatapos ng ulan o mataas na humidity, at i-report ang paninilaw, spots, o kumpol ng insekto.' : 'Inspect leaves twice a week after rain or high humidity and report unusual yellowing, spots, or insect clusters.';
            $steps[] = $tagalog ? 'Iwasan ang hindi kailangang pesticide hangga\'t hindi pa natutukoy ang peste o sakit.' : 'Avoid unnecessary pesticide use until the pest or disease is identified.';
        }

        if ($steps === []) {
            $steps[] = $tagalog ? 'Ipagpatuloy ang regular na pagmamasid sa bukid at ikumpara ang payo sa aktuwal na kondisyon.' : 'Continue normal field monitoring and compare the advice with actual field condition.';
        }

        if ($quality['issues'] !== []) {
            $steps[] = $tagalog ? 'Para mas tumama ang susunod na prediction, magdagdag ng recent climate records at rice production results pagkatapos ng ani.' : 'Improve accuracy by adding recent climate records and rice production outcomes after harvest.';
        }

        return [
            'reasons' => array_values(array_unique($reasons)),
            'next_steps' => array_values(array_unique($steps)),
            'quality' => $quality,
            'input_summary' => $tagalog
                ? 'Ulan '.number_format((float) $context['rainfall'], 1).' mm, temperatura '.number_format((float) $context['temp_avg'], 1).' C, humidity '.number_format((float) $context['humidity'], 1).'%, hangin '.number_format((float) $context['wind_speed'], 1).', '.$context['season'].' season, '.$context['farm_type'].' na bukid.'
                : 'Rainfall '.number_format((float) $context['rainfall'], 1).' mm, temperature '.number_format((float) $context['temp_avg'], 1).' C, humidity '.number_format((float) $context['humidity'], 1).'%, wind '.number_format((float) $context['wind_speed'], 1).', '.$context['season'].' season, '.$context['farm_type'].' farm.',
        ];
    }

    private function explanation(array $weather, array $yield, array $insights, ?string $apiError, string $language): string
    {
        $tagalog = $this->isTagalog($language);
        $parts = [];
        $parts[] = $tagalog ? 'Sinuri ko ang tanong gamit ang mga input na ito: '.$insights['input_summary'] : 'I checked the question against these inputs: '.$insights['input_summary'];
        $yieldPhrase = ($yield['fallback'] ?? false) === true
            ? (isset($yield['predicted_yield']) && $yield['predicted_yield'] !== null
                ? ($tagalog ? 'gumamit ng fallback estimate na ' : 'used a fallback estimate of ').number_format((float) $yield['predicted_yield'], 2).' tons/hectare'
                : ($tagalog ? 'gumamit ng fallback estimate pero walang numeric value' : 'used fallback rules but did not produce a numeric value'))
            : (isset($yield['predicted_yield']) && $yield['predicted_yield'] !== null
                ? ($tagalog ? 'nag-estimate ng ' : 'estimated ').number_format((float) $yield['predicted_yield'], 2).' tons/hectare'
                : ($tagalog ? 'walang naibalik na numeric yield estimate' : 'did not return a numeric yield estimate'));

        $parts[] = $tagalog
            ? 'Ayon sa weather model, '.$this->weatherLabel($weather['predicted_weather'] ?? null, $language).' ang kondisyon; ang yield model naman ay '.$yieldPhrase.'.'
            : 'The weather model indicates '.$weather['predicted_weather'].', while the yield model '.$yieldPhrase.'.';
        $parts[] = $tagalog ? 'Sinuri rin ng decision rules ang tagtuyot, malakas na ulan, init, humidity, pangangailangan sa patubig, inaasahang ani, at stress factors bago pumili ng rekomendasyon.' : 'The decision rules checked drought, heavy rainfall, heat, humidity, irrigation need, expected yield, and stress factors before choosing the recommendation.';

        if ($insights['reasons'] !== []) {
            $parts[] = ($tagalog ? 'Pangunahing dahilan: ' : 'Main reason: ').implode(' ', array_slice($insights['reasons'], 0, 2));
        }

        $parts[] = $tagalog
            ? 'Reliability: '.$this->qualityLabel((string) $insights['quality']['label'], $language).' na may score na '.$insights['quality']['score'].'.'
            : 'Reliability: '.$insights['quality']['label'].' with score '.$insights['quality']['score'].'.';

        if ($apiError) {
            $parts[] = $tagalog ? 'Tandaan: '.$apiError.' Gumamit ng local decision-support fallback rules kung walang model output.' : 'Note: '.$apiError.' Local decision-support fallback rules were used where model output was unavailable.';
        }

        return implode(' ', $parts);
    }

    private function composeAnswer(string $intent, array $weather, array $yield, array $decision, array $warnings, array $insights, string $explanation, string $source, float $confidence, string $language): string
    {
        $tagalog = $this->isTagalog($language);
        $yieldText = isset($yield['predicted_yield']) && $yield['predicted_yield'] !== null
            ? number_format((float) $yield['predicted_yield'], 2).' tons/hectare'
            : ($tagalog ? 'hindi available' : 'not available');
        $warningText = $warnings === []
            ? ($tagalog ? 'Walang urgent climate warning na nabuo.' : 'No urgent climate warning was generated.')
            : collect($warnings)->pluck('title')->implode('; ');
        $weatherText = $this->weatherLabel($weather['predicted_weather'] ?? null, $language);
        $riskText = $this->riskLabel((string) $decision['risk']['label'], $language);
        $qualityText = $this->qualityLabel((string) $insights['quality']['label'], $language);

        if ($tagalog) {
            $opening = match ($intent) {
                IntentDetectionService::PLANTING_RECOMMENDATION => 'Para sa pagtatanim, ito ang rekomendasyon ko: '.$this->tagalogPhrase((string) $decision['planting']['recommendation']),
                IntentDetectionService::IRRIGATION_RECOMMENDATION => 'Para sa patubig, ito ang rekomendasyon ko: '.$this->tagalogPhrase((string) $decision['irrigation']['recommendation']),
                IntentDetectionService::RICE_YIELD_PREDICTION => 'Ang predicted rice yield mo ay '.$yieldText.'. '.$this->tagalogPhrase((string) $decision['yield']['advisory']),
                IntentDetectionService::WEATHER_PREDICTION => 'Ang predicted weather ay '.$weatherText.'.',
                IntentDetectionService::CLIMATE_RISK => 'Climate warning check: '.$warningText,
                IntentDetectionService::FARMING_ADVISORY => 'Para sa payong pangsakahan, magsimula sa pagmamasid sa bukid at itugma ang fertilizer, peste, o soil decisions sa predicted climate conditions. '.$this->tagalogPhrase((string) $decision['yield']['advisory']),
                default => 'Narito ang payong pangsakahan batay sa latest climate data at model results.',
            };
        } else {
            $opening = match ($intent) {
                IntentDetectionService::PLANTING_RECOMMENDATION => 'For planting, I recommend: '.$decision['planting']['recommendation'],
                IntentDetectionService::IRRIGATION_RECOMMENDATION => 'For irrigation, I recommend: '.$decision['irrigation']['recommendation'],
                IntentDetectionService::RICE_YIELD_PREDICTION => 'Your predicted rice yield is '.$yieldText.'. '.$decision['yield']['advisory'],
                IntentDetectionService::WEATHER_PREDICTION => 'The predicted weather is '.$weather['predicted_weather'].'.',
                IntentDetectionService::CLIMATE_RISK => 'Climate warning check: '.$warningText,
                IntentDetectionService::FARMING_ADVISORY => 'For farming advice, start with field monitoring and match fertilizer, pest, or soil decisions with the predicted climate conditions. '.$decision['yield']['advisory'],
                default => 'Here is the farming guidance based on the latest climate and model results.',
            };
        }

        $nextSteps = collect($insights['next_steps'])
            ->take(4)
            ->map(fn (string $step, int $index): string => ($index + 1).'. '.$step)
            ->implode("\n");

        if ($tagalog) {
            return "{$opening}\n\n".
                'Mga input na ginamit: '.$insights['input_summary']."\n".
                'Predicted Weather: '.$weatherText."\n".
                'Predicted Yield: '.$yieldText."\n".
                'Risk Level: '.$riskText.' (score '.$decision['score']['value'].")\n".
                'Prediction Reliability: '.$qualityText.' ('.$insights['quality']['score'].")\n".
                $explanation."\n\n".
                "Rekomendasyon:\n".
                'Pagtatanim: '.$this->tagalogPhrase((string) $decision['planting']['recommendation'])."\n".
                'Patubig: '.$this->tagalogPhrase((string) $decision['irrigation']['recommendation'])."\n".
                "Susunod na hakbang:\n".$nextSteps."\n\n".
                "Babala: {$warningText}";
        }

        return "{$opening}\n\n".
            'Inputs used: '.$insights['input_summary']."\n".
            'Predicted Weather: '.$weather['predicted_weather']."\n".
            'Predicted Yield: '.$yieldText."\n".
            'Risk Level: '.$decision['risk']['label'].' (score '.$decision['score']['value'].")\n".
            'Prediction Reliability: '.$insights['quality']['label'].' ('.$insights['quality']['score'].")\n".
            $explanation."\n\n".
            "Recommendation:\n".
            'Planting: '.$decision['planting']['recommendation']."\n".
            'Irrigation: '.$decision['irrigation']['recommendation']."\n".
            "Next steps:\n".$nextSteps."\n\n".
            "Warning: {$warningText}";
    }

    private function isTagalog(string $language): bool
    {
        return str_contains($language, 'Tagalog');
    }

    private function weatherLabel(?string $weather, string $language): string
    {
        if (! $this->isTagalog($language)) {
            return (string) $weather;
        }

        return match (strtolower((string) $weather)) {
            'heavy rain' => 'malakas na ulan',
            'rain' => 'maulan',
            'dry' => 'tuyo o mababa ang ulan',
            'cloudy' => 'maulap',
            default => (string) $weather,
        };
    }

    private function riskLabel(string $label, string $language): string
    {
        if (! $this->isTagalog($language)) {
            return $label;
        }

        return match (strtolower($label)) {
            'high' => 'Mataas',
            'moderate', 'medium' => 'Katamtaman',
            'low' => 'Mababa',
            default => $label,
        };
    }

    private function qualityLabel(string $label, string $language): string
    {
        if (! $this->isTagalog($language)) {
            return $label;
        }

        return match (strtolower($label)) {
            'high reliability' => 'Mataas ang reliability',
            'moderate reliability' => 'Katamtaman ang reliability',
            'low reliability' => 'Mababa ang reliability',
            default => $label,
        };
    }

    private function tagalogStressLabel(string $label): string
    {
        return match (strtolower($label)) {
            'rainfall' => 'Ulan',
            'temperature' => 'Temperatura',
            'humidity' => 'Humidity',
            'yield' => 'Ani',
            'irrigation' => 'Patubig',
            default => $label,
        };
    }

    private function tagalogPhrase(string $text): string
    {
        $phrases = [
            'Proceed with planting' => 'Puwedeng magtanim',
            'Delay planting' => 'Ipagpaliban muna ang pagtatanim',
            'Plant with caution' => 'Magtanim nang may pag-iingat',
            'Irrigation not required' => 'Hindi kailangan ang patubig ngayon',
            'Irrigate immediately' => 'Magpatubig agad',
            'Prepare supplemental irrigation' => 'Maghanda ng karagdagang patubig',
            'Maintain normal irrigation' => 'Panatilihin ang normal na patubig',
            'Expected yield is favorable.' => 'Maganda ang inaasahang ani.',
            'Expected yield is low; review inputs and field condition.' => 'Mababa ang inaasahang ani; suriin ang inputs at kondisyon ng bukid.',
            'Expected yield is moderate.' => 'Katamtaman ang inaasahang ani.',
        ];

        return $phrases[$text] ?? $text;
    }

    private function score(string $text, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $score++;
            }
        }

        return $score;
    }
}
