<?php

namespace App\Support;

use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Turns an advisory into the plain-language pieces shown in the farmer's advisory popup
 * (resources/views/advisories/_modal.blade.php).
 *
 * Only wording and layout change here: every sentence comes from the advisory itself,
 * every number comes unchanged from AdvisoryWeatherService, and nothing is stored.
 * The one addition is the short "what this can mean for your field" lines, which are
 * fixed explanations picked by the kind of weather the advisory itself names.
 */
class FarmerAdvisoryView
{
    /** Short explanations per kind of weather, worded with "may/can" so they never overstate the advisory. */
    private const FARM_EFFECTS = [
        'typhoon' => [
            'Strong wind and heavy rain can damage rice plants and farm structures.',
            'Low-lying fields may flood.',
        ],
        'heavy_rain' => [
            'Low-lying fields may flood or hold too much water.',
            'Heavy rain can wash away fertilizer or spray.',
        ],
        'thunderstorm' => [
            'Sudden strong rain and lightning are possible.',
            'Working in an open field during a thunderstorm is not safe.',
        ],
        'rain' => [
            'Your field may get extra water from the rain.',
            'Rain can wash away fertilizer or spray put on just before it falls.',
        ],
        'heat' => [
            'Hot weather dries the soil and field water faster.',
            'Heat can stress rice plants and people working in the field.',
        ],
        'dry' => [
            'Your field may get less rain than usual, so water may run low.',
            'Young rice plants need enough water to grow well.',
        ],
        'wind' => [
            'Strong wind can bend or knock down tall rice plants.',
        ],
    ];

    private const HAZARD_PATTERNS = [
        'typhoon' => '/typhoon|tropical (storm|depression|cyclone)|signal no/i',
        'heavy_rain' => '/heavy rain|flood|excessive rain/i',
        'thunderstorm' => '/thunderstorm/i',
        'rain' => '/\brain|shower/i',                       // \b: not "drainage"
        'heat' => '/\bheat\b|hot weather|high temperature/i',
        'dry' => '/\bdry\b|dry spell|drought|below[- ]normal rain|water shortage/i',
        'wind' => '/strong wind|gust/i',
    ];

    public function __construct(private readonly PlantingAdvisory $advisory) {}

    public function isPagasa(): bool
    {
        return $this->advisory->isOfficialPagasa();
    }

    /** Who the advice comes from, in words a farmer knows. */
    public function sourceName(?User $viewer = null): string
    {
        $source = (string) $this->advisory->source;

        if ($viewer?->role === User::ROLE_IT_EXPERT) {
            return $source ?: 'Open-Meteo + iClimate Rules';   // IT staff keep the exact technical source
        }

        return match (true) {
            $this->isPagasa() => 'PAGASA',
            stripos($source, 'mao') !== false => 'Municipal Agriculture Office (MAO)',
            default => 'iClimate, based on the weather forecast',
        };
    }

    /** Small line above the title. */
    public function eyebrow(): string
    {
        return match (true) {
            $this->isPagasa() => 'Official PAGASA Advisory',
            stripos((string) $this->advisory->source, 'mao') !== false => 'Advice from the Municipal Agriculture Office',
            default => 'Farm advice from iClimate',
        };
    }

    public function area(): string
    {
        return $this->advisory->target_barangay
            ? 'Barangay '.$this->advisory->target_barangay.', Lian'
            : 'All barangays in Lian';
    }

    public function farmerArea(): string
    {
        if ($scope = data_get($this->advisory->metadata, 'scope_name')) {
            return $scope;
        }

        return $this->advisory->target_barangay
            ? 'Barangay '.$this->advisory->target_barangay.', Lian, Batangas'
            : 'Lian, Batangas';
    }

    public function farmerRiskLevel(): string
    {
        return match ($this->advisory->severity) {
            PlantingAdvisory::SEVERITY_CRITICAL => 'Severe',
            PlantingAdvisory::SEVERITY_HIGH => 'High',
            PlantingAdvisory::SEVERITY_MODERATE => 'Moderate',
            default => 'Low',
        };
    }

    public function farmerRiskTone(): string
    {
        return match ($this->farmerRiskLevel()) {
            'Severe', 'High' => 'danger',
            'Moderate' => 'watch',
            default => 'good',
        };
    }

