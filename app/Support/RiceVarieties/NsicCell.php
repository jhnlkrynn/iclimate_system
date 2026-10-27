<?php

namespace App\Support\RiceVarieties;

/**
 * Reads the cells of the NSIC "Rice" sheet, which mix numbers and text:
 *  - measures:  109 | "109 (100-120)" | "108-112" (no average: the middle is used)
 *  - yields:    5630 | "Southern Luzon (SL): 7888 , Zamboanga Peninsula and Western Mindanao (ZPWM): 8729"
 *  - reactions: "I" | "MS (Screenhouse)" | "WSB - I; YSB - MR" | "PhilRice CES - S PhilRice Midsayap - I UPLB - I"
 *               | "Induced (PhilRice CES) - S Modified (PhilRice Midsayap) - R"
 *  - amylose:   0.131 (a fraction) or 13.1 (a percent)
 */
final class NsicCell
{
    /** Reaction codes, as the NSIC list writes them (longest first, so "MR" is not read as "R"). */
    public const CODES = ['HR', 'MR', 'MS', 'HS', 'R', 'I', 'S'];

    private const ABBREVIATIONS = [
        'wsb' => 'White Stem Borer',
        'white stemborer' => 'White Stem Borer',
        'white stem borer' => 'White Stem Borer',
        'white sb' => 'White Stem Borer',
        'ysb' => 'Yellow Stem Borer',
        'yellow stemborer' => 'Yellow Stem Borer',
        'yellow stem borer' => 'Yellow Stem Borer',
        'yellow sb' => 'Yellow Stem Borer',
        'induced' => 'Induced',
        'modified' => 'Modified',
    ];

    /** Blank (null, "", "-", "n/a") → null; otherwise the trimmed text with runs of spaces made single. */
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', (string) $value)));

