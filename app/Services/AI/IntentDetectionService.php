<?php

namespace App\Services\AI;

class IntentDetectionService
{
    public const WEATHER_PREDICTION = 'Weather Prediction';
    public const RICE_YIELD_PREDICTION = 'Rice Yield Prediction';
    public const PLANTING_RECOMMENDATION = 'Planting Recommendation';
    public const IRRIGATION_RECOMMENDATION = 'Irrigation Recommendation';
    public const CLIMATE_RISK = 'Climate Risk';
    public const FARMING_ADVISORY = 'Farming Advisory';
    public const ANNOUNCEMENT = 'Announcement';
    public const NOTIFICATION = 'Notification';
    public const CALENDAR = 'Calendar';
    public const USER_PROFILE = 'User Profile';
    public const SYSTEM_HELP = 'System Help';
    public const GENERAL_AGRICULTURE = 'General Agriculture';
    public const GENERAL_CONVERSATION = 'General Conversation';
    public const GENERAL_KNOWLEDGE = 'General Knowledge';

    public function detect(string $question, array $memory = []): array
    {
        $text = str($question)->lower()->toString();

        if ($this->isMethodQuestion($text)) {
            return [
                'intent' => self::SYSTEM_HELP,
                'confidence' => 92,
                'requires_prediction' => false,
            ];
        }

        $scores = [
            self::WEATHER_PREDICTION => $this->score($text, ['rain', 'rainfall', 'weather', 'forecast', 'ulan', 'uulan', 'maulan', 'bagyo', 'storm', 'wind', 'hangin', 'humidity', 'temperature', 'will it rain']),
            self::RICE_YIELD_PREDICTION => $this->score($text, ['yield', 'harvest', 'production', 'ani', 'aanihin', 'anihin', 'tons', 'tonelada', 'palay', 'expected yield']),
            self::PLANTING_RECOMMENDATION => $this->score($text, ['plant', 'planting', 'transplant', 'seedling', 'sow', 'punla', 'tanim', 'magtanim', 'itanim']) + (str_contains($text, 'should i plant') ? 2 : 0) + (str_contains($text, 'magtanim') ? 3 : 0),
            self::IRRIGATION_RECOMMENDATION => $this->score($text, ['irrigat', 'water', 'tubig', 'patubig', 'dilig', 'diligan', 'canal', 'rainfed', 'irrigated']) + (str_contains($text, 'should i irrigate') ? 3 : 0),
            self::CLIMATE_RISK => $this->score($text, ['risk', 'warning', 'babala', 'drought', 'flood', 'heat', 'tagtuyot', 'baha', 'init', 'water shortage']),
            self::FARMING_ADVISORY => $this->score($text, ['advisory', 'fertilizer', 'soil', 'nitrogen', 'urea', 'compost', 'abono', 'pataba', 'pest', 'disease', 'sakit', 'peste']),
            self::ANNOUNCEMENT => $this->score($text, ['announcement', 'announcements', 'anunsyo', 'post', 'community feed']),
            self::NOTIFICATION => $this->score($text, ['notification', 'notifications', 'alert', 'mark read', 'unread']),
            self::CALENDAR => $this->score($text, ['calendar', 'schedule', 'planting calendar', 'date', 'month']),
            self::USER_PROFILE => $this->score($text, ['profile', 'account', 'barangay', 'farm area', 'contact number', 'password', 'reset password']),
            self::SYSTEM_HELP => $this->score($text, ['register', 'login', 'log in', 'logout', 'how do i', 'help', 'feedback', 'dashboard', 'iclimate']),
            self::GENERAL_AGRICULTURE => $this->score($text, ['rice', 'palay', 'farm', 'farmer', 'crop', 'agriculture', 'bukid', 'sakahan']),
            self::GENERAL_CONVERSATION => $this->score($text, ['hello', 'hi', 'kumusta', 'salamat', 'thanks', 'good morning']),
            self::GENERAL_KNOWLEDGE => 1,
        ];

        arsort($scores);
        $intent = (string) array_key_first($scores);
        $score = (int) ($scores[$intent] ?? 0);

        if ($score <= 1 && str($text)->contains(['it', 'that', 'this', 'also', 'what about', 'paano naman'])) {
            $intent = $this->normalizeLegacyIntent((string) ($memory['last_intent'] ?? $intent));
        }

        return [
            'intent' => $intent,
            'confidence' => $this->confidence($score),
            'requires_prediction' => $this->requiresPrediction($intent, $text),
        ];
    }

    public function requiresPrediction(string $intent, string $question): bool
    {
        $text = str($question)->lower()->toString();

        if ($this->isMethodQuestion($text)) {
            return false;
        }

        if (in_array($intent, [
            self::WEATHER_PREDICTION,
            self::RICE_YIELD_PREDICTION,
            self::PLANTING_RECOMMENDATION,
            self::IRRIGATION_RECOMMENDATION,
            self::CLIMATE_RISK,
        ], true)) {
            return true;
        }

        return str($text)->contains(['predict', 'will it rain', 'should i irrigate', 'should i plant', 'when should i plant', 'risk assessment', 'yield explanation', 'climate recommendation']);
    }

    public function detectLanguage(string $question): string
    {
        $text = str($question)->lower()->toString();
        $tagalog = $this->score($text, ['ang', 'ako', 'ba', 'kaba', 'bakit', 'dapat', 'gamit', 'kailan', 'kaya', 'kung', 'mag', 'magiging', 'mga', 'ng', 'paano', 'para', 'po', 'sa', 'tag-ulan', 'tag-init', 'tanim', 'tubig', 'ulan', 'uulan', 'ani', 'palay', 'bukas', 'ngayon']);
        $english = $this->score($text, ['the', 'is', 'are', 'should', 'when', 'how', 'what', 'why', 'farm', 'rice', 'rain', 'yield']);

        if ($tagalog > 0 && $tagalog >= $english) {
            return 'Tagalog';
        }

        if ($tagalog > 0 && $english > 0) {
            return 'Mixed English and Tagalog';
        }

        return $tagalog > $english ? 'Tagalog' : 'English';
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

    private function confidence(int $score): float
    {
        return round(max(45, min(96, 48 + ($score * 12))), 2);
    }

    private function normalizeLegacyIntent(string $intent): string
    {
        return match ($intent) {
            'planting' => self::PLANTING_RECOMMENDATION,
            'irrigation' => self::IRRIGATION_RECOMMENDATION,
            'yield' => self::RICE_YIELD_PREDICTION,
            'weather' => self::WEATHER_PREDICTION,
            'warning' => self::CLIMATE_RISK,
            'fertilizer', 'pest_disease' => self::FARMING_ADVISORY,
            default => $intent,
        };
    }

    private function isMethodQuestion(string $text): bool
    {
        return str($text)->contains([
            'how do you predict',
            'how does iclimate predict',
            'how does the system predict',
            'how is weather predicted',
            'how are predictions made',
            'explain weather prediction',
            'explain rice yield prediction',
            'paano ka mag predict',
            'paano nag predict',
            'paano mo pinipredict',
            'paano mo hinuhulaan',
            'paano hinuhulaan',
        ]);
    }
}
