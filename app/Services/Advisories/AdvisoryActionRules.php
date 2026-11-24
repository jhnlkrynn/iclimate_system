<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use Illuminate\Support\Str;

/**
 * Single source of truth for turning any advisory's hazard/text, severity,
 * and optional farmer crop stage into 1-3 simple, physical, verb-first
 * actions (<= 12 words each) in English and Filipino.
 */
class AdvisoryActionRules
{
    /** Priority order: most urgent first. */
    public const HAZARD_PRIORITY = [
        'typhoon',
        'flood',
        'thunder',
        'heat',
        'fair',
    ];

    /** @var array<string, string> */
    private const HAZARD_PATTERNS = [
        'typhoon' => '/\b(typhoon|super\s*typhoon|tropical\s*(cyclone|storm|depression)|wind\s*signal|signal\s*no\.?|bagyo|buhawi|storm\s*surge|malakas\s+na\s+hangin|strong\s+wind)\b/i',
        'flood' => '/\b(flood(ing|ed)?|heavy\s+rain(fall)?|excessive\s+rain|torrential|red\s+warning|orange\s+warning|yellow\s+warning|monsoon|habagat|baha|pagbaha|malakas\s+na\s+ulan)\b/i',
        'thunder' => '/\b(thunderstorms?|thunder|lightning|isolated\s+rain(showers?)?|scattered\s+rain(showers?)?|rain\s*showers?|light\s+to\s+moderate\s+rain|rain\s+expected|chance\s+of\s+rain|kulog|kidlat|ulan|uulan|amihan)\b/i',
        'heat' => '/\b(heat|hot(\s+weather)?|dry(\s+spell)?|drought|el\s+nino|water\s+shortage|high\s+temperature|mainit|init|tuyo|tagtuyot)\b/i',
        'fair' => '/\b(fair\s+weather|partly\s+cloudy|sunny|clear\s+skies|maaliwalas|magandang\s+panahon|favorable)\b/i',
    ];

    /** @var array<string, array{en: list<string>, fil: list<string>}> */
    private const HAZARD_ACTIONS = [
        'typhoon' => [
            'en' => [
                'Harvest mature palay now if you can.',
                'Tie down or store tools and sacks.',
                'Follow MAO and barangay warnings; evacuate if told.',
            ],
            'fil' => [
                'Anihin na ang hinog na palay kung kaya.',
                'Itali o itago ang mga gamit at sako.',
                'Sundin ang babala ng MAO; lumikas kung pinayuhan.',
            ],
        ],
        'flood' => [
            'en' => [
                'Open the canals to drain the field.',
                'Move seeds, fertilizer and tools to a high, dry place.',
                "Don't cross flooded rivers.",
            ],
            'fil' => [
                'Buksan ang mga kanal para lumabas ang tubig.',
                'Ilipat ang punla, pataba, at gamit sa tuyong lugar.',
                'Huwag tumawid sa bumabahang ilog.',
            ],
        ],
        'thunder' => [
            'en' => [
                "Don't spray or fertilize if rain is near.",
                'Clean the canals so water can flow.',
                'Go home if you hear thunder.',
            ],
            'fil' => [
                'Huwag mag-spray o mag-abono kung uulan.',
                'Linisin ang mga kanal para dumaloy ang tubig.',
                'Umuwi agad kapag narinig ang kulog.',
            ],
        ],
        'heat' => [
            'en' => [
                'Water the field early morning or late afternoon.',
                'Keep water in the field during flowering.',
                'Rest in the shade at midday.',
            ],
            'fil' => [
                'Magpatubig nang maaga o sa hapon.',
                'Panatilihin ang tubig sa palayan habang namumulaklak.',
                'Magpahinga sa lilim kapag tanghaling tapat.',
            ],
        ],
        'fair' => [
            'en' => [
                'Spray, fertilize, or dry palay in fair weather.',
            ],
            'fil' => [
                'Mag-spray, mag-abono, o magbilad ng palay ngayon.',
            ],
        ],
    ];

    /** @var array<string, array{en: list<string>, fil: list<string>}> */
    private const DEFAULT_ACTIONS = [
        'high' => [
            'en' => [
                'Pause spraying and store farm tools safely.',
                'Keep canals clear so water can drain.',
            ],
            'fil' => [
                'Itigil muna ang pag-spray at itago ang gamit.',
                'Panatilihing malinis ang mga kanal sa palayan.',
            ],
        ],
        'moderate' => [
            'en' => [
                'Clean the canals around your field.',
                'Hold off on spraying until weather clears.',
            ],
            'fil' => [
                'Linisin ang mga kanal sa paligid ng palayan.',
                'Ipagpaliban ang pag-spray hanggang umayos ang panahon.',
            ],
        ],
        'low' => [
            'en' => [
                'Walk your field and check water depth.',
                'Keep canals and dikes clear.',
            ],
            'fil' => [
                'Tingnan ang palayan at sukat ng patubig.',
                'Panatilihing malinis ang mga kanal at pilapil.',
            ],
        ],
    ];

    /**
     * Generate 1-3 simple, physical, verb-first actions for an advisory.
     *
     * @return list<string>
     */
    public function forAdvisory(PlantingAdvisory|array $advisory, string $locale = 'en', ?string $cropStage = null): array
    {
        $lang = $locale === 'fil' ? 'fil' : 'en';

        $title = (string) data_get($advisory, 'title', '');
        $summary = $advisory instanceof PlantingAdvisory
            ? $advisory->displaySummary()
            : (string) data_get($advisory, 'summary', '');
        $message = (string) data_get($advisory, 'message', data_get($advisory, 'content', ''));
        $severity = (string) data_get($advisory, 'severity', 'low');

        $text = trim(implode(' ', array_filter([$title, $summary, $message])));

        return $this->forAttributes($text, $severity, $lang, $cropStage);
    }

