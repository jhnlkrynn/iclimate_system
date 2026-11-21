<?php

namespace App\Support;

class LianBarangays
{
    /**
     * Representative point per barangay, used as the query location for
     * live per-barangay weather (LianBarangayWeatherService) and as the
     * default map marker position. Each point is the polygon centroid of
     * that barangay's own feature in public/geojson/lian-barangays.geojson
     * (verified to fall inside that polygon — see
     * tests/Unit/LianBarangaysCoordinatesTest.php), NOT hand-guessed.
     *
     * These values are recomputed directly from the shipped GeoJSON polygons,
     * not re-guessed.
     *
     * All 19 barangays of Lian are supported for weather, the Heat Map and risk
     * ratings (thesis objective 3.5; owner decision 2026-11-20, which restores
     * the 10 barangays the 2026-11-18 release had removed). Ten of them have no
     * MAO rice-production rows: their weather and risk are shown, and any
     * production-based figure says "no data" instead of being invented.
     */
    private const COORDINATES = [
        'Bagong Pook' => [14.0320331, 120.6807593],
        'Balibago' => [13.9413816, 120.6489014],
        'Barangay 1 (Pob.)' => [14.0354539, 120.6510264],
        'Barangay 2 (Pob.)' => [14.0381382, 120.6513488],
        'Barangay 3 (Pob.)' => [14.0405819, 120.6507332],
        'Barangay 4 (Pob.)' => [14.0379401, 120.6536591],
        'Barangay 5 (Pob.)' => [14.0413525, 120.6528515],
        'Binubusan' => [13.9694821, 120.6384280],
        'Bungahan' => [14.0470903, 120.6379867],
        'Cumba' => [13.9718581, 120.6609465],
        'Humayingan' => [13.9967494, 120.6724865],
        'Kapito' => [14.0162390, 120.6689030],
        'Lumaniag' => [13.9946196, 120.6343614],
        'Luyahan' => [13.9641912, 120.6176801],
        'Malaruhatan' => [14.0383984, 120.6654801],
        'Matabungkay' => [13.9467451, 120.6249676],
        'Prenza' => [14.0128685, 120.6398701],
        'Puting-Kahoy' => [13.9894782, 120.6534129],
        'San Diego' => [14.0336258, 120.6335108],
    ];

    /**
     * The barangays with rice farms in the verified MAO rice-production source. Only these get a rice-yield estimate
     * (Heat Map yield layer, barangay yield runs); the other ten still get weather, flood, drought and risk ratings.
     * Owner/team decision 2026-11-20: a yield model run for a barangay with no rice farms is not a meaningful figure.
     */
    private const RICE_FARMING = [
        'Balibago',
        'Binubusan',
        'Bungahan',
        'Kapito',
        'Luyahan',
        'Malaruhatan',
        'Matabungkay',
        'Prenza',
        'San Diego',
    ];

    /** @return list<string> the barangays that get a rice-yield estimate */
    public static function riceFarming(): array
    {
        return self::RICE_FARMING;
    }

    public static function isRiceFarming(?string $barangay): bool
    {
        return in_array(self::canonicalName($barangay), self::RICE_FARMING, true);
    }

    /**
     * Lian barangays that are named in questions but not supported. Empty since all 19 are supported again; kept so
     * namedIn() and withoutNames() still work if a barangay is ever taken out of COORDINATES.
     */
    private const UNSUPPORTED_LIAN_BARANGAYS = [];

