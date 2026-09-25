<?php

namespace App\Services\AI;

/**
 * Answers farming and weather-vocabulary questions from the approved
 * knowledge in the database (terms and FAQs). It only ever returns text an
 * IT Expert or MAO officer entered. It never produces a weather value or a
 * yield figure; those questions stay with the live-data tools.
 */
class KnowledgeAnswerService
{
    public const MODE_DEFINITION = 'definition';

    public const MODE_HOWTO = 'howto';

    /** "What does X mean" style: always a request for the meaning. */
    private const STRONG_DEFINITION = '/^(define|meaning of|what does|what do you mean|ibig sabihin|kahulugan|explain|ipaliwanag)\b|\b(mean|means|meaning)\b|\bibig sabihin\b|\bkahulugan\b/';

    /** "What is X / ano ang X": a definition, unless X is usually asked about as live data. */
    private const WEAK_DEFINITION = '/^(what is|whats|what are|ano ang|ano yung|ano po ang|ano ba ang|ano ba)\b/';

    private const HOWTO = '/^(how to|how do i|how can i|how should i|when should i|when do i|when to|paano|pano|kailan|ano ang hakbang|ano ang mga hakbang|steps to|hakbang sa)\b|\b(steps|hakbang|proseso|gabay)\b/';

    private const FILIPINO_CUES = ['ano', 'ang', 'ng', 'mga', 'ba', 'po', 'paano', 'pano', 'kailan', 'bakit', 'yung', 'yun', 'ito', 'ibig', 'sabihin', 'kahulugan', 'ipaliwanag', 'pwede', 'puwede', 'gawin', 'dapat', 'ko', 'mo', 'namin', 'natin', 'sa', 'na', 'ay', 'hindi', 'bang'];

    private const ENGLISH_CUES = ['what', 'whats', 'is', 'are', 'the', 'how', 'do', 'does', 'i', 'my', 'should', 'can', 'when', 'a', 'an', 'of', 'to', 'mean', 'means', 'meaning', 'explain', 'define', 'difference', 'between', 'and', 'in'];

    public function __construct(private readonly LexiconService $lexicon) {}

    /**
     * Whether the message reads as "explain this" or "how do I do this" and
     * is not asking for live weather, a forecast, or a number-based estimate.
     */
    public function informationalMode(array $analysis): ?string
    {
        if ($analysis['concepts'] === [] || $analysis['has_now'] || $analysis['has_future'] || $analysis['has_number']) {
            return null;
        }

        $text = $analysis['normalized'];

        // "...in my rice field" is ordinary how-to wording, so "my" only rules out definitions.
        if (preg_match(self::HOWTO, $text) === 1 && preg_match(self::STRONG_DEFINITION, $text) !== 1) {
            return self::MODE_HOWTO;
        }

        // "What is my yield?" asks about the person's own farm, which needs their data.
        if ($analysis['has_personal']) {
            return null;
        }

        if (preg_match(self::STRONG_DEFINITION, $text) === 1) {
            return self::MODE_DEFINITION;
        }

        if (preg_match(self::WEAK_DEFINITION, $text) === 1) {
            $exact = array_filter($analysis['concepts'], fn (array $c): bool => ! $c['fuzzy']);
            $liveOnly = $exact !== [] && array_filter(
                $exact,
                fn (array $c): bool => ! ($this->lexicon->topic($c['slug'])['prefers_live_data'] ?? false),
            ) === [];

            return $liveOnly ? null : self::MODE_DEFINITION;
        }

        return null;
    }

    /**
     * The best approved answer, or null when there is none (never a guess).
     *
     * @return array{answer: string, source: string, kind: string, topic: ?string}|null
     */
    public function find(array $analysis, string $language, ?string $mode): ?array
    {
        $faq = $this->bestFaq($analysis);
        $language = $this->answerLanguage($analysis, $language);

        // How-to questions belong to FAQs. Definitions belong to the term. A bare
        // mention gets the FAQ only when it clearly matches, else the term.
        $faqFirst = $faq !== null && match ($mode) {
            self::MODE_HOWTO => $faq['score'] >= 5,
            self::MODE_DEFINITION => $faq['score'] >= 8,
            default => $faq['score'] >= 6,
        };

        if ($faqFirst) {
            return $this->faqResult($faq, $language);
        }

        // A how-to question is not answered by a definition. With no FAQ that
        // clearly fits, say nothing rather than give an answer to a different question.
        if ($mode === self::MODE_HOWTO) {
            return null;
        }

        $slugs = $this->primarySlugs($analysis);

        if (count($slugs) === 1) {
            $term = $this->termAnswer($slugs[0], $language);

            if ($term !== null) {
                return $term;
            }
        }

        return $faq !== null ? $this->faqResult($faq, $language) : null;
    }

