<?php

namespace App\Services\AI;

use Normalizer;

/**
 * Shared text clean-up for the vocabulary matcher and the language filter.
 * Pure string functions: no database, no cache.
 */
class TextNormalizer
{
    /** Look-alike characters used to disguise words. Applied only inside tokens that contain a letter. */
    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i', '+' => 't'];

    /** Lowercase, fold accents (ñ -> n), unify quotes/dashes and collapse whitespace. */
    public function base(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        if (class_exists(Normalizer::class)) {
            $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
            $text = $decomposed === false ? $text : (preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $text);
        }

        $text = strtr($text, ['’' => "'", '‘' => "'", '“' => '"', '”' => '"', '–' => '-', '—' => '-', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Word tokens made of letters and digits only. "pag-aani" -> [pag, aani].
     *
     * @return list<string>
     */
    public function words(string $text): array
    {
        // Apostrophes are dropped, not split on: "what's" is one word ("whats"), not "what" + "s".
        return preg_split('/[^a-z0-9]+/', str_replace("'", '', $this->base($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** The stored/matching form of a term or phrase: words joined by single spaces. */
    public function term(string $text): string
    {
        return implode(' ', $this->words($text));
    }

    /** Words joined with nothing between them, so "pag-aani", "pag aani" and "pagaani" agree. */
    public function squash(string $text): string
    {
        return implode('', $this->words($text));
    }

    /** Decode look-alike characters; digits and symbols are left alone in tokens with no letters. */
    public function leet(string $token): string
    {
        return preg_match('/[a-z]/', $token) === 1 ? strtr($token, self::LEET) : $token;
    }

    /** Shrink runs of a repeated character to at most $keep. */
    public function collapseRuns(string $token, int $keep): string
    {
        $keep = max(1, $keep);

        return preg_replace('/(.)\1{'.$keep.',}/u', str_repeat('$1', $keep), $token) ?? $token;
    }
}