    /**
     * The Lian barangay a free-text question names, supported or not, or null
     * when none is named. Supported names win; longer names are tried first so
     * a short name never shadows a longer one that contains it.
     */
    public static function namedIn(string $text): ?string
    {
        $haystack = ' '.self::normalize($text).' ';
        if (trim($haystack) === '') {
            return null;
        }

        foreach ([self::all(), self::UNSUPPORTED_LIAN_BARANGAYS] as $names) {
            usort($names, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($names as $name) {
                $needle = self::normalize($name);
                // Numbered Poblacion barangays normalize to a bare digit; only
                // "barangay 1", "brgy 1", "poblacion 1" or "1 poblacion" name
                // one, never a stray number such as "1 hectare".
                $matches = ctype_digit($needle)
                    ? preg_match('/\b(?:barangay|brgy\.?|poblacion|pob\.?)\s*'.$needle.'\b|\b'.$needle.'\s*\(?(?:poblacion|pob)\b/i', $text) === 1
                    : str_contains($haystack, ' '.$needle.' ');
                if ($matches) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The text with every Lian barangay name removed. Place names are not
     * language evidence: "Bagong Pook" must not make an English question look
     * Tagalog (" po"), nor "Lumaniag" (" ani").
     */
    public static function withoutNames(string $text): string
    {
        foreach ([...self::all(), ...self::UNSUPPORTED_LIAN_BARANGAYS] as $name) {
            $pattern = '/\b'.str_replace(['\-', ' '], '[\s-]+',preg_quote($name, '/')).'/iu';
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return $text;
    }

    public static function all(): array
    {
        return array_keys(self::COORDINATES);
    }

    public static function options(bool $includeAll = true): array
    {
        $options = collect(self::all())->mapWithKeys(fn (string $barangay): array => [
            $barangay => $barangay,
        ])->all();

        return $includeAll ? ['' => 'All Barangays'] + $options : $options;
    }

    /**
     * Labels used by account-management forms. The stored values remain the
     * canonical names used throughout the application.
     *
     * @return array<string, string> canonical value => display label
     */
    public static function userManagementOptions(): array
    {
        return collect(self::all())->mapWithKeys(function (string $barangay): array {
            $label = preg_replace('/ \(Pob\.\)$/', '', $barangay) ?: $barangay;

            return [$barangay => $label];
        })->all();
    }

    /**
     * Values accepted at the account-management boundary.
     */
    public static function userManagementInputValues(): array
    {
        return array_values(array_unique(array_merge(
            self::all(),
            array_values(self::userManagementOptions()),
        )));
    }

    public static function coordinates(): array
    {
        return self::COORDINATES;
    }

    /**
     * The barangay part of a realtime channel name ("barangay.San_Diego"). Pusher/Reverb channel names allow only
     * letters, digits and -_=@,.; so a space ("San Diego") is sent as "_". canonicalName() reads it back, so the
     * channel authorization in routes/channels.php is unchanged. Keep in step with channelKey() in barangay-risk-map.js.
     */
    public static function channelKey(string $barangay): string
    {
        return (string) preg_replace('/[^-a-zA-Z0-9_=@,.;]/', '_', self::canonicalName($barangay) ?? $barangay);
    }

    public static function coordinateFor(string $barangay): ?array
    {
        $canonical = self::canonicalName($barangay);

        return $canonical ? self::COORDINATES[$canonical] : null;
    }

    public static function canonicalName(?string $barangay): ?string
    {
        $needle = self::normalize((string) $barangay);

        if ($needle === '') {
            return null;
        }

        foreach (array_keys(self::COORDINATES) as $name) {
            if (self::normalize($name) === $needle) {
                return $name;
            }
        }

        // Keep local Poblacion aliases at the system boundary while all map
        // and risk logic uses one stable canonical name.
        return match ($needle) {
            '1 bonbon', 'bonbon', '1 bonbon poblacion' => 'Barangay 1 (Pob.)',
            '5 tabok city', 'tabok city', '5 tabok city poblacion' => 'Barangay 5 (Pob.)',
            // Boundary files and local government exports use these shorter
            // spellings; keep the stored/application name canonical.
            'puting kahoy' => 'Puting-Kahoy',
            '1 poblacion', 'poblacion 1' => 'Barangay 1 (Pob.)',
            '2 poblacion', 'poblacion 2' => 'Barangay 2 (Pob.)',
            '3 poblacion', 'poblacion 3' => 'Barangay 3 (Pob.)',
            '4 poblacion', 'poblacion 4' => 'Barangay 4 (Pob.)',
            '5 poblacion', 'poblacion 5' => 'Barangay 5 (Pob.)',
            default => self::closestName($needle),
        };
    }

    /**
     * A misspelt name typed into a farm or profile ("LUYAHN", "BALIBGO"): the one official barangay within one letter
     * (two for names of nine letters or more). Numbers never match this way (Barangay 1 must not become Barangay 2), and
     * a tie between two barangays matches neither.
     */
    private static function closestName(string $needle): ?string
    {
        if (strlen($needle) < 5 || preg_match('/\d/', $needle)) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $tied = false;
        foreach (array_keys(self::COORDINATES) as $name) {
            $candidate = self::normalize($name);
            if (preg_match('/\d/', $candidate)) {
                continue;
            }
            $distance = levenshtein($needle, $candidate);
            if ($distance < $bestDistance) {
                [$best, $bestDistance, $tied] = [$name, $distance, false];
            } elseif ($distance === $bestDistance) {
                $tied = true;
            }
        }
        $allowed = strlen($needle) >= 9 ? 2 : 1;

        return $best !== null && ! $tied && $bestDistance <= $allowed ? $best : null;
    }

    private static function normalize(string $barangay): string
    {
        $value = strtolower($barangay);
        $value = str_replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
        // "BARANGAY 1 POB" (no dot) is Barangay 1 (Pob.) too.
        $value = preg_replace('/\bpob\b/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
