<?php

namespace App\Services\AI;

use App\Models\KnowledgeBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class KnowledgeBaseService
{
    public function search(string $question, string $intent): ?KnowledgeBase
    {
        $keywords = $this->keywords($question);

        // The same question is asked again and again: remember which record matched, or that none did.
        $key = 'ai:kb-search:'.md5($intent.'|'.implode(' ', $keywords));
        $remembered = Cache::get($key);

        if (is_int($remembered)) {
            $record = $remembered > 0 ? KnowledgeBase::query()->find($remembered) : null;

            if ($record) {
                $record->increment('times_used');
            }

            return $record;
        }

        $found = $this->lookup($keywords, $intent);
        Cache::put($key, $found?->id ?? 0, max(1, (int) config('ai.knowledge_cache_seconds', 600)));

        return $found;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function lookup(array $keywords, string $intent): ?KnowledgeBase
    {
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
            ->take(80)
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

        $minRequired = count($keywords) >= 2 ? 2 : 1;

        if (! $best || $bestScore < $minRequired) {
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
                ? 'Tinatantiya ng iClimate ang panahon gamit ang mga nakaraang tala ng ulan, temperatura, humidity, hangin, buwan at season. Ipinapaliwanag lang ni Climora ang resulta sa madaling salita. Hindi siya gumagawa ng sariling numero.'
                : 'iClimate estimates the weather from past records of rain, temperature, humidity, wind, month and season. Climora only explains the result in simple words. It does not make up numbers.',
            'how does iclimate predict weather' => $tagalog
                ? 'Tinatantiya ng iClimate ang panahon gamit ang mga nakaraang tala ng ulan, temperatura, humidity, hangin, buwan at season. Ipinapaliwanag lang ni Climora ang resulta sa madaling salita. Hindi siya gumagawa ng sariling numero.'
                : 'iClimate estimates the weather from past records of rain, temperature, humidity, wind, month and season. Climora only explains the result in simple words. It does not make up numbers.',
            'paano mo pinipredict ang weather' => 'Tinatantiya ng iClimate ang panahon gamit ang mga nakaraang tala ng ulan, temperatura, humidity, hangin, buwan at season. Ipinapaliwanag lang ni Climora ang resulta sa madaling salita. Hindi siya gumagawa ng sariling numero.',
            'paano mo hinuhulaan ang weather' => 'Tinatantiya ng iClimate ang panahon gamit ang mga nakaraang tala ng ulan, temperatura, humidity, hangin, buwan at season. Ipinapaliwanag lang ni Climora ang resulta sa madaling salita. Hindi siya gumagawa ng sariling numero.',
            'how do you predict rice yield' => $tagalog
                ? 'Tinatantiya ng iClimate ang ani ng palay gamit ang laki ng bukid at ang panahon: ulan, temperatura, nakaraang panahon at season. Ipinapaliwanag lang ni Climora ang resulta sa madaling salita. Hindi siya gumagawa ng sariling numero.'
                : 'iClimate estimates rice yield from your farm size and the weather: rain, temperature, past weather and season. Climora only explains the result in simple words. It does not make up numbers.',
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
            ->reject(fn (string $word): bool => in_array($word, [
                'the', 'and', 'for', 'with', 'how', 'what', 'when', 'should',
                'you', 'your', 'can', 'will', 'about', 'have', 'has', 'was', 'were', 'this', 'that', 'into', 'than', 'which', 'does',
                'ang', 'mga', 'ba', 'po', 'sa', 'na', 'ng', 'mo', 'ko', 'sila', 'din', 'rin', 'kasi', 'kapag', 'ako',
                'para', 'kung', 'may', 'wala', 'yan', 'yun', 'ito', 'niya', 'namin', 'natin', 'kayo', 'siya', 'daw', 'raw',
            ], true))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }
}