    public function farmerEffect(): string
    {
        return $this->quickSummary()[0] ?? ($this->farmEffects()[0] ?? 'Weather conditions may affect rice fields.');
    }

    public function farmerAction(): string
    {
        return $this->actions()[0] ?? 'Check your field before doing farm work.';
    }

    public function farmerSourceLabel(): string
    {
        return match (true) {
            $this->isPagasa() => 'PAGASA',
            stripos((string) $this->advisory->source, 'open-meteo') !== false => 'Open-Meteo + iClimate Local Guidance',
            stripos((string) $this->advisory->source, 'pagasa') !== false => 'Unverified source',
            default => $this->advisory->plainSourceLabel(),
        };
    }

    public function officialUrl(): ?string
    {
        if (! $this->isPagasa()) {
            return null;
        }

        return $this->advisory->source_url ?: (data_get($this->advisory->metadata, 'pagasa_source_url') ?: null);
    }

    /** Only levels that call for extra care are shown, so ordinary advice does not look alarming. */
    public function alertLevel(): ?array
    {
        return match ($this->advisory->severity) {
            PlantingAdvisory::SEVERITY_MODERATE => ['label' => 'Alert level: Moderate', 'tone' => 'watch'],
            PlantingAdvisory::SEVERITY_HIGH => ['label' => 'Alert level: High', 'tone' => 'danger'],
            PlantingAdvisory::SEVERITY_CRITICAL => ['label' => 'Alert level: Critical', 'tone' => 'danger'],
            default => null,
        };
    }

    /** 1–3 short sentences: the advisory summary without system prefixes. */
    public function quickSummary(): array
    {
        $sentences = $this->sentences($this->stripPrefix($this->advisory->displaySummary()));

        return array_slice($sentences, 0, 3);
    }

    /** What the weather means for the field: the advisory's own situation lines, then the fixed explanations. */
    public function farmEffects(): array
    {
        [$situation] = $this->messageParts();
        $lines = $this->without($situation, $this->quickSummary());

        $hazardText = implode(' ', [$this->advisory->title, $this->advisory->displaySummary(), implode(' ', $situation)]);
        $hazards = [];
        foreach (self::HAZARD_PATTERNS as $hazard => $pattern) {
            if (preg_match($pattern, $hazardText)) {
                $hazards[] = $hazard;
            }
        }
        // The stronger kind of rain already covers ordinary rain.
        if (array_intersect($hazards, ['typhoon', 'heavy_rain', 'thunderstorm'])) {
            $hazards = array_values(array_diff($hazards, ['rain']));
        }
        if (in_array('typhoon', $hazards, true)) {
            $hazards = array_values(array_diff($hazards, ['heavy_rain', 'wind']));
        }

        foreach (array_slice($hazards, 0, 2) as $hazard) {
            foreach (self::FARM_EFFECTS[$hazard] as $line) {
                $lines[] = $line;
            }
        }

        return $this->unique($lines);
    }

    /** What to do: the recommended action, plus any farm guidance written into the message. */
    public function actions(): array
    {
        [, $guidance] = $this->messageParts();
        $recommended = $this->sentences((string) $this->advisory->recommended_action);

        $actions = $this->unique(array_merge($recommended, $guidance));
        if ($actions === []) {
            $actions = ['Check your field before doing any farm work.', 'Ask the Municipal Agriculture Office if you are unsure what to do.'];
        }

        if (! preg_grep('/monitor|update|pagasa|advisor/i', $actions)) {
            $actions[] = 'Keep checking for new advisories and PAGASA updates.';
        }

        return $actions;
    }

    /** Short safety reminder that replaces the long disclaimer. */
    public function importantNote(): array
    {
        if ($this->isPagasa()) {
            return [
                'This advice is based on PAGASA weather information for Lian or Batangas.',
                'During severe weather, always follow official PAGASA and local government safety announcements.',
            ];
        }

        return [
            'This advice is based on the weather forecast. Conditions on your farm may be different, so check your field first.',
            'During severe weather, always follow official PAGASA and local government safety announcements.',
        ];
    }

