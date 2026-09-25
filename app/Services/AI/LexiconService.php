<?php

namespace App\Services\AI;

use App\Models\AiFaq;
use App\Models\AiKnowledgeTopic;
use App\Support\AiCache;

/**
 * Turns a question into canonical farming/weather concepts using the
 * vocabulary stored in Supabase (topics, terms, synonyms, alternative
 * spellings). Nothing about the vocabulary itself is hardcoded here, so
 * adding a word in the admin pages is enough for it to be understood.
 *
 * Matching runs on normalized word sequences with the spaces removed, so
 * "pag-aani", "pag aani" and "pagaani" are the same word, and it tolerates a
 * one- or two-letter typo in longer words when the result is unambiguous.
 * It does NOT know words nobody has added.
 */
class LexiconService
{
    /** Grammar cues (not vocabulary): the question is about right now / a future time / a forecast. */
    private const NOW_WORDS = ['now', 'today', 'tonight', 'currently', 'current', 'ngayon', 'ngayong', 'kasalukuyan', 'mamaya', 'kanina'];

    private const FUTURE_WORDS = ['tomorrow', 'bukas', 'week', 'weekend', 'next', 'susunod', 'linggo', 'magiging', 'hulaan', 'predict', 'prediction'];

    /** "my yield", "ang ani ko": a question about the person's own farm, which needs their data, not a definition. */
    private const PERSONAL_WORDS = ['my', 'mine', 'ko', 'akin', 'aking', 'namin', 'amin', 'ating', 'natin'];

    /** Words that make a very short key (e.g. "ha") count only right after a number. */
    private const MIN_PLAIN_KEY = 3;

    public function __construct(private readonly TextNormalizer $normalizer) {}

    /**
     * @return array{
     *     normalized: string, canonical: string, tokens: list<string>,
     *     concepts: array<string, array{slug: string, category: string, matched: string, fuzzy: bool, context: bool}>,
     *     ambiguous: list<list<string>>, has_now: bool, has_future: bool, has_personal: bool, has_number: bool
     * }
     */
    public function analyze(string $text): array
    {
        $index = $this->index();
        $tokens = $this->normalizer->words($text);
        $concepts = [];
        $ambiguous = [];
        $canonical = [];
        $count = count($tokens);

        for ($i = 0; $i < $count;) {
            $matched = null;

            for ($n = min($index['maxTokens'], $count - $i); $n >= 1; $n--) {
                $slice = array_slice($tokens, $i, $n);
                $key = implode('', $slice);

                if (! isset($index['keys'][$key])) {
                    continue;
                }

                if (strlen($key) < self::MIN_PLAIN_KEY && ! ($i > 0 && ctype_digit($tokens[$i - 1]))) {
                    continue;
                }

                $matched = [$n, $key, array_keys($index['keys'][$key])];
                break;
            }

            $fuzzy = false;

            if ($matched === null && ($slug = $this->fuzzyMatch($tokens[$i], $index)) !== null) {
                $matched = [1, $tokens[$i], [$slug]];
                $fuzzy = true;
            }

            if ($matched === null) {
                $canonical[] = $tokens[$i];
                $i++;

                continue;
            }

            [$n, $key, $slugs] = $matched;

            if (count($slugs) > 1) {
                $ambiguous[] = $slugs;
            }

            foreach ($slugs as $slug) {
                $concepts[$slug] ??= [
                    'slug' => $slug,
                    'category' => $index['topics'][$slug]['category'],
                    'matched' => implode(' ', array_slice($tokens, $i, $n)),
                    'fuzzy' => $fuzzy,
                    'context' => (bool) $index['topics'][$slug]['is_context'],
                ];
            }

            $canonical[] = $slugs[0];
            $i += $n;
        }

        return [
            'normalized' => implode(' ', $tokens),
            'canonical' => implode(' ', $canonical),
            'tokens' => $tokens,
            'concepts' => $concepts,
            'ambiguous' => $ambiguous,
            'has_now' => array_intersect($tokens, self::NOW_WORDS) !== [],
            'has_future' => array_intersect($tokens, self::FUTURE_WORDS) !== [],
            'has_personal' => array_intersect($tokens, self::PERSONAL_WORDS) !== [],
            'has_number' => preg_match('/\d/', $text) === 1,
        ];
    }

    /** Whether a matched word (spaces removed) is one of the English names/synonyms, as opposed to a Filipino or local one. */
    public function isEnglishKey(string $squashed): bool
    {
        return isset($this->index()['enKeys'][$squashed]);
    }

