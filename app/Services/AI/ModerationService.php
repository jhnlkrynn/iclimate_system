<?php

namespace App\Services\AI;

use App\Models\AiBlockedPattern;
use App\Models\AiBlockedTerm;
use App\Models\AiModerationEvent;
use App\Models\User;
use App\Support\AiCache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Screens a chat message before any other processing.
 *
 * The dictionary lives in ai_blocked_terms / ai_blocked_patterns (managed by
 * IT and MAO) and is cached; a chat message never queries all terms.
 *
 * Matching is by whole word, never by substring, so "class", "grass" and
 * "assessment" are not caught by "ass". Disguises handled: letter look-alikes
 * (leetspeak), stretched letters, spaced-out letters ("f u c k"), inserted
 * punctuation ("f.u.c.k") and "*" as a wildcard ("f*ck"). Words with a
 * legitimate second meaning (lintik = lightning, hayop = animal) are handled
 * through per-term exceptions or context patterns rather than a blunt block.
 *
 * It is a dictionary, not a language model: it will miss words nobody has
 * added yet and it does not understand tone. The dictionary is meant to grow.
 */
class ModerationService
{
    public const MESSAGE_FIL = 'Pakigamit po ang maayos na pananalita upang matulungan kita tungkol sa pagsasaka ng palay at panahon.';

    public const MESSAGE_EN = 'Please use appropriate language so I can help with rice farming and weather questions.';

    public function __construct(private readonly TextNormalizer $normalizer) {}

    /**
     * @return array{blocked: bool, category: ?string, reason_code: ?string, language: ?string}
     */
    public function inspect(string $message): array
    {
        $none = ['blocked' => false, 'category' => null, 'reason_code' => null, 'language' => null];
        $index = $this->index();

        if ($index['single'] === [] && $index['phrases'] === [] && $index['patterns'] === []) {
            return $none;
        }

        $tokens = $this->tokens($message);

        if ($tokens === []) {
            return $none;
        }

        $flat = ' '.implode(' ', array_map(fn (array $t): string => $t['plain'], $tokens)).' ';

        // 1. Single words (and solid spellings of phrases).
        foreach ($tokens as $token) {
            foreach ($token['variants'] as $variant) {
                if (isset($index['single'][$variant]) && ! $this->excepted($index['single'][$variant]['exceptions'], $flat)) {
                    return $this->hit($index['single'][$variant], 'blocked_term');
                }
            }

            if ($token['wildcard'] !== null && ($entry = $this->wildcardHit($token['wildcard'], $index, $flat)) !== null) {
                return $this->hit($entry, 'blocked_term');
            }
        }

        // 2. Multi-word phrases, each word allowed to be disguised.
        foreach ($tokens as $i => $token) {
            foreach ($token['variants'] as $variant) {
                foreach ($index['phrases'][$variant] ?? [] as $phrase) {
                    if ($this->phraseMatches($phrase['words'], $tokens, $i) && ! $this->excepted($phrase['exceptions'], $flat)) {
                        return $this->hit($phrase, 'blocked_term');
                    }
                }
            }
        }

        // 3. Context patterns, on both the plain and the look-alike-decoded text.
        $decoded = ' '.implode(' ', array_map(fn (array $t): string => $t['decoded'], $tokens)).' ';

        foreach ($index['patterns'] as $pattern) {
            foreach ([$flat, $decoded] as $text) {
                $matched = @preg_match('~'.$pattern['pattern'].'~iu', $text);

                if ($matched === false) {
                    Log::warning('A blocked pattern could not be evaluated and was skipped.', ['pattern_id' => $pattern['id']]);
                    break;
                }

                if ($matched === 1) {
                    return $this->hit($pattern, 'blocked_pattern');
                }
            }
        }

        return $none;
    }

    /** Neutral, non-repeating reply in the person's language. Taglish gets the Filipino line. */
    public function message(string $language): string
    {
        return str_contains($language, 'Tagalog') ? self::MESSAGE_FIL : self::MESSAGE_EN;
    }

    /** Stores the safe facts only: who, when, language, category, reason code. */
    public function record(?User $user, string $language, array $result): void
    {
        try {
            AiModerationEvent::create([
                'user_id' => $user?->id,
                'language' => mb_substr($language, 0, 40),
                'category' => (string) ($result['category'] ?? 'abusive'),
                'reason_code' => (string) ($result['reason_code'] ?? 'blocked_term'),
                'blocked' => true,
            ]);
        } catch (Throwable) {
            // Logging must never turn a blocked message into an error page.
        }
    }

    // ---------------------------------------------------------------- tokens

