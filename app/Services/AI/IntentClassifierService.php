<?php

namespace App\Services\AI;

use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Support\Arr;

class IntentClassifierService
{
    public const GREETING = 'greeting';

    public const GENERAL_CHAT = 'general_chat';

    public const CURRENT_WEATHER = 'current_weather';

    public const WEATHER_FORECAST = 'weather_forecast';

    public const WEATHER_PREDICTION = 'weather_prediction';

    public const RICE_YIELD_PREDICTION = 'rice_yield_prediction';

    public const PLANTING_RECOMMENDATION = 'planting_recommendation';

    public const IRRIGATION_RECOMMENDATION = 'irrigation_recommendation';

    public const CLIMATE_RISK = 'climate_risk';

    public const WEATHER_ADVISORY = 'weather_advisory';

    public const SYSTEM_HELP = 'system_help';

    public const PROFILE_ACCOUNT = 'profile_account';

    public const COMMUNITY = 'community';

    public const REPORTS = 'reports';

    public const UNKNOWN = 'unknown';

    private const SUPPORTED_INTENTS = [
        self::GREETING,
        self::GENERAL_CHAT,
        self::CURRENT_WEATHER,
        self::WEATHER_FORECAST,
        self::WEATHER_PREDICTION,
        self::RICE_YIELD_PREDICTION,
        self::PLANTING_RECOMMENDATION,
        self::IRRIGATION_RECOMMENDATION,
        self::CLIMATE_RISK,
        self::WEATHER_ADVISORY,
        self::SYSTEM_HELP,
        self::PROFILE_ACCOUNT,
        self::COMMUNITY,
        self::REPORTS,
        self::UNKNOWN,
    ];

    public function __construct(
        private readonly GroqChatService $groq,
        private readonly IntentDetectionService $fallbackDetector,
    ) {}

    /**
     * Local rules first, Groq only for what the rules cannot place.
     *
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}
     */
    public function classify(User $user, string $question, string $language, array $memory = []): array
    {
        $local = $this->classifyLocal($question, $memory);

        if (! $this->needsLlm($local)) {
            return $local;
        }

        return $this->classifyWithLlm($user, $question, $language, $memory) ?? $local;
    }

    /**
     * Rule-based classification: instant, no network.
     *
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}
     */
    public function classifyLocal(string $question, array $memory = []): array
    {
        return $this->preflight($question) ?? $this->fallbackClassification($question, $memory);
    }

    /**
     * Whether the rules left the message unplaced, so asking Groq is worth
     * its wait. A message the rules already know is ambiguous is answered with
     * a clarifying question instead.
     *
     * @param  array{intent:string,requires_clarification:bool}  $local
     */
    public function needsLlm(array $local): bool
    {
        return $local['intent'] === self::GENERAL_CHAT
            || ($local['intent'] === self::UNKNOWN && ! $local['requires_clarification']);
    }

    /**
     * Groq's structured classification, or null when Groq is unavailable,
     * slow, or not confident enough.
     *
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}|null
     */
    public function classifyWithLlm(User $user, string $question, string $language, array $memory = []): ?array
    {
        $llm = $this->groq->classifyIntent($user, $question, $language, $memory);

        if ($llm && $this->validIntent((string) $llm['intent']) && (float) $llm['confidence'] >= 0.75) {
            return [
                'intent' => (string) $llm['intent'],
                'confidence' => (float) $llm['confidence'],
                'entities' => $this->normalizeEntities((array) ($llm['entities'] ?? []), $question),
                'source' => 'llm_structured_classifier',
                'missing' => array_values((array) ($llm['missing'] ?? [])),
                'requires_clarification' => (bool) ($llm['requires_clarification'] ?? false),
            ];
        }

        return null;
    }