    /** Concept slug -> topic details for the current active vocabulary. */
    public function topic(string $slug): ?array
    {
        return $this->index()['topics'][$slug] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function faqs(): array
    {
        return $this->index()['faqs'];
    }

    // ---------------------------------------------------------------- fuzzy

    /** A typo-tolerant match for longer words only, and only when exactly one topic is nearest. */
    private function fuzzyMatch(string $token, array $index): ?string
    {
        $length = strlen($token);
        $allowed = $length >= 10 ? 2 : ($length >= 7 ? 1 : 0);

        if ($allowed === 0) {
            return null;
        }

        $best = $allowed + 1;
        $slugs = [];

        for ($candidateLength = $length - $allowed; $candidateLength <= $length + $allowed; $candidateLength++) {
            foreach ($index['fuzzy'][$candidateLength] ?? [] as [$key, $slug]) {
                $distance = levenshtein($token, $key);

                if ($distance > $allowed || $distance === 0 || $distance > $best) {
                    continue;
                }

                if ($distance < $best) {
                    $best = $distance;
                    $slugs = [];
                }

                $slugs[$slug] = true;
            }
        }

        return count($slugs) === 1 ? (string) array_key_first($slugs) : null;
    }

    // ---------------------------------------------------------------- index

    /**
     * The whole active vocabulary in lookup form, cached until an admin edits
     * a topic, term, synonym or FAQ.
     */
    private function index(): array
    {
        return AiCache::remember('vocab', fn (): array => $this->build());
    }

    private function build(): array
    {
        $index = ['keys' => [], 'fuzzy' => [], 'topics' => [], 'faqs' => [], 'enKeys' => [], 'maxTokens' => 1];

        $topics = AiKnowledgeTopic::query()
            ->where('is_active', true)
            ->with(['terms' => fn ($query) => $query->where('is_active', true)->with(['synonyms' => fn ($q) => $q->where('is_active', true)])])
            ->get();

        foreach ($topics as $topic) {
            $phrases = [$topic->title_en, $topic->title_fil];
            $terms = [];

            foreach ($topic->terms as $term) {
                array_push($phrases, $term->term_en, $term->term_fil, ...(array) $term->alt_spellings);

                foreach ($term->synonyms as $synonym) {
                    $phrases[] = $synonym->synonym;
                }

                $terms[] = [
                    'term_en' => $term->term_en,
                    'term_fil' => $term->term_fil,
                    'definition_en' => $term->definition_en,
                    'definition_fil' => $term->definition_fil,
                    'farming_context' => $term->farming_context,
                    'approved_response_en' => $term->approved_response_en,
                    'approved_response_fil' => $term->approved_response_fil,
                    'related_terms' => (array) $term->related_terms,
                    'source' => $term->source,
                ];
            }

            $index['topics'][$topic->slug] = [
                'slug' => $topic->slug,
                'category' => $topic->category,
                'title_en' => $topic->title_en,
                'title_fil' => $topic->title_fil,
                'prefers_live_data' => (bool) $topic->prefers_live_data,
                'is_context' => (bool) $topic->is_context,
                'terms' => $terms,
            ];

            // Words that are plainly English (English names and "en" synonyms), used to pick the answer language.
            $english = [$topic->title_en];
            foreach ($topic->terms as $term) {
                $english[] = $term->term_en;
                foreach ($term->synonyms as $synonym) {
                    if ($synonym->language === 'en') {
                        $english[] = $synonym->synonym;
                    }
                }
            }
            foreach ($english as $phrase) {
                if (($key = $this->normalizer->squash((string) $phrase)) !== '') {
                    $index['enKeys'][$key] = true;
                }
            }

            foreach (array_unique(array_filter(array_map('strval', $phrases))) as $phrase) {
                $words = $this->normalizer->words($phrase);
                $key = implode('', $words);

                if ($key === '') {
                    continue;
                }

                $index['keys'][$key][$topic->slug] = true;
                $index['maxTokens'] = max($index['maxTokens'], count($words));

                if (count($words) === 1 && strlen($key) >= 7) {
                    $index['fuzzy'][strlen($key)][] = [$key, $topic->slug];
                }
            }
        }

        $stop = [
            'the', 'and', 'for', 'with', 'how', 'what', 'when', 'should', 'can', 'do', 'does', 'is', 'are', 'to', 'a', 'an', 'of', 'in', 'on', 'my', 'i', 'it',
            'ang', 'mga', 'ng', 'sa', 'na', 'ba', 'po', 'ano', 'paano', 'para', 'ko', 'mo', 'ako', 'pag', 'dapat', 'kailan', 'gawin', 'ay',
        ];

        foreach (AiFaq::query()->where('is_active', true)->with('topic:id,slug,is_active')->get() as $faq) {
            if ($faq->topic_id !== null && ! ($faq->topic?->is_active)) {
                continue;
            }

            $words = array_merge(
                $this->normalizer->words((string) $faq->question_en),
                $this->normalizer->words((string) $faq->question_fil),
                ...array_map(fn ($keyword) => $this->normalizer->words((string) $keyword), (array) $faq->keywords),
            );

            $index['faqs'][] = [
                'id' => $faq->id,
                'slug' => $faq->topic?->slug,
                'words' => array_values(array_diff(array_unique($words), $stop)),
                'question_en' => $faq->question_en,
                'question_fil' => $faq->question_fil,
                'answer_en' => $faq->answer_en,
                'answer_fil' => $faq->answer_fil,
                'source' => $faq->source,
            ];
        }

        return $index;
    }
}
