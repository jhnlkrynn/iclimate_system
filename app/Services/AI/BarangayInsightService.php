<?php

namespace App\Services\AI;

use App\Services\Risk\BarangayRiskMapService;
use App\Support\LianBarangays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Heat Map's Barangay AI Insight: one barangay's verified iClimate values (the same entry the risk map and the
 * Barangays Monitored list show) plus a short plain-language explanation of them.
 *
 *   shared barangay weather + current verified risk  →  structured context  →  Climora (Groq) explains it
 *
 * The values are never calculated here: risk comes from the risk engine's current map and weather from the shared
 * barangay weather store. The AI only words them, and its reply is checked (short, plain words, nothing invented,
 * numbers and risk exactly as supplied) before it is shown. One explanation is kept per barangay, weather time, risk
 * time and language, so the same data is never explained twice.
 */
class BarangayInsightService
{
    public const CACHE_PREFIX = 'ai:barangay-insight:v1:';

    public const LANGUAGES = ['en', 'fil'];

    /** A good explanation is kept until its data changes; the key already holds the weather and risk times. */
    private const INSIGHT_TTL_HOURS = 6;

    /** A failed or rejected explanation is retried after this long, not on every click. */
    private const FAILURE_TTL_SECONDS = 60;

    public const MAX_INSIGHT_SENTENCES = 1;

    public const MAX_ACTION_SENTENCES = 1;

    public const MAX_INSIGHT_WORDS = 25;

    public const MAX_ACTION_WORDS = 20;

    public const MAX_NUMBERS = 2;

    private const TECHNICAL_WORDS = '/\b(meteorolog\w*|precipitation|evapotranspiration|probabilit\w*|inference|models?|confidence|classif\w*|normali[sz]\w*|algorithms?|random\s+forest|machine\s+learning|training|dataset|data|api|cache|query|synchroni[sz]\w*|aggregat\w*|computation|anomal\w*)\b/iu';

    /** Topics the supplied data never contains, so any mention would be invented. */
    private const INVENTED_TOPICS = '/\b(typhoons?|bagyo|floods?|flooding|baha|bumaha|pagbaha|droughts?|tagtuyot|diseases?|sakit|pests?|peste|insects?|insekto|yields?|ani|aanihin|january|february|march|april|june|july|august|september|october|november|december|enero|pebrero|marso|abril|mayo|hunyo|hulyo|agosto|setyembre|oktubre|nobyembre|disyembre)\b/iu';