    /**
     * The four weather values with farmer-friendly labels. Values are passed through unchanged;
     * a missing value stays missing (null) and is shown as "Not available".
     *
     * @param  array<int, array{key: string, value: ?string, detail: ?string}>  $rows
     */
    public static function weatherCards(array $rows): array
    {
        $labels = [
            'rainfall' => ['Expected Rain', 'Expected during this advisory'],
            'probability' => ['Chance of Rain', 'Chance that rain may occur'],
            'temperature' => ['Highest Temperature', 'Hottest it may get'],
            'wind' => ['Wind Speed', 'Highest steady wind expected'],
        ];

        $cards = [];
        foreach ($rows as $row) {
            [$label, $note] = $labels[$row['key']] ?? [$row['label'], null];
            if ($row['key'] === 'wind' && preg_match('/gusts up to ([\d.]+ km\/h)/', (string) $row['detail'], $gust)) {
                $note = 'Short gusts may reach '.$gust[1];
            }
            $cards[] = ['key' => $row['key'], 'label' => $label, 'value' => $row['value'], 'note' => $row['value'] === null ? null : $note];
        }

        return $cards;
    }

    /** One or two plain sentences about where the weather numbers come from and any limits on them. */
    public static function weatherNotes(array $wx): array
    {
        $notes = [];
        $messages = implode(' ', $wx['messages'] ?? []);

        if (($wx['status'] ?? null) === 'snapshot') {
            $notes[] = 'This is the forecast saved when this advice was made.';
        }
        if (! empty($wx['stale']) && ! empty($wx['updated'])) {
            $notes[] = 'A new forecast could not be loaded just now, so the last one is shown (from '
                .Carbon::parse($wx['updated'])->timezone('Asia/Manila')->format('M j, g:i A').').';
        }
        if (str_contains($messages, 'only part') || str_contains($messages, 'ends before')) {
            $notes[] = 'The forecast covers only part of this advisory’s dates.';
        }
        if (! empty($wx['window_note'])) {
            $notes[] = 'This advice has no end date, so the next 24 hours are shown.';
        }

        return $notes;
    }

    /* ------------------------------------------------------------------ text helpers */

    /** @return array{0: string[], 1: string[]} [situation sentences, farm guidance sentences] */
    private function messageParts(): array
    {
        $situation = [];
        $guidance = [];

        foreach (preg_split('/\R+/u', $this->advisory->displayMessage()) as $line) {
            $line = trim(preg_replace('/^[\s•·\-\*\?]+/u', '', $line));
            if ($line === '' || preg_match('/^Official PAGASA .*Advisory for/i', $line)) {
                continue;
            }

            if (preg_match('/^(Weather Situation|Farm Guidance|Details|Source)\s*:\s*(.*)$/i', $line, $match)) {
                $label = strtolower($match[1]);
                if ($label === 'weather situation') {
                    array_push($situation, ...$this->sentences($match[2]));
                } elseif ($label === 'farm guidance') {
                    array_push($guidance, ...$this->sentences($match[2]));
                }

                // "Details" is a raw copy of the PAGASA web page and "Source" is shown in its own section.
                continue;
            }

            foreach ($this->sentences($this->stripPrefix($line)) as $sentence) {
                // System notes about the forecast window and how the advisory was generated.
                if (preg_match('/^(Forecast period|Horizon)\s*:|^This advisory is generated from/i', $sentence)) {
                    continue;
                }
                $situation[] = $sentence;
            }
        }

        return [$situation, $guidance];
    }

    private function stripPrefix(string $text): string
    {
        return trim(preg_replace('/^(PAGASA (Update|Notice|Warning|Alert)|\d+-Day outlook)\s*:\s*/i', '', trim($text)));
    }

    /** @return string[] */
    private function sentences(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"“(])/u', $text);

        return array_values(array_filter(array_map(function (string $part) {
            $part = trim($part);

            return $part === '' || preg_match('/[.!?]$/', $part) ? $part : $part.'.';
        }, $parts)));
    }

    private function key(string $sentence): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($sentence));
    }

    /** @return string[] */
    private function unique(array $lines): array
    {
        $seen = [];
        $out = [];
        foreach ($lines as $line) {
            $key = $this->key($line);
            if ($key !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $line;
            }
        }

        return $out;
    }

    /** @return string[] */
    private function without(array $lines, array $remove): array
    {
        $removeKeys = array_map(fn ($line) => $this->key($line), $remove);

        return array_values(array_filter($lines, fn ($line) => ! in_array($this->key($line), $removeKeys, true)));
    }
}