    /**
     * Keep tiny assistant-control messages local so they do not require an LLM
     * classification call.
     *
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}|null
     */
    private function preflight(string $question): ?array
    {
        $text = str($question)->lower()->toString();

        // Keep simple greetings local; they must never enter weather or
        // prediction handling.
        if (preg_match('/^\s*(?:hi|hello|hey|hoy|kumusta|kamusta|good\s+morning|good\s+afternoon|good\s+evening)(?:\s+(?:there|po|ka|kayo))?\s*[!,.?]*\s*$/iu', $text) === 1) {
            return [
                'intent' => self::GREETING,
                'confidence' => 1.0,
                'entities' => [],
                'source' => 'local_greeting_preflight',
                'missing' => [],
                'requires_clarification' => false,
            ];
        }

        // "sino ka"/"ano ka" are checked with word boundaries, not plain
        // substring matching — "ano ka" is otherwise a false-positive
        // substring of unrelated text like "paano kaba" ("p-AANO KA-ba"),
        // which would misfire this identity-question shortcut.
        if (preg_match('/\b(sino|ano)\s+ka\b/u', $text) === 1 || str($text)->contains([
            'tagalog',
            'filipino',
            'pilipino',
            'english',
            'ingles',
            'who are you',
            'what are you',
        ])) {
            return [
                'intent' => self::SYSTEM_HELP,
                'confidence' => 1.0,
                'entities' => $this->normalizeEntities([], $question),
                'source' => 'local_control_preflight',
                'missing' => [],
                'requires_clarification' => false,
            ];
        }

        return null;
    }

    /**
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}
     */
    private function fallbackClassification(string $question, array $memory): array
    {
        $text = str($question)->lower()->toString();
        $detected = $this->fallbackDetector->detect($question, $memory);
        $intent = $this->mapLegacyIntent((string) $detected['intent'], $text);

        // The keyword cascade below exists to catch cases the legacy
        // detector handles poorly (e.g. distinguishing "current weather"
        // from "forecast"). It must not run once the legacy detector has
        // already landed on a specific, unambiguous intent — otherwise
        // incidental keyword overlap silently reroutes a correct
        // classification into an unrelated one. Concretely: "Should I
        // plant rice THIS WEEK?" is unambiguously a planting question, but
        // without this guard it gets reclassified as a weather forecast
        // request purely because it contains "this week"; "how do you
        // PREDICT WEATHER?" is a meta/about-the-system question, not an
        // actual prediction request, but contains a phrase the cascade
        // treats as a weather-prediction trigger.
        $protectedFromCascade = [
            self::PLANTING_RECOMMENDATION,
            self::IRRIGATION_RECOMMENDATION,
            self::SYSTEM_HELP,
            self::PROFILE_ACCOUNT,
            self::COMMUNITY,
            self::REPORTS,
        ];

        if (! in_array($intent, $protectedFromCascade, true)) {
            if ($this->looksLikePlantingAction($text)) {
                $intent = self::PLANTING_RECOMMENDATION;
            } elseif ($this->looksLikeIrrigationAction($text)) {
                $intent = self::IRRIGATION_RECOMMENDATION;
            } elseif ($this->looksLikeAmbiguousPrediction($text)) {
                $intent = self::UNKNOWN;
            } elseif ($this->looksLikeYieldPrediction($text)) {
                $intent = self::RICE_YIELD_PREDICTION;
            } elseif ($this->looksLikeRisk($text)) {
                $intent = self::CLIMATE_RISK;
            } elseif ($this->looksLikeCurrentWeather($text) || $this->asksAboutConditionsWithoutPeriod($text)) {
                $intent = self::CURRENT_WEATHER;
            } elseif ($this->looksLikeApiForecast($text)) {
                $intent = self::WEATHER_FORECAST;
            } elseif ($this->looksLikeMonthlyModelPrediction($text)) {
                $intent = self::WEATHER_PREDICTION;
            } elseif ($this->looksLikeAdvisory($text)) {
                $intent = self::WEATHER_ADVISORY;
            }
        }

        $pendingIntent = (string) data_get($memory, 'pending_intent', '');
        if ($pendingIntent !== '' && $this->extractArea($question, allowBareNumber: true) !== null) {
            $intent = $pendingIntent;
        }

        return [
            'intent' => $intent,
            'confidence' => ((float) ($detected['confidence'] ?? 65)) / 100,
            'entities' => $this->normalizeEntities([], $question, $pendingIntent !== ''),
            'source' => 'local_semantic_fallback',
            'missing' => [],
            'requires_clarification' => $intent === self::UNKNOWN && $this->containsPredictionWord($text),
        ];
    }