    /**
     * A question instead of a guess, for a short message that names several
     * different things. Null when the message is not short and vague.
     */
    public function clarification(array $analysis, string $language): ?string
    {
        $language = $this->answerLanguage($analysis, $language);
        $slugs = $this->primarySlugs($analysis);

        if (count($slugs) < 2 || count($analysis['tokens']) > 4) {
            return null;
        }

        $titles = array_map(fn (string $slug): string => $this->title($slug, $language), array_slice($slugs, 0, 3));

        return $this->isFilipino($language)
            ? 'Ang tinutukoy mo ba ay '.$this->joinTitles($titles, 'o').'?'
            : 'Do you mean '.$this->joinTitles($titles, 'or').'?';
    }

    // ---------------------------------------------------------------- pieces

    /**
     * The topics the message is really about: exact matches only (a typo match
     * is not trusted to decide between topics) and, when a specific topic is
     * named, without the generic crop/setting words such as "palay".
     *
     * @return list<string>
     */
    private function primarySlugs(array $analysis): array
    {
        $concepts = array_filter($analysis['concepts'], fn (array $c): bool => ! $c['fuzzy']) ?: $analysis['concepts'];
        $specific = array_filter($concepts, fn (array $c): bool => ! $c['context']);

        return array_keys($specific !== [] ? $specific : $concepts);
    }

    /** @return array{score: int, faq: array}|null */
    private function bestFaq(array $analysis): ?array
    {
        $best = null;
        $tokens = $analysis['tokens'];

        foreach ($this->lexicon->faqs() as $faq) {
            $topicHit = $faq['slug'] !== null && isset($analysis['concepts'][$faq['slug']]);
            $overlap = count(array_intersect($tokens, $faq['words']));
            $score = ($topicHit ? 3 : 0) + $overlap;

            if (! $topicHit && $overlap < 3) {
                continue;
            }

            if ($score >= 4 && ($best === null || $score > $best['score'])) {
                $best = ['score' => $score, 'faq' => $faq];
            }
        }

        return $best;
    }

    private function faqResult(array $best, string $language): array
    {
        $faq = $best['faq'];
        $answer = $this->isFilipino($language)
            ? ($faq['answer_fil'] ?: $faq['answer_en'])
            : ($faq['answer_en'] ?: $faq['answer_fil']);

        return ['answer' => (string) $answer, 'source' => (string) ($faq['source'] ?: 'iClimate approved knowledge'), 'kind' => 'faq', 'topic' => $faq['slug']];
    }

    private function termAnswer(string $slug, string $language): ?array
    {
        $topic = $this->lexicon->topic($slug);

        foreach ($topic['terms'] ?? [] as $term) {
            $filipino = $this->isFilipino($language);
            $answer = $filipino
                ? ($term['approved_response_fil'] ?: $term['approved_response_en'])
                : ($term['approved_response_en'] ?: $term['approved_response_fil']);

            if (! $answer) {
                $definition = $filipino
                    ? ($term['definition_fil'] ?: $term['definition_en'])
                    : ($term['definition_en'] ?: $term['definition_fil']);

                $answer = trim($definition.' '.$term['farming_context']);
            }

            if (trim((string) $answer) !== '') {
                return ['answer' => (string) $answer, 'source' => (string) ($term['source'] ?: 'iClimate approved knowledge'), 'kind' => 'term', 'topic' => $slug];
            }
        }

        return null;
    }

    private function title(string $slug, string $language): string
    {
        $topic = $this->lexicon->topic($slug);

        return $this->isFilipino($language)
            ? (string) ($topic['title_fil'] ?: $topic['title_en'])
            : (string) $topic['title_en'];
    }

    /** @param list<string> $titles */
    private function joinTitles(array $titles, string $conjunction): string
    {
        $last = array_pop($titles);

        return $titles === [] ? (string) $last : implode(', ', $titles).', '.$conjunction.' '.$last;
    }

    /** Filipino and Taglish questions get the Filipino text when it exists. */
    private function isFilipino(string $language): bool
    {
        return $language === 'fil' || str_contains($language, 'Tagalog');
    }

    /**
     * Which language to answer in, from whole words only. The general language
     * detector matches fragments ("prepa-ra-tion" contains "para"), which would
     * answer an English question in Filipino. Mixed messages get Filipino
     * (Taglish is fine); with no cue words at all, the detector's guess stands.
     */
    private function answerLanguage(array $analysis, string $detected): string
    {
        $filipino = count(array_intersect($analysis['tokens'], self::FILIPINO_CUES));
        $english = count(array_intersect($analysis['tokens'], self::ENGLISH_CUES));

        if ($filipino > 0) {
            return 'fil';
        }

        if ($english > 0) {
            return 'en';
        }

        // Bare vocabulary ("abono peste", "fertilizer pests"): go by which language the matched words are in.
        $matchedEnglish = 0;
        $matchedOther = 0;

        foreach ($analysis['concepts'] as $concept) {
            $this->lexicon->isEnglishKey(str_replace(' ', '', $concept['matched'])) ? $matchedEnglish++ : $matchedOther++;
        }

        return match (true) {
            $matchedOther > 0 => 'fil',
            $matchedEnglish > 0 => 'en',
            default => $this->isFilipino($detected) ? 'fil' : 'en',
        };
    }
}
