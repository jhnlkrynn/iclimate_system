<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use Illuminate\Support\Collection;

class KnowledgeBaseService
{
    public function search(string $question, string $intent): ?KnowledgeBase
    {
        $keywords = $this->keywords($question);
        $firstKeyword = $keywords[0] ?? '';

        /** @var Collection<int, KnowledgeBase> $records */
        $records = KnowledgeBase::query()
            ->where(function ($query) use ($intent, $firstKeyword): void {
                $query->where('category', $intent);

                if ($firstKeyword !== '') {
                    $query->orWhere('question', 'like', '%'.$firstKeyword.'%');
                }
            })
            ->latest('verified')
            ->latest('times_used')
            ->take(20)
            ->get();

        $best = null;
        $bestScore = 0;

        foreach ($records as $record) {
            $score = $this->similarity($keywords, $record);

            if ($score > $bestScore) {
                $best = $record;
                $bestScore = $score;
            }
        }

        if (! $best || $bestScore < 2) {
            return null;
        }

        $best->increment('times_used');

        return $best->refresh();
    }

    public function storeRetrieved(string $question, string $answer, string $intent, array $source, float $confidence = 70): KnowledgeBase
    {
        return KnowledgeBase::query()->create([
            'question' => $question,
            'answer' => $answer,
            'category' => $intent,
            'keywords' => $this->keywords($question),
            'source_type' => 'Online Retrieval',
            'source_name' => $source['name'] ?? 'Trusted source',
            'source_url' => $source['url'] ?? null,
            'verified' => false,
            'times_used' => 1,
            'confidence' => $confidence,
        ]);
    }

    public function builtInAnswer(string $question, string $intent, string $language = 'English'): ?array
    {
        $text = str($question)->lower()->toString();
        $tagalog = str_contains($language, 'Tagalog');

        $answers = [
            'how do you predict weather' => $tagalog
                ? 'Hinuhulaan ng iClimate ang panahon sa pamamagitan ng pagpapadala ng recent climate data sa trained local weather model. Kasama sa input ang dating ulan, temperatura, humidity, bilis ng hangin, buwan, at season. Ipinapaliwanag lang ng chatbot ang resulta sa madaling salita at hindi ito gumagawa ng sariling prediction values.'
                : 'iClimate predicts weather by sending recent climate features to the trained local weather model. The model uses inputs such as previous rainfall, temperature, humidity, wind speed, month, and season. The chatbot explains the model result in farmer-friendly language, but it does not invent prediction values.',
            'how does iclimate predict weather' => $tagalog
                ? 'Hinuhulaan ng iClimate ang panahon sa pamamagitan ng pagpapadala ng recent climate data sa trained local weather model. Kasama sa input ang dating ulan, temperatura, humidity, bilis ng hangin, buwan, at season. Ipinapaliwanag lang ng chatbot ang resulta sa madaling salita at hindi ito gumagawa ng sariling prediction values.'
                : 'iClimate predicts weather by sending recent climate features to the trained local weather model. The model uses inputs such as previous rainfall, temperature, humidity, wind speed, month, and season. The chatbot explains the model result in farmer-friendly language, but it does not invent prediction values.',
            'paano mo pinipredict ang weather' => 'Hinuhulaan ng iClimate ang panahon sa pamamagitan ng trained local weather model gamit ang ulan, temperatura, humidity, hangin, buwan, at season. Hindi ako gumagawa ng sariling prediction values; ipinapaliwanag ko lang ang resulta ng model sa mas madaling maintindihan.',
            'paano mo hinuhulaan ang weather' => 'Hinuhulaan ng iClimate ang panahon sa pamamagitan ng trained local weather model gamit ang ulan, temperatura, humidity, hangin, buwan, at season. Hindi ako gumagawa ng sariling prediction values; ipinapaliwanag ko lang ang resulta ng model sa mas madaling maintindihan.',
            'how do you predict rice yield' => $tagalog
                ? 'Hinuhulaan ng iClimate ang ani ng palay sa pamamagitan ng trained local rice-yield model. Gumagamit ito ng farm at climate inputs tulad ng ulan, average temperature, lawak ng bukid, dating weather, seasonal rainfall, seasonal temperature, at season. Hindi gumagawa ang chatbot ng sariling yield values; ipinapaliwanag lang nito ang output ng machine-learning model.'
                : 'iClimate predicts rice yield by sending farm and climate inputs to the trained local rice-yield model. Inputs include rainfall, average temperature, temperature range, farm area, previous weather, seasonal rainfall, seasonal temperature, and season. The chatbot does not invent yield values; it explains the machine-learning output in simple terms.',
            'register' => $tagalog ? 'Para mag-register, buksan ang registration page, ilagay ang pangalan, email, password, contact details, barangay, at farm information, pagkatapos ay i-submit ang form.' : 'To register, open the registration page, enter your name, email, password, contact details, barangay, and farm information, then submit the form.',
            'login' => $tagalog ? 'Para mag-login, gamitin ang registered email at password sa login page.' : 'To log in, use your registered email and password on the login page.',
            'reset password' => $tagalog ? 'Gamitin ang forgot password link sa login page, ilagay ang email, at sundin ang reset instructions.' : 'Use the forgot password link on the login page, enter your email, and follow the reset instructions.',
            'advisory' => $tagalog ? 'Ang advisory ay gabay mula sa iClimate o MAO tungkol sa pagtatanim, patubig, weather risk, at mga gawain sa rice farming.' : 'An advisory is guidance from iClimate or MAO personnel about planting, irrigation, weather risk, and rice farming actions.',
            'planting calendar' => $tagalog ? 'Tinutulungan ng planting calendar ang farmers na planuhin ang gawain ayon sa season, petsa, at inaasahang kondisyon ng panahon.' : 'The planting calendar helps farmers plan activities by season, date, and expected climate conditions.',
            'announcement' => $tagalog ? 'Ang announcements ay official updates mula sa iClimate o MAO na makikita sa community feed at notification areas.' : 'Announcements are official iClimate or MAO updates shown through the community feed and notification areas.',
            'notification' => $tagalog ? 'Ang notifications ay alerts tungkol sa advisories, warnings, at announcements na konektado sa account o barangay mo.' : 'Notifications alert you about advisories, warnings, and announcements connected to your account or barangay.',
            'feedback' => $tagalog ? 'Sa feedback, puwedeng mag-report ng concern o magbigay ng suhestiyon para mapaganda ang suporta ng iClimate.' : 'Feedback lets users report concerns or share comments so administrators can improve iClimate support.',
        ];

        foreach ($answers as $keyword => $answer) {
            if (str_contains($text, $keyword)) {
                return [
                    'answer' => $answer,
                    'source_name' => 'iClimate Knowledge Base',
                    'confidence' => 88,
                    'intent' => $intent,
                ];
            }
        }

        return null;
    }

    private function similarity(array $keywords, KnowledgeBase $record): int
    {
        $haystack = str($record->question.' '.$record->answer.' '.implode(' ', $record->keywords ?? []))->lower()->toString();

        return collect($keywords)
            ->filter(fn (string $keyword): bool => str_contains($haystack, $keyword))
            ->count();
    }

    private function keywords(string $text): array
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text)) ?: [];

        return collect($words)
            ->filter(fn (string $word): bool => strlen($word) >= 3)
            ->reject(fn (string $word): bool => in_array($word, ['the', 'and', 'for', 'with', 'how', 'what', 'when', 'should'], true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }
}