    private function mapLegacyIntent(string $legacyIntent, string $text): string
    {
        return match ($legacyIntent) {
            IntentDetectionService::WEATHER_PREDICTION => self::WEATHER_PREDICTION,
            IntentDetectionService::RICE_YIELD_PREDICTION => self::RICE_YIELD_PREDICTION,
            IntentDetectionService::PLANTING_RECOMMENDATION => self::PLANTING_RECOMMENDATION,
            IntentDetectionService::IRRIGATION_RECOMMENDATION => self::IRRIGATION_RECOMMENDATION,
            IntentDetectionService::CLIMATE_RISK => self::CLIMATE_RISK,
            IntentDetectionService::FARMING_ADVISORY => $this->looksLikeAdvisory($text) ? self::WEATHER_ADVISORY : self::GENERAL_CHAT,
            IntentDetectionService::ANNOUNCEMENT => self::COMMUNITY,
            IntentDetectionService::NOTIFICATION => self::SYSTEM_HELP,
            IntentDetectionService::CALENDAR => self::SYSTEM_HELP,
            IntentDetectionService::USER_PROFILE => self::PROFILE_ACCOUNT,
            IntentDetectionService::SYSTEM_HELP => self::SYSTEM_HELP,
            IntentDetectionService::BARANGAY_INFO => self::CLIMATE_RISK,
            IntentDetectionService::MAO_REPORTS => self::REPORTS,
            IntentDetectionService::IT_SYSTEM_STATUS => self::SYSTEM_HELP,
            default => self::GENERAL_CHAT,
        };
    }

    private function looksLikeCurrentWeather(string $text): bool
    {
        return str($text)->contains([
            'right now',
            'now',
            'current weather',
            'current temperature',
            'weather today',
            'temperature today',
            'humidity now',
            'is it raining',
            'umuulan ba ngayon',
            'panahon ngayon',
        ]);
    }

    /**
     * A plain question about the weather/conditions ("What is the weather in San Diego?", "Kamusta ang panahon
     * sa San Diego?", "Umuulan ba sa San Diego?") with no future period is about the weather now. Wording with
     * a short-term period (tomorrow, later, next week) stays a forecast, a month or "monthly" stays the monthly
     * prediction, and advisory/warning questions stay advisories.
     */
    private function asksAboutConditionsWithoutPeriod(string $text): bool
    {
        $asksAboutConditions = preg_match('/\b(?:what|how)(?:\s+is|[\'’]s|s)?\s+(?:the\s+)?(?:weather|temperature|humidity|panahon)\b/u', $text) === 1
            || preg_match('/^\W*(?:the\s+)?weather\s+(?:in|at|sa|for)\b/u', $text) === 1
            || preg_match('/\b(?:kamusta|kumusta|ano)\s+(?:na\s+)?(?:ang\s+)?panahon\b/u', $text) === 1
            || preg_match('/\b(?:umuulan|mainit|maulan|mahangin|maalinsangan|malamig)\s+ba\b/u', $text) === 1
            || preg_match('/\bis\s+it\s+(?:raining|hot|humid|windy|sunny|cloudy)\b/u', $text) === 1;

        return $asksAboutConditions
            && ! $this->looksLikeApiForecast($text)
            && ! $this->looksLikeMonthlyModelPrediction($text)
            && ! $this->looksLikeAdvisory($text);
    }

    private function looksLikeRisk(string $text): bool
    {
        return str($text)->contains(['risk', 'high risk', 'severe risk', 'heat map', 'heatmap', 'panganib']);
    }

    private function looksLikeApiForecast(string $text): bool
    {
        return str($text)->contains([
            'tomorrow',
            'later',
            'next week',
            'next few days',
            'mamaya',
            'this week',
            'weekend',
            '7-day',
            '7 day',
            'forecast',
            'bukas',
            'linggo',
        ]) && ! $this->looksLikeMonthlyModelPrediction($text);
    }