    private const RISK_WORDS = ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'severe' => 'Severe',
        'mababa' => 'Low', 'katamtaman' => 'Moderate', 'mataas' => 'High', 'malubha' => 'Severe', 'matindi' => 'Severe'];

    /** The tone for each verified risk level (wording guide only; the level itself comes from iClimate). */
    private const TONE = [
        'Low' => 'Conditions are generally good. Suggest continuing normal field monitoring.',
        'Moderate' => 'Conditions need some attention. Suggest checking the field before watering or major farm work.',
        'High' => 'Weather conditions may affect farm activities. Suggest checking drainage and field conditions before continuing work.',
        'Severe' => 'Conditions need close attention. Suggest checking official advisories and inspecting the field before farm work.',
    ];

    /** iClimate's own short action for each verified risk level, shown when no AI explanation is available. */
    private const ACTION = [
        'en' => [
            'Low' => 'Continue normal farm work.',
            'Moderate' => 'Check your field before watering or doing major farm work.',
            'High' => 'Check drainage and field conditions before continuing work.',
            'Severe' => 'Check official advisories and inspect your field before doing farm work.',
            '' => 'Check your field and wait for the next update.',
        ],
        'fil' => [
            'Low' => 'Ituloy ang karaniwang trabaho sa bukid.',
            'Moderate' => 'Suriin muna ang lupa bago magdilig o gumawa ng malaking trabaho.',
            'High' => 'Silipin ang kanal at ang bukid bago ituloy ang trabaho.',
            'Severe' => 'Sundin ang opisyal na abiso at silipin ang bukid bago magtrabaho.',
            '' => 'Suriin ang bukid at hintayin ang susunod na update.',
        ],
    ];

    public function __construct(
        private readonly BarangayRiskMapService $riskMap,
        private readonly GroqChatService $groq,
    ) {}

    /**
     * The insight for one barangay, or null when the name is not one of Lian's 19 barangays.
     *
     * @return array<string, mixed>|null
     */
    public function forBarangay(string $barangay, string $language = 'en'): ?array
    {
        $canonical = LianBarangays::canonicalName($barangay);

        if ($canonical === null) {
            return null;
        }

        $language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
        // Exactly what GET /api/lian/risk-map/{barangay} serves: the current verified rating with the latest shared weather.
        $entry = $this->riskMap->detailFor($canonical, false);
        $entry = $entry !== null ? $this->riskMap->withCurrentWeather(['barangays' => [$entry]])['barangays'][0] : ['barangay' => $canonical];

        $data = $this->verifiedData($canonical, $entry);
        $insight = $this->insight($data, $language);

        return [
            'barangay' => $canonical,
            'language' => $language,
            'freshness' => $data['weather_status'] === 'current' && $data['risk_status'] === 'current' ? 'current' : 'latest_available',
            'data' => $data,
            'insight' => $insight,
            'notices' => $this->notices($data),
            // Changes only when the weather or the risk changes; the page re-asks for an insight only then.
            'signature' => self::signature($data['weather_updated_at'], $data['risk_calculated_at']),
        ];
    }

    public static function signature(?string $weatherUpdatedAt, ?string $riskCalculatedAt): string
    {
        return ($weatherUpdatedAt ?? '-').'|'.($riskCalculatedAt ?? '-');
    }

    /**
     * The values shown on the cards and given to the AI, copied from the risk-map entry (never recalculated).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function verifiedData(string $barangay, array $entry): array
    {
        $weather = (array) ($entry['weather'] ?? []);
        $level = in_array($entry['risk_level'] ?? null, ['Low', 'Moderate', 'High', 'Severe'], true) ? $entry['risk_level'] : null;
        $hasWeather = ($weather['status'] ?? 'unavailable') !== 'unavailable' && is_numeric($weather['temperature_c'] ?? null);

        return [
            'risk_level' => $level,
            // A kept older rating while a newer one is made ("last known good") is not today's rating yet.
            'risk_status' => $level === null ? 'unavailable' : (($entry['last_known_good'] ?? false) ? 'refreshing' : 'current'),
            'risk_calculated_at' => $level !== null ? ($entry['risk_calculated_at'] ?? null) : null,
            'risk_source' => 'iClimate',
            'weather_status' => ! $hasWeather ? 'unavailable' : (in_array($weather['status'] ?? null, ['fresh', 'recent'], true) ? 'current' : 'delayed'),
            'temperature_c' => $hasWeather ? $this->number($weather['temperature_c'] ?? null) : null,
            'humidity_percent' => $hasWeather ? $this->number($weather['humidity_percent'] ?? null) : null,
            'rainfall_24h_mm' => $hasWeather ? $this->number($weather['rainfall_24h_mm'] ?? null) : null,
            'rainfall_status' => $hasWeather ? ($weather['rainfall_status'] ?? null) : null,
            'condition' => $hasWeather ? ($weather['condition'] ?? null) : null,
            'weather_updated_at' => $hasWeather ? ($weather['fetched_at'] ?? null) : null,
            'weather_source' => $weather['provider'] ?? 'Open-Meteo',
            'barangay' => $barangay,
        ];
    }

    private function number(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function notices(array $data): array
    {
        return array_values(array_filter([
            $data['weather_status'] === 'delayed' ? 'Based on the last available weather update.' : null,
            $data['weather_status'] === 'unavailable' ? 'Latest weather is temporarily unavailable.' : null,
            $data['risk_status'] !== 'current' ? 'Risk is being updated.' : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: string, text: ?string, action: string, action_source: string, cached: bool}
     */
    private function insight(array $data, string $language): array
    {
        $unavailable = [
            'status' => 'unavailable',
            'text' => null,
            'action' => self::ACTION[$language][$data['risk_level'] ?? ''],
            'action_source' => 'iclimate',
            'cached' => false,
        ];

        // Nothing verified to explain: no AI call, and nothing is guessed.
        if ($data['risk_level'] === null && $data['weather_status'] === 'unavailable') {
            return $unavailable;
        }

        $key = self::CACHE_PREFIX.md5(implode('|', [$data['barangay'], $data['weather_updated_at'], $data['risk_calculated_at'], $data['risk_level'], $language]));
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached['status'] === 'ok' ? ['cached' => true] + $cached : $unavailable;
        }

        $context = $this->context($data);

        try {
            $reply = $this->groq->barangayInsight($context, $language);
        } catch (Throwable $exception) {
            Log::warning('Barangay AI insight failed.', ['barangay' => $data['barangay'], 'message' => $exception->getMessage()]);
            $reply = null;
        }

        $checked = $reply !== null ? self::check($reply, $context) : null;

        if ($reply !== null && $checked === null) {
            Log::info('Barangay AI insight rejected by the farmer-language check.', ['barangay' => $data['barangay'], 'language' => $language]);
        }

        if ($checked === null) {
            Cache::put($key, ['status' => 'unavailable'], now()->addSeconds(self::FAILURE_TTL_SECONDS));

            return $unavailable;
        }

        $result = ['status' => 'ok', 'text' => $checked['insight'], 'action' => $checked['action'], 'action_source' => 'ai'];
        Cache::put($key, $result, now()->addHours(self::INSIGHT_TTL_HOURS));

        return $result + ['cached' => false];
    }

    /**
     * Only what the explanation needs: the barangay's verified values and their freshness (no ids, scores, farmers
     * or other database fields).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function context(array $data): array
    {
        $time = fn (?string $value): ?string => $value ? Carbon::parse($value)->setTimezone('Asia/Manila')->format('g:i A') : null;

        return [
            'barangay' => $data['barangay'],
            'risk_level' => $data['risk_level'],
            'risk_status' => $data['risk_status'],
            'temperature_c' => $data['temperature_c'],
            'humidity_percent' => $data['humidity_percent'],
            'rainfall_next_24h_mm' => $data['rainfall_24h_mm'],
            'rain_outlook' => $data['rainfall_status'],
            'condition' => $data['condition'],
            'weather_status' => $data['weather_status'],
            'weather_updated' => $time($data['weather_updated_at']),
            'risk_calculated' => $time($data['risk_calculated_at']),
            'tone' => self::TONE[$data['risk_level'] ?? ''] ?? 'The risk level is not available. Explain the weather only.',
        ];
    }

    /**
     * The farmer-language check every AI reply passes before it is shown. A reply that is only too long is shortened
     * to its first sentence (or first clause); it is rejected only when it is unusable (a list, headings, or one
     * sentence too long to cut cleanly) or unsafe anywhere, including the part cut off: technical words, invented
     * topics or dates, numbers not supplied, or a risk level other than the one iClimate rated. Returns the
     * {insight, action} to show, or null.
     *
     * @param  array{insight: string, action: string}  $reply
     * @param  array<string, mixed>  $context
     * @return array{insight: string, action: string}|null
     */
    public static function check(array $reply, array $context): ?array
    {
        $parts = [];
        $whole = [];

        foreach (['insight' => self::MAX_INSIGHT_WORDS, 'action' => self::MAX_ACTION_WORDS] as $field => $maxWords) {
            $raw = trim((string) ($reply[$field] ?? ''));

            // Lists, headings or several paragraphs ignore the format entirely: there is no one sentence to keep.
            if (preg_match('/[*#•\n]/u', $raw)) {
                return null;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $raw), " \t\"'“”‘’");
            $short = self::shorten($text, $maxWords);

            if ($short === null) {
                return null;
            }

            $whole[] = $text;
            $parts[$field] = $short;
        }

        // Safety is judged on the whole reply: a made-up claim in a sentence that is cut off still means it is not trusted.
        $all = implode(' ', $whole);

        if (preg_match(self::TECHNICAL_WORDS, $all) || preg_match(self::INVENTED_TOPICS, $all)) {
            return null;
        }

        // Delayed or missing readings are never presented as live.
        if (($context['weather_status'] ?? null) !== 'current' && preg_match('/\b(live|real[- ]?time)\b/iu', $all)) {
            return null;
        }

        if (! self::numbersAreSupplied($all, $context) || ! self::riskMatches($all, $context['risk_level'] ?? null)) {
            return null;
        }

        // The cards already show the values; an explanation that lists them is a data dump, not plain words.
        if (preg_match_all('/\d+(?::\d+|\.\d+)?/u', $parts['insight'].' '.$parts['action']) > self::MAX_NUMBERS) {
            return null;
        }

        return $parts;
    }

    /**
     * One short sentence from a longer reply: the first sentence, or the first clause of a semicolon-joined one,
     * ending with a full stop. Null when it is empty or still longer than $maxWords (cutting it would break it).
     */
    private static function shorten(string $text, int $maxWords): ?string
    {
        $first = trim((string) (preg_split('/(?<=[.!?])\s+/u', $text)[0] ?? ''));
        $first = trim(explode(';', $first)[0]);

        if ($first === '' || self::wordCount($first) > $maxWords) {
            return null;
        }

        return preg_match('/[.!?]$/u', $first) ? $first : rtrim($first, ',: ').'.';
    }

    private static function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text)));
    }

    /** Every number in the reply is one of the supplied values (or the "24" of "next 24 hours"), exactly. */
    private static function numbersAreSupplied(string $text, array $context): bool
    {
        $allowed = ['24'];

        foreach (['temperature_c', 'humidity_percent', 'rainfall_next_24h_mm'] as $field) {
            if (is_numeric($context[$field] ?? null)) {
                $allowed[] = FarmerAnswerStyle::number($context[$field]);
            }
        }

        foreach (['weather_updated', 'risk_calculated'] as $field) {
            if (is_string($context[$field] ?? null) && preg_match('/^(\d+):(\d+)/', $context[$field], $time)) {
                array_push($allowed, $time[1], $time[2], $time[1].':'.$time[2]);
            }
        }

        preg_match_all('/\d+(?::\d+|\.\d+)?/u', $text, $matches);

        foreach ($matches[0] as $number) {
            if (! in_array($number, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** A risk level named in the reply is the verified one; with no verified rating, no risk level is named at all. */
    private static function riskMatches(string $text, ?string $level): bool
    {
        $words = implode('|', array_keys(self::RISK_WORDS));
        // "Moderate risk", "high-risk", "mataas na panganib", "mababang panganib"; then "risk is High", "panganib ay mababa".
        preg_match_all('/\b('.$words.')(?:ng)?(?:\s+na)?[\s-]+(?:risk|panganib)\b/iu', $text, $before);
        preg_match_all('/\b(?:risk|panganib)\s+(?:ay\s+|is\s+)?('.$words.')\b/iu', $text, $after);
        $named = array_map(fn (string $word) => self::RISK_WORDS[mb_strtolower($word)], [...$before[1], ...$after[1]]);

        if ($level === null) {
            return $named === [] && ! preg_match('/\b(risk|panganib)\b/iu', $text);
        }

        return array_diff($named, [$level]) === [];
    }
}
