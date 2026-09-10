<?php

namespace App\Services\AI;

use App\Models\AIChat;
use App\Models\ChatbotResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatbotResponseService
{
    private const SYNONYMS = [
        'weather' => ['forecast', 'rain', 'rainfall', 'ulan', 'temperature', 'humidity', 'climate'],
        'forecast' => ['weather', 'prediction', 'tomorrow', 'week', 'rainfall'],
        'rainfall' => ['rain', 'weather', 'precipitation', 'ulan', 'forecast'],
        'rice yield' => ['yield', 'harvest', 'production', 'palay', 'ani', 'tons per hectare', 'tons/ha'],
        'yield' => ['rice yield', 'harvest', 'production', 'ani'],
        'heat map' => ['heatmap', 'risk map', 'climate risk', 'risk', 'barangay risk'],
        'advisory' => ['advisories', 'warning', 'alert', 'recommendation', 'planting advisory', 'babala'],
        'farmer profile' => ['profile', 'farmer records', 'farmer information', 'account'],
        'climate record' => ['climate records', 'weather records', 'rainfall records', 'historical data'],
        'report' => ['reports', 'summary', 'export', 'print'],
        'login' => ['sign in', 'signin', 'password', 'account access', 'navigation'],
        'dashboard' => ['home', 'overview', 'main page', 'summary'],
        'prediction' => ['predict', 'forecast', 'estimate', 'model', 'random forest'],
        'planting' => ['rice planting', 'pagtatanim', 'magtanim', 'tanim', 'punla', 'transplant'],
        'irrigation' => ['patubig', 'tubig', 'dilig', 'diligan', 'water management'],
        'palay' => ['rice', 'rice yield', 'ani', 'harvest', 'production'],
        'ulan' => ['rain', 'rainfall', 'forecast ng ulan', 'weather', 'panahon'],
        'panahon' => ['weather', 'forecast', 'climate', 'ulan'],
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function answer(User $user, string $question, string $language, ?string $intent = null): ?array
    {
        $matches = $this->rankedMatches($user, $question, $language, $intent);
        /** @var ChatbotResponse|null $record */
        $record = $matches->first()['record'] ?? null;

        if (! $record) {
            return null;
        }

        $record->forceFill([
            'times_used' => $record->times_used + 1,
            'last_used_at' => now(),
        ])->save();

        $matchedKeywords = $matches->first()['matched_keywords'] ?? [];

        return [
            'answer' => $this->friendlyAnswer($record, $matchedKeywords, $language),
            'source_name' => 'Climora AI Knowledge',
            'confidence' => min(96, 72 + ((int) $matches->first()['score'] * 3)),
            'chatbot_response_id' => $record->id,
            'matched_keywords' => $matchedKeywords,
            'intent' => $intent ?: Str::headline($record->category),
            'suggested_questions' => $this->suggestedQuestions($user, $language),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestPredictionAnswer(User $user, string $question, string $language): ?array
    {
        $topic = $this->predictionTopic($question);

        if (! $topic || ! $this->asksForExistingResult($question)) {
            return null;
        }

        $column = match ($topic) {
            'weather' => 'weather_prediction',
            'yield' => 'rice_yield_prediction',
            'advisory', 'risk' => 'warnings',
        };

        $chat = AIChat::query()
            ->where('user_id', $user->id)
            ->whereNotNull($column)
            ->latest()
            ->first();

        if (! $chat) {
            return [
                'intent' => $topic === 'yield' ? 'rice_yield_prediction' : ($topic === 'weather' ? 'weather_prediction' : 'weather_advisory'),
                'answer' => $this->missingPredictionMessage($topic, $language),
                'source_type' => 'Prediction Records',
                'source_name' => 'iClimate Prediction History',
                'source_url' => null,
                'source_credit' => 'Source: iClimate Prediction History',
                'language' => $language,
                'memory' => [],
                'prediction_result' => ['available' => false, 'topic' => $topic, 'suggested_questions' => $this->suggestedQuestions($user, $language)],
                'weather_prediction' => null,
                'rice_yield_prediction' => null,
                'planting_recommendation' => null,
                'irrigation_recommendation' => null,
                'warnings' => [],
                'explanation' => 'No saved prediction output was found for this user and topic.',
                'confidence_score' => 88,
                'response_time_ms' => 0,
            ];
        }

        return [
            'intent' => (string) $chat->intent,
            'answer' => $this->formatPredictionRecord($topic, $chat, $language, $this->wantsTechnicalDetails($question)),
            'source_type' => 'Prediction Records',
            'source_name' => 'iClimate Prediction History',
            'source_url' => null,
            'source_credit' => 'Source: iClimate Prediction History',
            'language' => $language,
            'memory' => [],
            'prediction_result' => [
                'available' => true,
                'topic' => $topic,
                'chat_id' => $chat->id,
                'created_at' => $chat->created_at?->toDateTimeString(),
                'suggested_questions' => $this->suggestedQuestions($user, $language),
            ],
            'weather_prediction' => $chat->weather_prediction,
            'rice_yield_prediction' => $chat->rice_yield_prediction,
            'planting_recommendation' => $chat->planting_recommendation,
            'irrigation_recommendation' => $chat->irrigation_recommendation,
            'warnings' => $chat->warnings ?? [],
            'explanation' => 'Displayed from the latest saved iClimate prediction response.',
            'confidence_score' => 92,
            'response_time_ms' => 0,
        ];
    }

    public function fallbackMessage(User $user, string $language = 'English'): string
    {
        $tagalog = $this->isFilipinoLike($language);
        $topics = match ($user->role) {
            User::ROLE_MAO => 'dashboard, weather prediction, rice yield prediction, heat map areas, farmer profiles, climate records, advisories, reports, and announcements',
            User::ROLE_IT_EXPERT => 'dashboard, reports, user management, system logs, chatbot responses, weather prediction, climate records, heat map areas, and login/navigation',
            default => 'dashboard, weather forecast, rice yield prediction, heat map risk, advisories, farmer profile, climate records, reports, and login/navigation',
        };

        if ($tagalog) {
            return 'Wala pa akong naka-save na sagot sa tanong na iyon. Maaari mong itanong ang tungkol sa dashboard, panahon, ulan, ani ng palay, heat map, advisory, farmer profile, climate records, reports, o login/navigation.';
        }

        return "I do not have a saved iClimate answer for that yet. You can ask me about {$topics}. Try: \"How do I use the dashboard?\", \"Show my latest rice yield prediction\", or \"What does the heat map mean?\"";
    }

    /**
     * @return array<int, string>
     */
    public function suggestedQuestions(User $user, string $language): array
    {
        if ($this->isFilipinoLike($language)) {
            return match ($user->role) {
                User::ROLE_MAO => ['Paano gamitin ang dashboard?', 'Nasaan ang farmer profiles?', 'Paano gumawa ng report?', 'Ano ang heat map?'],
                User::ROLE_IT_EXPERT => ['Paano i-update ang chatbot answers?', 'Nasaan ang system logs?', 'Paano mag-manage ng users?', 'May latest weather prediction ba?'],
                default => ['Paano gamitin ang dashboard?', 'May forecast ba ng ulan?', 'Ano ang predicted rice yield ko?', 'Ano ang ibig sabihin ng heat map?'],
            };
        }

        return match ($user->role) {
            User::ROLE_MAO => ['How do I use the dashboard?', 'Where are farmer profiles?', 'How do reports work?', 'What does the heat map show?'],
            User::ROLE_IT_EXPERT => ['How do I update chatbot answers?', 'Where are system logs?', 'How do I manage users?', 'Show latest weather prediction.'],
            default => ['How do I use the dashboard?', 'Will it rain this week?', 'Show my latest rice yield prediction.', 'What does the heat map mean?'],
        };
    }

    /**
     * @return Collection<int, array{record: ChatbotResponse, score: int, matched_keywords: array<int, string>}>
     */
    private function rankedMatches(User $user, string $question, string $language, ?string $intent): Collection
    {
        $questionText = $this->normalize($question);
        $questionTokens = $this->tokens($questionText);

        return ChatbotResponse::query()
            ->active()
            ->latest('updated_at')
            ->get()
            ->filter(fn (ChatbotResponse $record): bool => $this->visibleTo($record, $user))
            ->map(function (ChatbotResponse $record) use ($questionText, $questionTokens, $intent, $language): array {
                $matched = [];
                $score = 0;

                foreach ($this->expandedKeywords([...(array) ($record->keywords ?? []), ...(array) ($record->synonyms ?? [])]) as $keyword) {
                    $keywordText = $this->normalize($keyword);

                    if ($keywordText === '') {
                        continue;
                    }

                    if ($questionText === $keywordText) {
                        $score += 20;
                        $matched[] = $keyword;
                    } elseif (str_contains($questionText, $keywordText)) {
                        $score += 8 + count($this->tokens($keywordText));
                        $matched[] = $keyword;
                    } elseif (str_contains($keywordText, $questionText) && strlen($questionText) >= 4) {
                        $score += 5;
                        $matched[] = $keyword;
                    } elseif ($this->tokens($keywordText)->intersect($questionTokens)->isNotEmpty()) {
                        $score += $this->tokens($keywordText)->intersect($questionTokens)->count();
                    } elseif ($this->closeTokenMatch($questionTokens, $this->tokens($keywordText))) {
                        $score += 2;
                        $matched[] = $keyword;
                    }
                }

                if ($this->sameLanguage($record->language ?? 'English', $language)) {
                    $score += 5;
                } elseif ($this->compatibleLanguage($record->language ?? 'English', $language)) {
                    $score += 2;
                }

                if ($intent && $this->normalize($record->category) === $this->normalize($intent)) {
                    $score += 3;
                }

                if (($record->visible_roles ?? []) !== []) {
                    $score += 2;
                }

                return [
                    'record' => $record,
                    'score' => $score,
                    'matched_keywords' => array_values(array_unique($matched)),
                ];
            })
            ->filter(fn (array $match): bool => $match['score'] >= 3)
            ->sortByDesc(fn (array $match): int => ((int) $match['score'] * 100_000) + (int) $match['record']->times_used)
            ->values();
    }

    private function friendlyAnswer(ChatbotResponse $record, array $matchedKeywords, string $language): string
    {
        $answer = $this->adaptAnswer($record, $language);

        if (Str::startsWith(Str::lower(trim($answer)), ['sagot:', 'answer:'])) {
            return $answer;
        }

        if ($this->isFilipinoLike($language)) {
            $prefix = "Tungkol sa {$record->category}:";
            $hint = $matchedKeywords === [] ? '' : "\n\nPaksa: ".implode(', ', array_slice($matchedKeywords, 0, 3));

            return $prefix."\n".$answer.$hint;
        }

        $prefix = "For {$record->category}:";
        $hint = $matchedKeywords === [] ? '' : "\n\nTopic: ".implode(', ', array_slice($matchedKeywords, 0, 3));

        return $prefix."\n".$answer.$hint;
    }

    private function visibleTo(ChatbotResponse $record, User $user): bool
    {
        $roles = $record->visible_roles ?? [];

        return $roles === [] || in_array($user->role, $roles, true);
    }

    private function expandedKeywords(array $keywords): array
    {
        $expanded = [];

        foreach ($keywords as $keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                continue;
            }

            $expanded[] = $keyword;
            foreach (self::SYNONYMS[$this->normalize($keyword)] ?? [] as $synonym) {
                $expanded[] = $synonym;
            }
        }

        return array_values(array_unique($expanded));
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower($value)) ?? '');
    }

    /**
     * @return Collection<int, string>
     */
    private function tokens(string $value): Collection
    {
        return collect(preg_split('/[^\pL\pN]+/u', $this->normalize($value)) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 3)
            ->reject(fn (string $token): bool => in_array($token, ['what', 'when', 'where', 'about', 'with', 'from', 'does', 'this', 'that', 'how'], true))
            ->values();
    }

    private function asksForExistingResult(string $question): bool
    {
        $text = $this->normalize($question);

        return Str::contains($text, ['latest', 'recent', 'available', 'last result', 'saved prediction', 'prediction result', 'show result']);
    }

    private function predictionTopic(string $question): ?string
    {
        $text = $this->normalize($question);

        if (Str::contains($text, ['rice yield', 'yield', 'harvest', 'production', 'ani'])) {
            return 'yield';
        }

        if (Str::contains($text, ['weather', 'rainfall', 'forecast', 'rain', 'ulan'])) {
            return 'weather';
        }

        if (Str::contains($text, ['advisory', 'advisories', 'warning', 'risk', 'heat map', 'heatmap'])) {
            return Str::contains($text, ['risk', 'heat map', 'heatmap']) ? 'risk' : 'advisory';
        }

        return null;
    }

    private function missingPredictionMessage(string $topic, string $language): string
    {
        if ($this->isFilipinoLike($language)) {
            return match ($topic) {
                'yield' => 'Wala pang naka-save na rice yield prediction. Buksan ang Weather & Yield o itanong muli kasama ang farm area para makagawa ang system ng record.',
                'weather' => 'Wala pang naka-save na weather prediction result. Buksan ang Weather Prediction o magtanong ng rainfall/weather prediction para makakuha ng result mula sa system.',
                'risk' => 'Wala pang naka-save na risk warning result. Buksan ang Heat Map o itanong ang risk level ng barangay.',
                default => 'Wala pang naka-save na advisory result. Buksan ang Planting Advisories o itanong ang active advisories sa area mo.',
            };
        }

        return match ($topic) {
            'yield' => 'No saved rice yield prediction is available yet. Open Weather & Yield or ask Climora to predict rice yield with your farm area, then I can show the recorded result.',
            'weather' => 'No saved weather prediction result is available yet. Open Weather Prediction or ask Climora for a weather/rainfall prediction so the system can generate one from existing services.',
            'risk' => 'No saved risk warning result is available yet. Open the Heat Map area or ask about a barangay risk level to check current iClimate risk records.',
            default => 'No saved advisory result is available yet. Open Planting Advisories or ask Climora for active advisories in your area.',
        };
    }

    private function formatPredictionRecord(string $topic, AIChat $chat, string $language, bool $technical): string
    {
        $date = $chat->created_at?->format('M d, Y h:i A') ?? 'Unknown date';
        $tagalog = $this->isFilipinoLike($language);

        if ($topic === 'yield') {
            $yield = $chat->rice_yield_prediction ?? [];
            $value = data_get($yield, 'predicted_yield');
            $total = data_get($yield, 'estimated_total_production_tons');

            $lines = $tagalog
                ? ["Latest na rice yield prediction ({$date}):", 'Predicted yield: '.($value !== null ? number_format((float) $value, 2).' tons/hectare' : 'Walang available'), 'Estimated total production: '.($total !== null ? number_format((float) $total, 2).' tons' : 'Walang available')]
                : ["Latest saved rice yield prediction ({$date}):", 'Predicted yield: '.($value !== null ? number_format((float) $value, 2).' tons/hectare' : 'Not available'), 'Estimated total production: '.($total !== null ? number_format((float) $total, 2).' tons' : 'Not available')];

            if ($technical) {
                $lines[] = 'Source: '.(data_get($yield, 'source_name') ?: $chat->source_name ?: 'iClimate prediction service');
                $lines[] = 'Explanation: '.$chat->explanation;
            }

            return implode("\n", $lines);
        }

        if ($topic === 'weather') {
            $weather = $chat->weather_prediction ?? [];

            $lines = $tagalog
                ? ["Latest na weather prediction ({$date}):", 'Panahon: '.(data_get($weather, 'predicted_weather') ?: data_get($weather, 'condition') ?: 'Walang available'), 'Ulan: '.$this->valueWithUnit(data_get($weather, 'rainfall'), 'mm'), 'Temperatura: '.$this->valueWithUnit(data_get($weather, 'temperature'), 'C'), 'Humidity: '.$this->valueWithUnit(data_get($weather, 'humidity'), '%')]
                : ["Latest saved weather prediction ({$date}):", 'Weather: '.(data_get($weather, 'predicted_weather') ?: data_get($weather, 'condition') ?: 'Not available'), 'Rainfall: '.$this->valueWithUnit(data_get($weather, 'rainfall'), 'mm'), 'Temperature: '.$this->valueWithUnit(data_get($weather, 'temperature'), 'C'), 'Humidity: '.$this->valueWithUnit(data_get($weather, 'humidity'), '%')];

            if ($technical) {
                $lines[] = 'Source: '.($chat->source_name ?: 'iClimate prediction service');
            }

            return implode("\n", $lines);
        }

        $warnings = collect($chat->warnings ?? []);
        $summary = $warnings->isEmpty()
            ? 'No saved warning details were attached to that response.'
            : $warnings->map(fn ($warning) => data_get($warning, 'title', data_get($warning, 'reason', 'Warning')))->take(4)->implode('; ');

        $label = $tagalog ? 'Latest na advisory o risk result' : 'Latest saved advisory or risk result';
        $source = $technical ? "\nSource: ".($chat->source_name ?: 'iClimate records') : '';

        return "{$label} ({$date}):\n{$summary}{$source}";
    }

    private function valueWithUnit(mixed $value, string $unit): string
    {
        return $value !== null && $value !== '' ? $value.' '.$unit : 'Not available';
    }

    private function closeTokenMatch(Collection $questionTokens, Collection $keywordTokens): bool
    {
        foreach ($questionTokens as $questionToken) {
            foreach ($keywordTokens as $keywordToken) {
                if ($questionToken[0] === $keywordToken[0] && abs(strlen($questionToken) - strlen($keywordToken)) <= 2 && levenshtein($questionToken, $keywordToken) <= 2) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sameLanguage(string $recordLanguage, string $userLanguage): bool
    {
        return $this->languageFamily($recordLanguage) === $this->languageFamily($userLanguage);
    }

    private function compatibleLanguage(string $recordLanguage, string $userLanguage): bool
    {
        return in_array($this->languageFamily($recordLanguage), ['Filipino', 'Taglish'], true)
            && in_array($this->languageFamily($userLanguage), ['Filipino', 'Taglish'], true);
    }

    private function languageFamily(string $language): string
    {
        $language = Str::lower($language);

        if (Str::contains($language, ['mixed', 'taglish'])) {
            return 'Taglish';
        }

        if (Str::contains($language, ['tagalog', 'filipino'])) {
            return 'Filipino';
        }

        return 'English';
    }

    private function isFilipinoLike(string $language): bool
    {
        return in_array($this->languageFamily($language), ['Filipino', 'Taglish'], true);
    }

    private function adaptAnswer(ChatbotResponse $record, string $language): string
    {
        if ($this->sameLanguage($record->language ?? 'English', $language) || ! $this->isFilipinoLike($language)) {
            return (string) $record->answer;
        }

        return match (Str::lower($record->category)) {
            'dashboard help' => 'Makikita sa dashboard ang buod ng tools na available sa role mo, gaya ng weather, advisories, records, heat map, reports, at profile shortcuts.',
            'weather prediction' => 'Buksan ang Weather Prediction o Weather & Yield para makita ang forecast at rainfall results na galing sa iClimate system.',
            'rice yield prediction' => 'Para sa rice yield prediction, ibigay ang farm area o gamitin ang Weather & Yield page. Ipapakita lang ni Climora ang result na mayroon sa system.',
            'heat map' => 'Ipinapakita ng Heat Map ang climate risk kada barangay, kasama ang risk level, risk type, rainfall status, at rekomendasyon kung available.',
            'advisories' => 'Ang advisories ay gabay tungkol sa pagtatanim, patubig, ulan, init, bagyo, tagtuyot, at iba pang kondisyon sa bukid.',
            'farmer profiles' => 'Ang farmer profile ay record ng farmer at farm details tulad ng barangay, farm area, farm type, contact, at organization.',
            'climate records' => 'Ang climate records ay tala ng ulan, temperatura, humidity, hangin, season, source, at petsa na ginagamit sa reports at predictions.',
            'reports' => 'Sa Reports page, ang authorized users ay puwedeng gumawa, mag-filter, mag-print, at mag-export ng summaries.',
            'login and navigation' => 'Mag-login gamit ang registered email at password. Pagkatapos, gamitin ang sidebar para buksan ang tools na allowed sa role mo.',
            default => (string) $record->answer,
        };
    }

    public function wantsTechnicalDetails(string $question): bool
    {
        return Str::contains($this->normalize($question), [
            'explain',
            'details',
            'debug',
            'technical',
            'source',
            'model input',
            'api error',
            'logs',
            'why',
            'paano kinalculate',
            'detalye',
            'show prediction',
            'prediction ngayon',
            'ano ang prediction',
            'weather forecast',
            'weather prediction',
            'rice yield prediction',
            'yield prediction',
            'explain the risk',
            'risk details',
        ]);
    }
}
