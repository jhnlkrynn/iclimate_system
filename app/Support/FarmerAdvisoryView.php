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

    /**
     * Lian barangays the advisory names: an iClimate or MAO advisory's own barangay, or the barangays a PAGASA
     * bulletin itself names next to Lian. PAGASA is never credited with a barangay it did not name.
     *
     * @return list<string>
     */
    public function affectedBarangays(): array
    {
        if (! $this->isPagasa()) {
            return $this->advisory->target_barangay ? [$this->advisory->target_barangay] : [];
        }

        $text = (string) ($this->advisory->content ?: $this->advisory->message);

        return preg_match('/\bLian\b/i', $text)
            ? array_values(array_intersect(LianBarangays::all(), (array) data_get($this->advisory->metadata, 'pagasa_barangay_matches', [])))
            : [];
    }

    /** The place line at the top of a farmer card. */
    public function farmerLocation(): string
    {
        if ($this->isNationalOutlook()) {
            return 'Philippines (national outlook)';
        }
        if ($this->isPagasa()) {
            return 'Lian, Batangas';
        }

        return $this->advisory->target_barangay
            ? $this->advisory->target_barangay.', Lian, Batangas'
            : 'All of Lian, Batangas';
    }

    /** PAGASA's own coverage when it is wider than Lian (kept as issued), or null for Lian-level bulletins. */
    public function originalScope(): ?string
    {
        if (! $this->isPagasa()) {
            return null;
        }

        $name = strtolower((string) data_get($this->advisory->metadata, 'scope_name'));

        return match (true) {
            str_contains($name, 'national') => 'Whole country (national outlook)',
            data_get($this->advisory->metadata, 'scope_type') === 'regional' => 'CALABARZON region',
            data_get($this->advisory->metadata, 'scope_type') === 'province' || ($this->affectedBarangays() === [] && data_get($this->advisory->metadata, 'scope_type') === 'barangay') => 'Batangas Province',
            default => null,
        };
    }

    /**
     * "What is happening" on an Advisories card. PAGASA bulletins written in capitals ("THE REST OF THE COUNTRY
     * WILL…") are shown in normal sentence case: the same words, easier to read.
     */
    public function cardEffect(): string
    {
        $text = $this->farmerEffect();

        return preg_match('/\p{Lu}{4,}/u', $text) && ! preg_match('/\p{Ll}/u', $text)
            ? ucfirst(mb_strtolower($text))
            : $text;
    }

    public function isNationalOutlook(): bool
    {
        return $this->isPagasa() && str_contains(strtolower((string) data_get($this->advisory->metadata, 'scope_name')), 'national');
    }

    /** For a wider PAGASA bulletin: say plainly what it covers, without claiming a Lian or barangay bulletin. */
    public function coverageNote(): ?string
    {
        return match (true) {
            $this->isNationalOutlook() => 'National outlook — supporting information, not specific to Lian.',
            $this->originalScope() !== null => 'This PAGASA advisory includes Lian, Batangas.',
            default => null,
        };
    }

    /**
     * The status badge on an Advisories card, from the advisory's own severity (set by the rule thresholds or by
     * PAGASA's warning level). "Information" advice, such as a chance to skip irrigation, is shown as Advice,
     * never as a Low risk.
     *
     * @return array{label: string, tone: string, icon: string}
     */
    public function statusBadge(): array
    {
        return match ($this->advisory->severity) {
            PlantingAdvisory::SEVERITY_CRITICAL => ['label' => 'Severe', 'tone' => 'danger', 'icon' => 'alert'],
            PlantingAdvisory::SEVERITY_HIGH => ['label' => 'High', 'tone' => 'danger', 'icon' => 'alert'],
            PlantingAdvisory::SEVERITY_MODERATE => ['label' => 'Moderate', 'tone' => 'watch', 'icon' => 'alert'],
            PlantingAdvisory::SEVERITY_LOW => ['label' => 'Low', 'tone' => 'good', 'icon' => 'check'],
            default => ['label' => $this->isPagasa() ? 'Information' : 'Advice', 'tone' => 'info', 'icon' => 'info'],
        };
    }

    /**
     * PAGASA's own table for the agri outlook, exactly as published (stored when it was fetched). For a row saved
     * before the table was read, the four weather values are read from the stored bulletin text; lists that cannot
     * be split reliably are left to the View Details popup.
     *
     * @return array{weather: array<string, string>, crops: array<string, list<string>>, farm_operations: list<string>}|null
     */
    public function pagasaDetails(): ?array
    {
        if (! $this->isPagasa()) {
            return null;
        }
        $stored = data_get($this->advisory->metadata, 'pagasa_details');
        if (is_array($stored) && ! empty($stored['weather'])) {
            return ['weather' => (array) $stored['weather'], 'crops' => (array) ($stored['crops'] ?? []), 'farm_operations' => (array) ($stored['farm_operations'] ?? [])];
        }

        $text = (string) ($this->advisory->content ?: $this->advisory->message);
        $range = '(\d+(?:\.\d+)?\s*[–-]\s*\d+(?:\.\d+)?)';
        $weather = array_filter([
            'rainfall_mm' => preg_match('/Rainfall \(mm\):\s*'.$range.'/u', $text, $m) ? $m[1] : null,
            'relative_humidity_percent' => preg_match('/Relative Humidity \(%\):\s*'.$range.'/u', $text, $m) ? $m[1] : null,
            'rainy_days' => preg_match('/Rainy Days[^:]*:\s*'.$range.'/u', $text, $m) ? $m[1] : null,
            'temperature_c' => preg_match('/Temperature \(°C\):\s*((?:Upland|Lowland)[^A-Z]*?(?:;\s*(?:Upland|Lowland)[^A-Z]*?)?)\s+(?=[A-Z]{3,})/u', $text, $m) ? trim($m[1]) : null,
        ]);

        return $weather ? ['weather' => $weather, 'crops' => [], 'farm_operations' => []] : null;
    }

    /**
     * The forecast values an iClimate rule advisory was calculated from (stored with it), for a short "Based on"
     * line: rainfall, rain chance, highest temperature and wind. Empty for advice without stored values.
     *
     * @return array<string, string>  icon => text
     */
    public function basedOn(): array
    {
        $basis = (array) data_get($this->advisory->metadata, 'weather_basis', []);
        if ($this->isPagasa() || $basis === []) {
            return [];
        }
        $number = fn ($value) => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') : null;

        return array_filter([
            'rain' => ($v = $number($basis['expected_rainfall_mm'] ?? $basis['rainfall_24h_mm'] ?? null)) !== null ? $v.' mm rain' : null,
            'weather' => ($v = $number($basis['rain_probability_percent'] ?? $basis['precip_probability_percent'] ?? null)) !== null ? $v.'% chance' : null,
            'temperature' => ($v = $number($basis['maximum_temperature_c'] ?? $basis['temperature_c'] ?? null)) !== null ? $v.'°C' : null,
            'wind' => ($v = $number($basis['maximum_wind_speed_kmh'] ?? $basis['wind_speed_kmh'] ?? null)) !== null ? $v.' km/h wind' : null,
        ]);
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
        // iClimate's current barangay guidance starts with a list of readings (shown separately on the card); its
        // plain answer is the topic in its title, e.g. "Rain Expected in the Next 24 Hours" → "Rain expected in the next 24 hours."
        if (str_starts_with((string) $this->advisory->generation_key, 'local-current:') && str_contains((string) $this->advisory->title, ': ')) {
            return ucfirst(strtolower(rtrim(substr((string) $this->advisory->title, strrpos((string) $this->advisory->title, ': ') + 2), '.'))).'.';
        }

        return $this->quickSummary()[0] ?? ($this->farmEffects()[0] ?? 'Weather conditions may affect rice fields.');
    }

    public function farmerAction(): string
    {
        $actions = $this->actions();

        // Advice often opens with what is happening ("Rain is likely today."); the card's "What to do" is the first
        // sentence that tells the farmer what to do ("Consider delaying fertilizer ...").
        return collect($actions)->first(fn (string $sentence) => (bool) preg_match(
            '/^(?:Check|Consider|Avoid|Delay|Postpone|Clear|Keep|Move|Use|Wait|Water|Plant|Harvest|Prepare|Inspect|Continue|Follow|Drain|Secure|Protect|Apply|Ask|Stay|Do not|Don\'t|Pause|Make|Give|Reduce|Remove|Cover|Store|Monitor|Watch|Suriin|Iwasan|Ihanda|Hintayin|Bantayan|Ituloy)\b/i',
            $sentence,
        )) ?? $actions[0] ?? 'Check your field before doing farm work.';
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
