<?php

namespace App\Services\AI;

use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Support\Arr;

class IntentClassifierService
{
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
     * @return array{intent:string,confidence:float,entities:array<string,mixed>,source:string,missing:array<int,string>,requires_clarification:bool}
     */
    public function classify(User $user, string $question, string $language, array $memory = []): array
    {
        if ($preflight = $this->preflight($question)) {
            return $preflight;
        }

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

        return $this->fallbackClassification($question, $memory);
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

        if (str($text)->contains([
            'tagalog',
            'filipino',
            'pilipino',
            'english',
            'ingles',
            'who are you',
            'what are you',
            'sino ka',
            'ano ka',
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

        if ($this->looksLikeAmbiguousPrediction($text)) {
            $intent = self::UNKNOWN;
        } elseif ($this->looksLikeYieldPrediction($text)) {
            $intent = self::RICE_YIELD_PREDICTION;
        } elseif ($this->looksLikeRisk($text)) {
            $intent = self::CLIMATE_RISK;
        } elseif ($this->looksLikeCurrentWeather($text)) {
            $intent = self::CURRENT_WEATHER;
        } elseif ($this->looksLikeApiForecast($text)) {
            $intent = self::WEATHER_FORECAST;
        } elseif ($this->looksLikeMonthlyModelPrediction($text)) {
            $intent = self::WEATHER_PREDICTION;
        } elseif ($this->looksLikeAdvisory($text)) {
            $intent = self::WEATHER_ADVISORY;
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

    private function looksLikeRisk(string $text): bool
    {
        return str($text)->contains(['risk', 'high risk', 'severe risk', 'heat map', 'heatmap', 'panganib']);
    }

    private function looksLikeApiForecast(string $text): bool
    {
        return str($text)->contains([
            'tomorrow',
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