    private function looksLikeMonthlyModelPrediction(string $text): bool
    {
        return str($text)->contains([
            'predict rainfall',
            'rainfall prediction',
            'predict weather',
            'weather prediction',
            'predict next month',
            'what rainfall does the model predict',
            'use the weather prediction model',
            'what does iclimate predict for rainfall',
            'next month',
            'monthly',
            'trained model',
            'model predict',
            'model estimate',
            'september',
            'october',
            'november',
            'december',
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'susunod na buwan',
        ]);
    }

    private function looksLikeYieldPrediction(string $text): bool
    {
        return str($text)->contains([
            'predict my rice yield',
            'predict rice yield',
            'rice yield',
            'yield for',
            'yield',
            'tons per hectare',
            'tons/ha',
            'estimate my harvest',
            'estimate my yield',
            'estimate my production',
            'harvest',
            'production',
            'ani',
            'aanihin',
        ]);
    }

    /**
     * An explicit "should I plant" style question is unambiguous even when
     * it's also full of supporting weather numbers (rainfall/temperature/
     * humidity) that would otherwise outweigh it in the legacy detector's
     * keyword scoring, or contains an incidental forecast-lookalike phrase
     * like "this week". Checked before every other cascade branch.
     */
    private function looksLikePlantingAction(string $text): bool
    {
        return str($text)->contains(['should i plant', 'dapat ba akong magtanim', 'dapat bang magtanim']);
    }

    private function looksLikeIrrigationAction(string $text): bool
    {
        return str($text)->contains(['should i irrigate', 'dapat ba akong magpatubig', 'dapat bang magpatubig']);
    }

    private function looksLikeAmbiguousPrediction(string $text): bool
    {
        return $this->containsPredictionWord($text)
            && ! $this->looksLikeYieldPrediction($text)
            && ! $this->looksLikeMonthlyModelPrediction($text)
            && ! $this->looksLikeApiForecast($text)
            && ! $this->looksLikeCurrentWeather($text)
            && ! $this->looksLikeRisk($text);
    }

    private function containsPredictionWord(string $text): bool
    {
        return str($text)->contains(['predict', 'prediction', 'estimate', 'model']);
    }

    private function looksLikeAdvisory(string $text): bool
    {
        return str($text)->contains(['advisory', 'advisories', 'warning', 'alert', 'babala', 'pagasa']);
    }

    private function validIntent(string $intent): bool
    {
        return in_array($intent, self::SUPPORTED_INTENTS, true);
    }

    /**
     * @param  array<string, mixed>  $entities
     * @return array<string, mixed>
     */
    private function normalizeEntities(array $entities, string $question, bool $allowBareArea = false): array
    {
        if (($area = $this->extractArea($question, $allowBareArea)) !== null) {
            $entities['area'] = $area;
        }

        $text = str($question)->lower()->toString();

        foreach (LianBarangays::all() as $barangay) {
            if (str_contains($text, strtolower($barangay))) {
                $entities['barangay'] = $barangay;
                break;
            }
        }

        if (str_contains($text, 'wet season') || str_contains($text, 'tag-ulan')) {
            $entities['season'] = 'Wet';
        } elseif (str_contains($text, 'dry season') || str_contains($text, 'tag-init')) {
            $entities['season'] = 'Dry';
        }

        if (str_contains($text, 'next month') || str_contains($text, 'susunod na buwan')) {
            $entities['target_period'] = 'next_month';
        } elseif (str_contains($text, 'tomorrow') || str_contains($text, 'bukas')) {
            $entities['target_period'] = 'tomorrow';
        }

        return Arr::where($entities, fn ($value) => $value !== null && $value !== '');
    }

    private function extractArea(string $question, bool $allowBareNumber = false): ?float
    {
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*(?:ha|hectare|hectares|ektarya|hektarya)\b/i', $question, $match)) {
            return round((float) $match[1], 2);
        }

        if ($allowBareNumber && preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', $question, $match)) {
            return round((float) $match[1], 2);
        }

        return null;
    }
}