    /**
     * @return list<string>
     */
    public function forAttributes(string $text, ?string $severity = null, string $locale = 'en', ?string $cropStage = null): array
    {
        $lang = $locale === 'fil' ? 'fil' : 'en';
        $hazard = $this->detectHazard($text);

        if ($hazard !== null) {
            $actions = self::HAZARD_ACTIONS[$hazard][$lang];
        } else {
            $bucket = match (strtolower((string) $severity)) {
                PlantingAdvisory::SEVERITY_CRITICAL, PlantingAdvisory::SEVERITY_HIGH => 'high',
                PlantingAdvisory::SEVERITY_MODERATE => 'moderate',
                default => 'low',
            };
            $actions = self::DEFAULT_ACTIONS[$bucket][$lang];
        }

        if (filled($cropStage)) {
            $actions = $this->applyCropStage($actions, $cropStage, $hazard, $lang);
        }

        return array_slice(array_values(array_unique($actions)), 0, 3);
    }

    public function detectHazard(string $text): ?string
    {
        foreach (self::HAZARD_PRIORITY as $hazard) {
            if (preg_match(self::HAZARD_PATTERNS[$hazard], $text)) {
                return $hazard;
            }
        }

        return null;
    }

    public function toActionString(array $actions): string
    {
        return implode(' ', array_slice(array_values(array_filter($actions)), 0, 3));
    }

    /**
     * @return array{actions_en: list<string>, actions_fil: list<string>}
     */
    public function metadataFor(string $text, ?string $severity = null, ?string $cropStage = null): array
    {
        return [
            'actions_en' => $this->forAttributes($text, $severity, 'en', $cropStage),
            'actions_fil' => $this->forAttributes($text, $severity, 'fil', $cropStage),
        ];
    }

    /**
     * Convert ALL CAPS weather bulletins into readable sentence case while preserving key acronyms.
     */
    public static function toSentenceCase(string $text): string
    {
        $trimmed = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($trimmed === '') {
            return '';
        }

        $letters = preg_replace('/[^a-zA-Z]/', '', $trimmed) ?? '';
        if ($letters !== '') {
            $upperCount = strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '');
            if (($upperCount / strlen($letters)) < 0.55) {
                return $trimmed;
            }
        }

        $lower = Str::lower($trimmed);
        $sentences = preg_split('/([.!?]\s+)/', $lower, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$lower];
        $result = '';
        foreach ($sentences as $part) {
            if ($part === '' || preg_match('/^[.!?]\s+$/', $part)) {
                $result .= $part;

                continue;
            }
            $result .= Str::ucfirst($part);
        }

        $replacements = [
            '/\bpagasa\b/i' => 'PAGASA',
            '/\bmao\b/i' => 'MAO',
            '/\blgu\b/i' => 'LGU',
            '/\bndrrmc\b/i' => 'NDRRMC',
            '/\blian\b/i' => 'Lian',
            '/\bbatangas\b/i' => 'Batangas',
            '/\bphilippines\b/i' => 'Philippines',
        ];

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $result);
    }

    /**
     * @param  list<string>  $actions
     * @return list<string>
     */
    private function applyCropStage(array $actions, string $cropStage, ?string $hazard, string $lang): array
    {
        $stage = strtolower(trim($cropStage));

        $stageAction = match (true) {
            str_contains($stage, 'flower') || str_contains($stage, 'bulaklak') || str_contains($stage, 'panicle') => match (true) {
                in_array($hazard, ['typhoon', 'flood'], true) => $lang === 'fil'
                    ? 'Dahan-dahang ilabas ang sobrang tubig sa palayan.'
                    : 'Drain excess water slowly after heavy rain.',
                default => $lang === 'fil'
                    ? 'Panatilihin ang patubig sa palayan habang namumulaklak.'
                    : 'Keep water in the field during flowering.',
            },
            str_contains($stage, 'seed') || str_contains($stage, 'punla') || str_contains($stage, 'nursery') => match (true) {
                in_array($hazard, ['typhoon', 'flood', 'thunder'], true) => $lang === 'fil'
                    ? 'Protektahan ang mga punla sa malalim na tubig.'
                    : 'Protect young punla from deep standing water.',
                default => $lang === 'fil'
                    ? 'Panatilihin ang mababaw na patubig sa punla.'
                    : 'Keep shallow water on young punla.',
            },
            str_contains($stage, 'ripen') || str_contains($stage, 'harvest') || str_contains($stage, 'mature') || str_contains($stage, 'hinog') => match (true) {
                in_array($hazard, ['heat', 'fair'], true) => $lang === 'fil'
                    ? 'Ibilad ang inaning palay sa malinis na banig.'
                    : 'Dry harvested palay on clean mats today.',
                default => $lang === 'fil'
                    ? 'Anihin nang maaga ang hinog na palay bago umulan.'
                    : 'Harvest mature palay early before rain arrives.',
            },
            default => null,
        };

        if ($stageAction === null || in_array($stageAction, $actions, true)) {
            return $actions;
        }

        if (count($actions) >= 3) {
            $actions[2] = $stageAction;
        } else {
            $actions[] = $stageAction;
        }

        return $actions;
    }
}