    /**
     * @return list<array{plain: string, decoded: string, variants: list<string>, wildcard: ?string}>
     */
    private function tokens(string $message): array
    {
        $rawTokens = preg_split('/\s+/u', $this->normalizer->base($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        $singles = [];

        foreach ($rawTokens as $raw) {
            $core = preg_replace('/[^a-z0-9@$!+*]/', '', $raw) ?? '';
            $plain = preg_replace('/[^a-z0-9]/', '', $core) ?? '';

            if ($plain === '' && ! str_contains($core, '@') && ! str_contains($core, '$')) {
                $singles[] = null;

                continue;
            }

            $token = $this->token($core, $plain);
            $tokens[] = $token;
            $singles[] = strlen($plain) === 1 && ctype_alpha($plain) ? $plain : null;
        }

        return [...$tokens, ...$this->spacedOutWords($singles)];
    }

    /**
     * @return array{plain: string, decoded: string, variants: list<string>, wildcard: ?string}
     */
    private function token(string $core, string $plain): array
    {
        $decoded = preg_replace('/[^a-z0-9]/', '', $this->normalizer->leet(str_replace('*', '', $core))) ?? '';
        $bases = array_unique(array_filter([$plain, $decoded]));
        $variants = [];

        foreach ($bases as $base) {
            $variants[] = $base;
            $variants[] = $this->normalizer->collapseRuns($base, 1);   // fuuuck -> fuck
            $variants[] = $this->normalizer->collapseRuns($base, 2);   // assss  -> ass

            // "fuuck" -> "fuck": only for longer words, so short real words are not merged into short bad ones.
            $singled = preg_replace('/(.)\1+/u', '$1', $base) ?? $base;
            if (strlen($base) >= 4 && strlen($singled) >= 4) {
                $variants[] = $singled;
            }
        }

        return [
            'plain' => $plain !== '' ? $plain : $decoded,
            'decoded' => $decoded !== '' ? $decoded : $plain,
            'variants' => array_values(array_unique(array_filter($variants))),
            'wildcard' => str_contains($core, '*') && strlen($core) >= 4 ? $core : null,
        ];
    }

    /**
     * Runs of three or more one-letter words ("f u c k") read as one word.
     *
     * @param  list<?string>  $singles  the single letter at each position, or null
     * @return list<array{plain: string, decoded: string, variants: list<string>, wildcard: ?string}>
     */
    private function spacedOutWords(array $singles): array
    {
        $words = [];
        $run = '';

        foreach ([...$singles, null] as $letter) {
            if ($letter !== null) {
                $run .= $letter;

                continue;
            }

            if (strlen($run) >= 3) {
                $words[] = $this->token($run, $run);
            }

            $run = '';
        }

        return $words;
    }

    // -------------------------------------------------------------- matching

    /**
     * @param  list<string>  $words
     * @param  list<array{variants: list<string>}>  $tokens
     */
    private function phraseMatches(array $words, array $tokens, int $start): bool
    {
        foreach ($words as $offset => $word) {
            if (! isset($tokens[$start + $offset]) || ! in_array($word, $tokens[$start + $offset]['variants'], true)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $exceptions */
    private function excepted(array $exceptions, string $flat): bool
    {
        foreach ($exceptions as $phrase) {
            if ($phrase !== '' && str_contains($flat, ' '.$phrase.' ')) {
                return true;
            }
        }

        return false;
    }

    /** "f*ck" matches any listed word of the same length that fits around the "*". */
    private function wildcardHit(string $core, array $index, string $flat): ?array
    {
        $regex = '/^'.str_replace('\*', '[a-z0-9]', preg_quote($this->normalizer->leet($core), '/')).'$/';

        foreach ($index['byLength'][strlen($core)] ?? [] as $term) {
            if (preg_match($regex, $term) === 1 && ! $this->excepted($index['single'][$term]['exceptions'], $flat)) {
                return $index['single'][$term];
            }
        }

        return null;
    }

    /**
     * @param  array{category: string, language?: string}  $entry
     * @return array{blocked: true, category: string, reason_code: string, language: ?string}
     */
    private function hit(array $entry, string $reason): array
    {
        return ['blocked' => true, 'category' => $entry['category'], 'reason_code' => $reason, 'language' => $entry['language'] ?? null];
    }

    // ------------------------------------------------------------ dictionary

    /**
     * @return array{single: array<string, array{category: string, language: string, exceptions: list<string>}>, phrases: array<string, list<array{words: list<string>, category: string, language: string, exceptions: list<string>}>>, byLength: array<int, list<string>>, patterns: list<array{id: int, pattern: string, category: string}>}
     */
    private function index(): array
    {
        return AiCache::remember('moderation', function (): array {
            $index = ['single' => [], 'phrases' => [], 'byLength' => [], 'patterns' => []];

            foreach (AiBlockedTerm::query()->where('is_active', true)->get() as $term) {
                $words = explode(' ', (string) $term->normalized_term);
                $entry = ['category' => (string) $term->category, 'language' => (string) $term->language, 'exceptions' => (array) $term->exceptions];

                if (count($words) === 1 && $words[0] !== '') {
                    $index['single'][$words[0]] = $entry;

                    continue;
                }

                if (count($words) > 1) {
                    $index['phrases'][$words[0]][] = ['words' => $words] + $entry;

                    // Also caught when written solid, e.g. "putangina".
                    $solid = implode('', $words);
                    if (strlen($solid) >= 6) {
                        $index['single'][$solid] ??= $entry;
                    }
                }
            }

            foreach (array_keys($index['single']) as $term) {
                $index['byLength'][strlen($term)][] = $term;
            }

            foreach (AiBlockedPattern::query()->where('is_active', true)->get() as $pattern) {
                $index['patterns'][] = ['id' => (int) $pattern->id, 'pattern' => (string) $pattern->pattern, 'category' => (string) $pattern->category, 'language' => (string) $pattern->language];
            }

            return $index;
        });
    }
}
