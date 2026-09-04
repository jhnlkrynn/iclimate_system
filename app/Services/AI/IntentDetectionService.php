<?php

namespace App\Services\AI;

use App\Support\LianBarangays;

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

    public const BARANGAY_INFO = 'Barangay Info';

    public const MAO_REPORTS = 'MAO Reports';

    public const IT_SYSTEM_STATUS = 'IT System Status';

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
            self::RICE_YIELD_PREDICTION => $this->score($text, ['yield', 'harvest', 'production', 'ani', 'aanihin', 'anihin', 'tons', 'tonelada', 'palay', 'expected yield'])
                + (str_contains($text, 'predicted rice yield') ? 6 : 0)
                + (str_contains($text, 'predicted yield') ? 3 : 0)
                + (str_contains($text, 'rice yield') ? 3 : 0)
                + (str_contains($text, 'my rice yield') ? 3 : 0),
            self::PLANTING_RECOMMENDATION => $this->score($text, ['plant', 'planting', 'transplant', 'seedling', 'sow', 'punla', 'tanim', 'magtanim', 'itanim']) + (str_contains($text, 'should i plant') ? 2 : 0) + (str_contains($text, 'magtanim') ? 3 : 0),
            self::IRRIGATION_RECOMMENDATION => $this->score($text, ['irrigat', 'water', 'tubig', 'patubig', 'dilig', 'diligan', 'canal', 'rainfed', 'irrigated']) + (str_contains($text, 'should i irrigate') ? 3 : 0),
            self::CLIMATE_RISK => $this->score($text, ['risk', 'warning', 'babala', 'drought', 'flood', 'heat', 'tagtuyot', 'baha', 'init', 'water shortage']) - (str_contains($text, 'heat map') || str_contains($text, 'heatmap') ? 1 : 0),
            self::FARMING_ADVISORY => $this->score($text, [
                'advisory', 'fertilizer', 'soil', 'nitrogen', 'urea', 'compost', 'abono', 'pataba', 'pest', 'disease', 'sakit', 'peste',
                'complete fertilizer', 'organic fertilizer', 'potassium', 'phosphorus', 'npk', 'basal application', 'topdress', 'soil ph', 'soil test',
                'water retention', 'soil moisture', 'soil fertility', 'soil type',
                'brown planthopper', 'stem borer', 'rice bug', 'leaf folder', 'armyworm', 'putakti', 'uod', 'tipaklong',
                'rice blast', 'bacterial leaf blight', 'sheath blight', 'tungro',
                'yellow leaves', 'yellowing', 'turning yellow', 'dilaw na dahon', 'namumula', 'wilting', 'lanta',
                'holes in leaves', 'butas ng dahon', 'spots on leaves', 'batik sa dahon', 'stunted growth', 'hindi lumalaki',
                'insects on my rice', 'may insekto', 'namamatay ang palay',
            ]) + (str_contains($text, 'yellow') ? 2 : 0),
            self::ANNOUNCEMENT => $this->score($text, ['announcement', 'announcements', 'anunsyo', 'post', 'community feed']),
            self::NOTIFICATION => $this->score($text, ['notification', 'notifications', 'alert', 'mark read', 'unread']),
            self::CALENDAR => $this->score($text, ['calendar', 'schedule', 'planting calendar', 'date', 'month']),
            self::USER_PROFILE => $this->score($text, ['profile', 'account', 'farm area', 'contact number', 'password', 'reset password']),
            self::SYSTEM_HELP => $this->score($text, [
                'register', 'login', 'log in', 'logout', 'how do i', 'help', 'feedback', 'dashboard', 'iclimate',
                'chart', 'graph', 'map legend', 'heat map', 'heatmap', 'how to read', 'explain the dashboard',
                'random forest', 'linear regression', 'gradient boosting', 'rmse', 'mae', 'r2', 'r-squared', 'confidence score',
                'how accurate', 'accuracy', 'algorithm', 'machine learning model', 'change password', 'update profile', 'forgot password',
                'who are you', 'who r u', 'what are you', 'sino ka', 'ano ka', 'tagalog', 'filipino',
            ]) + (str_contains($text, 'heat map') || str_contains($text, 'heatmap') ? 2 : 0),
            self::GENERAL_AGRICULTURE => $this->score($text, [
                'rice', 'palay', 'farm', 'farmer', 'crop', 'agriculture', 'bukid', 'sakahan',
                'growth stage', 'variety', 'varieties', 'psb rc', 'nsic rc',
                'el nino', 'la nina', 'el niño', 'la niña', 'climate change', 'seasonal pattern', 'rainfall trend', 'temperature trend',
                'typhoon', 'bagyo preparation', 'disaster preparedness', 'paghahanda sa bagyo', 'flood preparation',
                'rcef', 'philrice', 'palaycheck', 'da program', 'government program', 'gobyerno program', 'subsidy', 'binhi program',
            ]),
            self::GENERAL_CONVERSATION => $this->score($text, ['hello', 'hi', 'kumusta', 'salamat', 'thanks', 'good morning', 'sino ka']),
            self::BARANGAY_INFO => $this->score($text, [
                'barangay', 'brgy', 'which barangay', 'anong barangay',
                ...array_map(static fn (string $barangay): string => strtolower($barangay), LianBarangays::all()),
            ]),
            self::MAO_REPORTS => $this->score($text, [
                'generate report', 'production summary', 'annual report', 'yield report', 'weather report',
                'farmer report', 'highest yield', 'lowest yield', 'pinakamataas na ani', 'pinakamababang ani',
                'report generation', 'gumawa ng ulat', 'buod ng produksyon',
                'how many farmer', 'ilang farmer', 'registered farmer', 'farmer count', 'number of farmers',
            ]) + (str_contains($text, 'summary') ? 2 : 0) + (str_contains($text, 'report') ? 2 : 0) + (str_contains($text, 'farmer') && str_contains($text, 'many') ? 2 : 0),
            self::IT_SYSTEM_STATUS => $this->score($text, [
                'number of users', 'user count', 'how many users', 'database status', 'db status', 'api status',
                'server status', 'system status', 'backup', 'error log', 'maintenance mode', 'system maintenance',
                'ilang user', 'status ng database', 'status ng server',
                'database', 'db okay', 'db connected', 'server', 'is the api', 'api online', 'api working',
                'system online', 'system okay', 'recent error', 'any errors', 'maintenance',
            ]) + (str_contains($text, 'how many users') ? 3 : 0) + (str_contains($text, 'api') ? 2 : 0) + (str_contains($text, 'database') ? 2 : 0),
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

        return str($text)->contains(['will it rain', 'should i irrigate', 'should i plant', 'when should i plant', 'risk assessment', 'yield explanation', 'climate recommendation']);
    }

    public function detectLanguage(string $question, array $memory = []): string
    {
        $text = str($question)->lower()->toString();

        if ($explicitLanguage = $this->explicitLanguageRequest($text)) {
            return $explicitLanguage;
        }

        $recentQuestions = implode(' ', array_slice((array) ($memory['recent_questions'] ?? []), 0, 3));
        $recentText = str($recentQuestions)->lower()->toString();
        $preferredLanguage = $this->explicitLanguageRequest($recentText);

        if ($preferredLanguage && ! $this->explicitLanguageRequest($text)) {
            return $preferredLanguage;
        }

        $tagalog = $this->score($text, [
            'ang ', ' ako', 'akin', 'kaba', 'natin', 'niyo', 'sila', 'siya',
            'bakit', 'dapat', 'gamit', 'ikaw', 'ilang', 'kailan', 'kaya', 'kung', 'mag', 'magiging',
            'mga', 'paano', 'para', ' po', 'sino', 'ano', 'tag-ulan', 'tag-init',
            'tanim', 'tubig', 'ulan', 'uulan', 'ani', 'palay', 'bukas', 'ngayon',
        ]);
        $english = $this->score($text, ['the', 'is', 'are', 'should', 'when', 'how', 'what', 'why', 'farm', 'rice', 'rain', 'yield']);

        if ($tagalog > 0 && $tagalog >= $english) {
            return 'Tagalog';
        }

        if ($tagalog > 0 && $english > 0) {
            return 'Mixed English and Tagalog';
        }

        return $tagalog > $english ? 'Tagalog' : 'English';
    }

    private function explicitLanguageRequest(string $text): ?string
    {
        $languageMap = [
            'Tagalog' => ['tagalog', 'filipino', 'pilipino', 'magtagalog', 'mag tagalog', 'wikang tagalog', 'salitang tagalog'],
            'English' => ['english', 'ingles', 'in english'],
            'Cebuano/Bisaya' => ['cebuano', 'bisaya', 'binisaya'],
            'Ilocano' => ['ilocano', 'ilokano'],
            'Kapampangan' => ['kapampangan', 'pampango'],
            'Hiligaynon/Ilonggo' => ['hiligaynon', 'ilonggo'],
            'Bicolano' => ['bicolano', 'bikol'],
            'Waray' => ['waray'],
            'Spanish' => ['spanish', 'espanol', 'español'],
        ];

        foreach ($languageMap as $language => $keywords) {
            if (str($text)->contains($keywords)) {
                return $language;
            }
        }

        if (preg_match('/[\x{3040}-\x{30ff}]/u', $text)) {
            return 'Japanese';
        }

        if (preg_match('/[\x{ac00}-\x{d7af}]/u', $text)) {
            return 'Korean';
        }

        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            return 'Chinese';
        }

        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) {
            return 'Arabic';
        }

        return null;
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
            'how do you gather',
            'how does iclimate gather',
            'how does the system gather',
            'where do you get weather',
            'where does iclimate get weather',
            'where does the system get weather',
            'how do you get weather',
            'how do you collect weather',
            'how does iclimate collect weather',
            'weather data source',
            'weather source',
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
            'what algorithm',
            'how accurate is',
            'what is rmse',
            'what is mae',
            'what is r2',
            'what is r-squared',
            'explain the machine learning model',
            'why random forest',
            'why is random forest used',
            'how was my prediction calculated',
            'how is my prediction calculated',
            'prediction calculated',
            'how was this calculated',
        ]);
    }
}