        return in_array(strtolower($text), ['', '-', '—', 'n/a', 'na', 'none'], true) ? null : $text;
    }

    /**
     * "109 (100-120)" → [109, 100, 120]; "108-112" → [110, 108, 112]; 117 → [117, null, null]; blank → [null, null, null].
     *
     * @return array{0: float|null, 1: float|null, 2: float|null}
     */
    public static function measure(mixed $value): array
    {
        if (is_int($value) || is_float($value)) {
            return [(float) $value, null, null];
        }
        $text = self::text($value);
        if ($text === null) {
            return [null, null, null];
        }
        $n = '(\d+(?:\.\d+)?)';
        if (preg_match('/^'.$n.'\s*\(\s*'.$n.'\s*[-–]\s*'.$n.'\s*\)/u', $text, $m)) {
            return [(float) $m[1], (float) $m[2], (float) $m[3]];
        }
        if (preg_match('/^'.$n.'\s*[-–]\s*'.$n.'$/u', $text, $m)) {
            [$low, $high] = [(float) $m[1], (float) $m[2]];

            return [round(($low + $high) / 2, 1), $low, $high];
        }
        if (preg_match('/'.$n.'/', $text, $m)) {
            return [(float) $m[1], null, null];
        }

        return [null, null, null];
    }

    /**
     * A yield cell: its kg/ha figure (the mean of its regions), every value with its region, and the raw text.
     *
     *  - 5630                                     → 5630
     *  - "Southern Luzon (SL): 7888 , … : 8729"   → one value per region
     *  - "4500-5600" / "Central Luzon: 5918-8573 kg/ha; Western Mindanao: 7472-9853 kg/ha"
     *                                             → a range counts as its middle (7246), min and max kept
     *  - "Yield Range: 5602-8465 with average yield of 7300 at Central Luzon"
     *                                             → the stated average (7300, Central Luzon), min and max kept
     *
     * @return array{kg: int|null, raw: string|null, values: array<int, array{label: string|null, kg: int, min?: int, max?: int}>}
     */
    public static function yield(mixed $value): array
    {
        $raw = is_int($value) || is_float($value) ? (string) $value : self::text($value);
        if ($raw === null) {
            return ['kg' => null, 'raw' => null, 'values' => []];
        }

        $n = '(?:\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?)';
        $figure = '('.$n.')(?:\s*[-–]\s*('.$n.'))?';               // one number or a range "5918-8573"
        $number = fn (?string $text): ?float => $text === null || $text === '' ? null : (float) str_replace(',', '', $text);
        $value = function (?string $label, ?float $low, ?float $high) : array {
            $label = $label !== null && ! preg_match('/^\s*(yield\s*range|yield|average\s*yield)\s*$/i', $label) ? trim($label) : null;

            return $high === null
                ? ['label' => $label, 'kg' => $low]
                : ['label' => $label, 'kg' => ($low + $high) / 2, 'min' => min($low, $high), 'max' => max($low, $high)];
        };

        $values = [];
        if (preg_match('/average\s+yield\s+of\s+('.$n.')(?:\s*kg\/ha)?(?:\s+(?:at|in)\s+([^;.]+))?/iu', $raw, $avg)) {
            // The cell says its own average: that is the figure; its range only sets min / max.
            $range = preg_match('/'.$n.'\s*[-–]\s*'.$n.'/u', $raw, $r) && preg_match('/('.$n.')\s*[-–]\s*('.$n.')/u', $r[0], $ends) ? [$number($ends[1]), $number($ends[2])] : null;
            $values[] = ['label' => isset($avg[2]) ? trim($avg[2]) : null, 'kg' => $number($avg[1])] + ($range ? ['min' => min($range), 'max' => max($range)] : []);
        } elseif (preg_match_all('/([^:,;]*?[A-Za-z)][^:,;]*?)\s*:\s*'.$figure.'/u', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[] = $value($match[1], $number($match[2]), $number($match[3] ?? null));
            }
        } elseif (preg_match_all('/'.$figure.'/u', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[] = $value(null, $number($match[1]), $number($match[2] ?? null));
            }
        }
        if ($values === []) {
            return ['kg' => null, 'raw' => $raw, 'values' => []];
        }

        // A figure written in tons per hectare (all under 50) is turned into kilograms.
        $scale = max(array_column($values, 'kg')) < 50 ? 1000 : 1;
        $values = array_map(fn (array $v): array => array_map(
            fn ($x) => is_float($x) || is_int($x) ? (int) round($x * $scale) : $x, $v,
        ), $values);

        return [
            'kg' => (int) round(array_sum(array_column($values, 'kg')) / count($values)),
            'raw' => $raw,
            'values' => $values,
        ];
    }

    /** 0.131 → 13.1; 13.1 → 13.1; "18.5%" → 18.5. */
    public static function amylose(mixed $value): ?float
    {
        [$number] = self::measure($value);
        if ($number === null || $number <= 0) {
            return null;
        }

        return round($number <= 1 ? $number * 100 : $number, 1);
    }

    /** "Hybird" → "Hybrid"; the five values the list uses, else null. */
    public static function varietyType(mixed $value): ?string
    {
        $text = self::text($value);
        if ($text === null) {
            return null;
        }
        $text = preg_replace('/\bhybird\b/i', 'Hybrid', $text);
        foreach (['Inbred GM/Biotech', 'Special Purpose', 'Hybrid', 'Inbred'] as $type) {
            if (strcasecmp($text, $type) === 0) {
                return $type;
            }
        }

        return $text;
    }

    /** Worst first: a summary of results by station shows the worst one (S > MS > I > MR > R). */
    public const SEVERITY = ['HS', 'S', 'MS', 'I', 'MR', 'R', 'HR'];

    /**
     * A reaction cell split into one entry per pest, site or test: [{label, code, note, station, kind}]. Results by
     * testing station ("PhilRice CES - I PhilRice Midsayap - S UPLB - I") give one entry per station; "kind" is the
     * stem borer or tungro test when the cell names one ("PhilRice Agusan (WSB)", "Induced (UPLB)"). "WSB - I; YSB - MR" gives
     * White Stem Borer · I and Yellow Stem Borer · MR; a plain "I" gives one entry without a label. A code the list
     * does not use is kept as the entry's note with no code ("Not tested").
     *
     * @return array<int, array{label: string|null, code: string|null, note: string|null, station: string|null, kind: string|null}>
     */
    public static function reactions(mixed $value): array
    {
        $raw = self::text($value);
        if ($raw === null) {
            return [];
        }

        // "1" is a typing slip for "I" in the NSIC list ("YSB - 1"; the same variety's whiteheads read "YSB - I").
        $codes = implode('|', self::CODES).'|1';
        $code = fn (string $c): string => $c === '1' ? 'I' : $c;
        $typo = fn (string $c, ?string $note): ?string => $c === '1' ? trim(($note ? $note.' · ' : '').'written "1" in the NSIC list') : $note;
        // A whole cell that is just a code: "I", "MS (Screenhouse)".
        if (preg_match('/^('.$codes.')\b\s*(?:\(([^)]*)\)?)?$/u', $raw, $m)) {
            return [['label' => null, 'code' => $code($m[1]), 'note' => $typo($m[1], self::text($m[2] ?? null)), 'station' => null, 'kind' => null]];
        }

        $entries = [];
        // A code can run straight into the next station with no space ("VSU (YSB) - RPhilRice Midsayap - I").
        $pattern = '/\s*([^;]*?)\s*-\s*('.$codes.')(?:\b|(?=[A-Z]))(?:\s*\(([^)]*)\)?)?\s*;?/u';
        if (preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                [$kind, $station] = self::reactionParts($match[1]);
                $entries[] = [
                    'label' => $kind !== null && $station !== null ? $kind.' · '.$station : ($kind ?? $station),
                    'code' => $code($match[2]),
                    'note' => $typo($match[2], self::text($match[3] ?? null)),
                    'station' => $station,
                    'kind' => $kind,
                ];
            }
        }

        return $entries !== [] ? $entries : [['label' => null, 'code' => null, 'note' => $raw, 'station' => null, 'kind' => null]];
    }

    /** True for a cell of results by testing station: two or more entries that each name a station. */
    public static function isByStation(array $entries): bool
    {
        return count(array_filter($entries, fn (array $e): bool => ($e['station'] ?? null) !== null)) >= 2;
    }

    /**
     * One chip for a cell of several results: the worst code (S > MS > I > MR > R), whether the results differ, and how
     * many stations were tested. Null when no entry has a code.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{code: string, varies: bool, sites: int}|null
     */
    public static function summary(array $entries): ?array
    {
        $codes = array_values(array_filter(array_map(fn (array $e): ?string => $e['code'] ?? null, $entries)));
        if ($codes === []) {
            return null;
        }
        usort($codes, fn (string $a, string $b): int => array_search($a, self::SEVERITY, true) <=> array_search($b, self::SEVERITY, true));

        return [
            'code' => $codes[0],
            'varies' => count(array_unique($codes)) > 1,
            'sites' => count(array_unique(array_filter(array_map(fn (array $e): ?string => $e['station'] ?? null, $entries)))),
        ];
    }

    /**
     * The test and the station of an entry's label, as [kind, station]:
     * "PhilRice Midsayap (WSB)" → [White Stem Borer, PhilRice Midsayap]; "Induced (UPLB)" → [Induced, UPLB];
     * "PhilRice CES" → [null, PhilRice CES]; "WSB" → [White Stem Borer, null].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private static function reactionParts(string $label): array
    {
        $label = trim($label, " \t:;,");
        // A stray code left from the entry before ("I R PhilRice Isabela (YSB)" → "PhilRice Isabela (YSB)").
        $label = (string) preg_replace('/^(?:(?:'.implode('|', self::CODES).')\s+)+/u', '', $label);
        if ($label === '') {
            return [null, null];
        }
        $known = fn (string $text): ?string => self::ABBREVIATIONS[strtolower(trim($text))] ?? null;

        if ($name = $known($label)) {
            return [$name, null];
        }
        if (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/u', $label, $m)) {
            [$outside, $inside] = [trim($m[1]), trim($m[2])];
            if ($name = $known($inside)) {                       // "PhilRice CES (YSB)", "PhilRice CES (induced)"
                return [$name, $outside === '' ? null : $outside];
            }
            if ($name = $known($outside)) {                      // "Induced (PhilRice CES)"
                return [$name, $inside === '' ? null : $inside];
            }
        }

        return [null, $label];
    }

    /** "R" / "MR" → resistant, "I" → intermediate, "MS" / "S" → susceptible, else not_tested. */
    public static function reactionLevel(?string $code): string
    {
        return match (strtoupper((string) $code)) {
            'R', 'MR', 'HR' => 'resistant',
            'I' => 'intermediate',
            'MS', 'S', 'HS' => 'susceptible',
            default => 'not_tested',
        };
    }

    /**
     * The farm types an adaptation text recommends, as the planting plans name them (FarmerPlantingSchedule::FARM_TYPES):
     * "irrigated" → Irrigated; "rainfed" or "upland" → Rainfed; both, or neither ("Not specified"). Saline, cool-elevated
     * and special-purpose notes stay in the text.
     *
     * @return array{irrigated: bool, rainfed: bool}
     */
    public static function farmTypes(?string $adaptation): array
    {
        $text = strtolower((string) $adaptation);

        return ['irrigated' => str_contains($text, 'irrigated'), 'rainfed' => str_contains($text, 'rainfed') || str_contains($text, 'upland')];
    }

    /** "National recommendation …" → National; "Recommended in irrigated lowland areas of Southern Luzon (SL): 7888 …" → Southern Luzon (SL), …. */
    public static function location(?string $adaptation): ?string
    {
        $text = self::text($adaptation);
        if ($text === null) {
            return null;
        }
        if (preg_match('/\bnational\b/i', $text)) {
            return 'National';
        }
        if (preg_match('/\bareas?\s+(?:of|in)\s+(.+)$/iu', $text, $m) || preg_match('/\b(?:regions?|provinces?)\s+(?:of\s+)?(.+)$/iu', $text, $m)) {
            $places = preg_replace('/\s*:\s*\d+(?:[.,]\d+)*/u', '', $m[1]);       // drop the yields after each region ("…: 7888 , …")
            $places = preg_replace('/\s+,/u', ',', (string) $places);
            $places = trim((string) preg_replace('/\s*\.\s*$/u', '', (string) $places), " ,;");

            return $places !== '' ? $places : null;
        }

        return preg_match('/\bregional\b/i', $text) ? 'Regional' : null;
    }
}
